<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

class AdminWalleeOrderController extends ModuleAdminController
{

    public function postProcess()
    {
        parent::postProcess();
        exit();
    }

    public function initProcess()
    {
        parent::initProcess();
        $access = Profile::getProfileAccess($this->context->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders'));
        if ($access['edit'] === '1' && ($action = Tools::getValue('walleeAction'))) {
            $this->action = $action;
        }
        else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => Wallee_Helper::translatePS(
                        'You do not have permission to edit the order.')
                ));
            die();
        }
    }

    public function processUpdateOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                Wallee_Service_TransactionVoid::instance()->updateForOrder($order);
                Wallee_Service_TransactionCompletion::instance()->updateForOrder($order);
                echo Tools::jsonEncode(array(
                    'success' => 'true'
                ));
                die();
            }
            catch (Exception $e) {
                echo Tools::jsonEncode(
                    array(
                        'success' => 'false',
                        'message' => $e->getMessage()
                    ));
                die();
            }
        }
        else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => Wallee_Helper::translatePS('Incomplete Request.')
                ));
            die();
        }
    }

    public function processVoidOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                Wallee_Service_TransactionVoid::instance()->executeVoid($order);
                echo Tools::jsonEncode(
                    array(
                        'success' => 'true',
                        'message' => Wallee_Helper::translatePS(
                            'The order is updated automatically once the void is processed.')
                    ));
                die();
            }
            catch (Exception $e) {
                echo Tools::jsonEncode(
                    array(
                        'success' => 'false',
                        'message' => Wallee_Helper::cleanExceptionMessage($e->getMessage())
                    ));
                die();
            }
        }
        else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => Wallee_Helper::translatePS('Incomplete Request.')
                ));
            die();
        }
    }
    
    public function processCompleteOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                Wallee_Service_TransactionCompletion::instance()->executeCompletion($order);
                echo Tools::jsonEncode(
                    array(
                        'success' => 'true',
                        'message' => Wallee_Helper::translatePS(
                            'The order is updated automatically once the completion is processed.')
                    ));
                die();
            }
            catch (Exception $e) {
                echo Tools::jsonEncode(
                    array(
                        'success' => 'false',
                        'message' => Wallee_Helper::cleanExceptionMessage($e->getMessage())
                    ));
                die();
            }
        }
        else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => Wallee_Helper::translatePS('Incomplete Request.')
                ));
            die();
        }
    }
}
    