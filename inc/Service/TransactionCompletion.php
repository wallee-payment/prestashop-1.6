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
 * This service provides functions to deal with wallee transaction completions.
 */
class Wallee_Service_TransactionCompletion extends Wallee_Service_Abstract
{

    /**
     * The transaction completion API service.
     *
     * @var \Wallee\Sdk\Service\TransactionCompletionService
     */
    private $completionService;

    public function executeCompletion($order)
    {
        $currentCompletionJob = null;
        try {
            Wallee_Helper::startDBTransaction();
            $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    Wallee_Helper::getModuleInstance()->l('Could not load corresponding transaction.','transactioncompletion'));
            }
           
            Wallee_Helper::lockByTransactionId($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            //Reload after locking
            $transactionInfo = Wallee_Model_TransactionInfo::loadByTransaction($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            $spaceId = $transactionInfo->getSpaceId();
            $transactionId = $transactionInfo->getTransactionId();
            
            if ($transactionInfo->getState() != \Wallee\Sdk\Model\TransactionState::AUTHORIZED) {
                throw new Exception(Wallee_Helper::getModuleInstance()->l('The transaction is not in a state to be completed.','transactioncompletion'));
            }
            
            if (Wallee_Model_CompletionJob::isCompletionRunningForTransaction(
                $spaceId, $transactionId)){
                    throw new Exception( Wallee_Helper::getModuleInstance()->l('Please wait until the existing completion is processed.','transactioncompletion'));
            }
            
            if (Wallee_Model_VoidJob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    Wallee_Helper::getModuleInstance()->l('There is a void in process. The order can not be completed.','transactioncompletion'));
            }

            $completionJob = new Wallee_Model_CompletionJob();
            $completionJob->setSpaceId($spaceId);
            $completionJob->setTransactionId($transactionId);
            $completionJob->setState(Wallee_Model_CompletionJob::STATE_CREATED);
            $completionJob->setOrderId(Wallee_Helper::getOrderMeta($order, 'walleeMainOrderId'));
            $completionJob->save();
            $currentCompletionJob = $completionJob->getId();
            Wallee_Helper::commitDBTransaction();
        }
        catch (Exception $e) {
            Wallee_Helper::rollbackDBTransaction();
            throw $e;
        }
        
        try {
            $this->updateLineItems($currentCompletionJob);
            $this->sendCompletion($currentCompletionJob);
        }
        catch (Exception $e) {
            throw $e;            
        }
    }
    
    
    protected function updateLineItems($completionJobId){
        
        $completionJob = new Wallee_Model_CompletionJob($completionJobId);
        Wallee_Helper::startDBTransaction();
        Wallee_Helper::lockByTransactionId($completionJob->getSpaceId(), $completionJob->getTransactionId());
        // Reload completion job;
        $completionJob = new Wallee_Model_CompletionJob($completionJobId);
        
        if ($completionJob->getState() != Wallee_Model_CompletionJob::STATE_CREATED) {
            //Already updated in the meantime
            Wallee_Helper::rollbackDBTransaction();
            return;
        }
        try {
            $baseOrder = new Order($completionJob->getOrderId());
            $collected = $baseOrder->getBrother()->getResults();
            $collected[] = $baseOrder;
            
            $lineItems = Wallee_Service_LineItem::instance()->getItemsFromOrders($collected);
            Wallee_Service_Transaction::instance()->updateLineItems($completionJob->getSpaceId(), $completionJob->getTransactionId(), $lineItems);
            $completionJob->setState(Wallee_Model_CompletionJob::STATE_ITEMS_UPDATED);
            $completionJob->save();
            Wallee_Helper::commitDBTransaction();
        }
        catch (Exception $e) {
            $completionJob->setFailureReason(
                array(
                    'en-US' => sprintf(
                        Wallee_Helper::getModuleInstance()->l('Could not update the line items. Error: %s','transactioncompletion'),
                        Wallee_Helper::cleanExceptionMessage($e->getMessage()))
                ));
            
            $completionJob->setState(Wallee_Model_CompletionJob::STATE_FAILURE);
            $completionJob->save();
            Wallee_Helper::commitDBTransaction();
            throw $e;
        }
    }

    protected function sendCompletion($completionJobId)
    {        
        $completionJob = new Wallee_Model_CompletionJob($completionJobId);
        Wallee_Helper::startDBTransaction();
        Wallee_Helper::lockByTransactionId($completionJob->getSpaceId(), $completionJob->getTransactionId());
        // Reload completion job;
        $completionJob = new Wallee_Model_CompletionJob($completionJobId);
        
        if ($completionJob->getState() != Wallee_Model_CompletionJob::STATE_ITEMS_UPDATED) {
            // Already sent in the meantime
            Wallee_Helper::rollbackDBTransaction();
            return;
        }
        try {                        
            $completion = $this->getCompletionService()->completeOnline($completionJob->getSpaceId(), $completionJob->getTransactionId());
            $completionJob->setCompletionId($completion->getId());
            $completionJob->setState(Wallee_Model_CompletionJob::STATE_SENT);
            $completionJob->save();
            Wallee_Helper::commitDBTransaction();
        }
        catch (Exception $e) {
            $completionJob->setFailureReason(
                array(
                    'en-US' => sprintf(
                        Wallee_Helper::getModuleInstance()->l('Could not send the completion to %s. Error: %s','transactioncompletion'), 'wallee',
                        Wallee_Helper::cleanExceptionMessage($e->getMessage()))
                ));
            $completionJob->setState(Wallee_Model_CompletionJob::STATE_FAILURE);
            $completionJob->save();
            Wallee_Helper::commitDBTransaction();
            throw $e;
        }
    }
    

    public function updateForOrder($order)
    {
        $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $completionJob = Wallee_Model_CompletionJob::loadRunningCompletionForTransaction($spaceId, $transactionId);
        $this->updateLineItems($completionJob->getId());
        $this->sendCompletion($completionJob->getId());
    }
        
    public function updateCompletions($endTime = null)
    {
        $toProcess = Wallee_Model_CompletionJob::loadNotSentJobIds();
        foreach ($toProcess as $id) {
            if($endTime!== null && time()+15 > $endTime){
                return;
            }
            try {
                $this->updateLineItems($id);
                $this->sendCompletion($id);
            }
            catch (Exception $e) {
                $message = sprintf(
                    Wallee_Helper::getModuleInstance()->l('Error updating completion job with id %d: %s','transactioncompletion'), $id,
                    $e->getMessage());
                PrestaShopLogger::addLog($message, 3, null, 'Wallee_Model_CompletionJob');
                
            }
        }
    }
    
    public function hasPendingCompletions(){
        $toProcess = Wallee_Model_CompletionJob::loadNotSentJobIds();
        return !empty($toProcess);
    }

     
    /**
     * Returns the transaction completion API service.
     *
     * @return \Wallee\Sdk\Service\TransactionCompletionService
     */
    protected function getCompletionService()
    {
        if ($this->completionService == null) {
            $this->completionService = new \Wallee\Sdk\Service\TransactionCompletionService(
                Wallee_Helper::getApiClient());
        }
        return $this->completionService;
    }
}
