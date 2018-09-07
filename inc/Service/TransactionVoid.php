<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * This service provides functions to deal with wallee transaction voids.
 */
class Wallee_Service_TransactionVoid extends Wallee_Service_Abstract
{

    /**
     * The transaction void API service.
     *
     * @var \Wallee\Sdk\Service\TransactionVoidService
     */
    private $voidService;

    public function executeVoid($order)
    {
        
        $currentVoidId = null;
        try {
            Wallee_Helper::startDBTransaction();
            $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    Wallee_Helper::getModuleInstance()->l('Could not load corresponding transaction.','transactionvoid'));
            }
           
            Wallee_Helper::lockByTransactionId($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            //Reload after locking
            $transactionInfo = Wallee_Model_TransactionInfo::loadByTransaction($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            $spaceId = $transactionInfo->getSpaceId();
            $transactionId = $transactionInfo->getTransactionId();
            
            if ($transactionInfo->getState() != \Wallee\Sdk\Model\TransactionState::AUTHORIZED) {
                throw new Exception(Wallee_Helper::getModuleInstance()->l('The transaction is not in a state to be voided.','transactionvoid'));
            }            
            if (Wallee_Model_VoidJob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    Wallee_Helper::getModuleInstance()->l('Please wait until the existing void is processed.','transactionvoid'));
            }
            if (Wallee_Model_CompletionJob::isCompletionRunningForTransaction(
                $spaceId, $transactionId)){
                    throw new Exception( Wallee_Helper::getModuleInstance()->l('There is a completion in process. The order can not be voided.','transactionvoid'));
            }
            
            $voidJob = new Wallee_Model_VoidJob();
            $voidJob->setSpaceId($spaceId);
            $voidJob->setTransactionId($transactionId);
            $voidJob->setState(Wallee_Model_VoidJob::STATE_CREATED);
            $voidJob->setOrderId(Wallee_Helper::getOrderMeta($order, 'walleeMainOrderId'));
            $voidJob->save();
            $currentVoidId = $voidJob->getId();
            Wallee_Helper::commitDBTransaction();
        }
        catch (Exception $e) {
            Wallee_Helper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendVoid($currentVoidId);
        
    }

    protected function sendVoid($voidJobId)
    {        
        $voidJob = new Wallee_Model_VoidJob($voidJobId);
        Wallee_Helper::startDBTransaction();
        Wallee_Helper::lockByTransactionId($voidJob->getSpaceId(), $voidJob->getTransactionId());
        // Reload void job;
        $voidJob = new Wallee_Model_VoidJob($voidJobId);
        if ($voidJob->getState() != Wallee_Model_VoidJob::STATE_CREATED) {
            // Already sent in the meantime
            Wallee_Helper::rollbackDBTransaction();
            return;
        }
        try {                        
            $void = $this->getVoidService()->voidOnline($voidJob->getSpaceId(), $voidJob->getTransactionId());
            $voidJob->setVoidId($void->getId());
            $voidJob->setState(Wallee_Model_VoidJob::STATE_SENT);
            $voidJob->save();
            Wallee_Helper::commitDBTransaction();
        }
        catch (Exception $e) {
            $voidJob->setFailureReason(
                array(
                    'en-US' => sprintf(
                        Wallee_Helper::getModuleInstance()->l('Could not send the void to %s. Error: %s','transactionvoid'), 'wallee',
                        Wallee_Helper::cleanExceptionMessage($e->getMessage()))
                ));
            $voidJob->setState(Wallee_Model_VoidJob::STATE_FAILURE);
            $voidJob->save();
            Wallee_Helper::commitDBTransaction();
            throw $e;
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $voidJob = Wallee_Model_VoidJob::loadRunningVoidForTransaction($spaceId, $transactionId);
        if ($voidJob->getState() == Wallee_Model_VoidJob::STATE_CREATED) {
            $this->sendVoid($voidJob->getId());
        }
    }

    public function updateVoids($endTime = null)
    {
        $toProcess = Wallee_Model_VoidJob::loadNotSentJobIds();

        foreach ($toProcess as $id) {
            if($endTime!== null && time()+15 > $endTime){
                return;
            }
            
            try {
                $this->sendVoid($id);
            }
            catch (Exception $e) {
                $message = sprintf(
                    Wallee_Helper::getModuleInstance()->l('Error updating void job with id %d: %s','transactionvoid'), $id,
                    $e->getMessage());
                PrestaShopLogger::addLog($message, 3, null, 'Wallee_Model_VoidJob');
                
            }
        }
    }
    
    public function hasPendingVoids(){
        $toProcess = Wallee_Model_VoidJob::loadNotSentJobIds();
        return !empty($toProcess);
    }

    /**
     * Returns the transaction void API service.
     *
     * @return \Wallee\Sdk\Service\TransactionVoidService
     */
    protected function getVoidService()
    {
        if ($this->voidService == null) {
            $this->voidService = new \Wallee\Sdk\Service\TransactionVoidService(
                Wallee_Helper::getApiClient());
        }
        
        return $this->voidService;
    }
}
