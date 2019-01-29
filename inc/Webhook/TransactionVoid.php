<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle transaction void state transitions.
 */
class Wallee_Webhook_TransactionVoid extends Wallee_Webhook_OrderRelatedAbstract
{

    /**
     *
     * @see Wallee_Webhook_OrderRelatedAbstract::loadEntity()
     * @return \Wallee\Sdk\Model\TransactionVoid
     */
    protected function loadEntity(Wallee_Webhook_Request $request)
    {
        $voidService = new \Wallee\Sdk\Service\TransactionVoidService(Wallee_Helper::getApiClient());
        return $voidService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($void)
    {
        /* @var \Wallee\Sdk\Model\TransactionVoid $void */
        return $void->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($void)
    {
        /* @var \Wallee\Sdk\Model\TransactionVoid $void */
        return $void->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Order $order, $void)
    {
        /* @var \Wallee\Sdk\Model\TransactionVoid $void */
        switch ($void->getState()) {
            case \Wallee\Sdk\Model\TransactionVoidState::FAILED:
                $this->update($void, $order, false);
                break;
            case \Wallee\Sdk\Model\TransactionVoidState::SUCCESSFUL:
                $this->update($void, $order, true);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function update(\Wallee\Sdk\Model\TransactionVoid $void, Order $order, $success)
    {
        $voidJob = Wallee_Model_VoidJob::loadByVoidId($void->getLinkedSpaceId(), $void->getId());
        if (!$voidJob->getId()) {
            //We have no void job with this id -> the server could not store the id of the void after sending the request. (e.g. connection issue or crash)
            //We only have on running void which was not yet processed successfully and use it as it should be the one the webhook is for.
            $voidJob = Wallee_Model_VoidJob::loadRunningVoidForTransaction($void->getLinkedSpaceId(), $void->getLinkedTransaction());
            if (!$voidJob->getId()) {
                //void not initated in shop backend ignore
                return;
            }
            $voidJob->setVoidId($void->getId());
        }
        if ($success) {
            $voidJob->setState(Wallee_Model_VoidJob::STATE_SUCCESS);
        } else {
            if ($voidJob->getFailureReason() != null) {
                $voidJob->setFailureReason($void->getFailureReason()->getDescription());
            }
            $voidJob->setState(Wallee_Model_VoidJob::STATE_FAILURE);
        }
        $voidJob->save();
    }
}
