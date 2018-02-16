<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

class WalleeCronModuleFrontController extends ModuleFrontController
{

    public $display_column_left = false;

    public $ssl = true;

    public function postProcess()
    {
        ob_end_clean();
        // Return request but keep executing
        set_time_limit(0);
        ignore_user_abort(true);
        ob_start();
        if(session_id()){
            session_write_close();
        }
        header("Content-Encoding: none");
        header("Connection: close");
        header('Content-Type: image/png');
        header("Content-Length: 0");
        ob_end_flush();
        flush();
        if(is_callable('fastcgi_finish_request')){
            fastcgi_finish_request();
        }
        
        $securityToken = Tools::getValue('security_token', false);
        if (! $securityToken) {
            die();
        }
        $time = new DateTime();
        Wallee_Helper::startDBTransaction();
        try{
           
            $sqlUpdate = 'UPDATE ' . _DB_PREFIX_ . 'wle_cron_job SET constraint_key = 0, state = "' .
                 pSQL(Wallee_Cron::STATE_PROCESSING) . '" , date_started = "' .
                 pSQL($time->format('Y-m-d H:i:s')) . '" WHERE security_token = "' . pSQL(
                    $securityToken) . '" AND state = "' . pSQL(Wallee_Cron::STATE_PENDING) . '"';
            
            $updateResult = DB::getInstance()->execute($sqlUpdate, false);
            if (! $updateResult) {
                $code = DB::getInstance()->getNumberError();
                if ($code == Wallee::MYSQL_DUPLICATE_CONSTRAINT_ERROR_CODE) {
                    Wallee_Helper::rollbackDBTransaction();
                    // Another Cron already running
                    die();
                }
                else {
                    Wallee_Helper::rollbackDBTransaction();
                    PrestaShopLogger::addLog(
                        'Could not update cron job. ' . DB::getInstance()->getMsgError(), 2, null,
                        'Wallee');
                    die();
                }
            }
            if (DB::getInstance()->Affected_Rows() == 0) {
                // Simultaneous Request
                Wallee_Helper::commitDBTransaction();
                die();
            }
        }
        catch(PrestaShopDatabaseException $e){
            $code = DB::getInstance()->getNumberError();
            if ($code == Wallee::MYSQL_DUPLICATE_CONSTRAINT_ERROR_CODE) {
                Wallee_Helper::rollbackDBTransaction();
                // Another Cron already running
                die();
            }
            else {
                Wallee_Helper::rollbackDBTransaction();
                PrestaShopLogger::addLog(
                    'Could not update cron job. ' . DB::getInstance()->getMsgError(), 2, null,
                    'Wallee');
                die();
            }
        }
        Wallee_Helper::commitDBTransaction();
        
        // We reduce max running time, so th cron has time to clean up.
        $maxTime = $time->format("U");
        $maxTime += Wallee_Cron::MAX_RUN_TIME_MINUTES * 60 - 60;
        
        $tasks = Hook::exec("walleeCron", array(), null, true, false);
        $error = array();
        foreach ($tasks as $module => $subTasks) {
            foreach ($subTasks as $subTask) {
                if ($maxTime - 15 < time()) {
                    $error[] = "Cron overloaded could not execute all registered tasks.";
                    break 2;
                }
                if (! is_callable($subTask, false, $callableName)) {
                    $error[] = "Module '$module' returns not callable task '$callableName'.";
                    continue;
                }
                try {
                    call_user_func($subTask, $maxTime);
                }
                catch (Exception $e) {
                    $error[] = "Module '$module' does not handle all exceptions in task '$callableName'. Exception Message: " .
                         $e->getMessage();
                }
                if ($maxTime + 15 < time()) {
                    $error[] = "Module '$module' returns not callable task '$callableName' does not respect the max runtime.";
                    break 2;
                }
            }
        }
        Wallee_Helper::startDBTransaction();
        try{
            $status = Wallee_Cron::STATE_SUCCESS;
            $errorMessage = "";
            if (! empty($error)) {
                $status = Wallee_Cron::STATE_ERROR;
                $errorMessage = implode("\n", $error);
            }
            $endTime = new DateTime();
            $sqlUpdate = 'UPDATE ' . _DB_PREFIX_ . 'wle_cron_job SET constraint_key = id_cron_job, state = "' .
                 pSQL($status) . '" , date_finished = "' . pSQL($endTime->format('Y-m-d H:i:s')) .
                 '", error_msg = "'.pSQL($errorMessage).'" WHERE security_token = "' . pSQL($securityToken) . '" AND state = "' .
                 pSQL(Wallee_Cron::STATE_PROCESSING) . '"';
            
            $updateResult = DB::getInstance()->execute($sqlUpdate, false);
            if (! $updateResult) {
                Wallee_Helper::rollbackDBTransaction();
                PrestaShopLogger::addLog(
                    'Could not update finished cron job. ' . DB::getInstance()->getMsgError(), 2, null,
                    'Wallee');
                die();
            }
            Wallee_Helper::commitDBTransaction();
        }
        catch(PrestaShopDatabaseException $e){
            Wallee_Helper::rollbackDBTransaction();
            PrestaShopLogger::addLog(
                'Could not update finished cron job. ' . DB::getInstance()->getMsgError(), 2, null,
                'Wallee');
            die();
        }        
        Wallee_Cron::insertNewPendingCron();
        die();
    }

    public function setMedia()
    {
        // We do not need styling here
    }

    protected function displayMaintenancePage()
    {
        // We never display the maintenance page.
    }

    protected function displayRestrictedCountryPage()
    {
        // We do not want to restrict the content by any country.
    }

    protected function canonicalRedirection($canonical_url = '')
    {
        // We do not need any canonical redirect
    }
}