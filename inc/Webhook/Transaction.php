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
 * Webhook processor to handle transaction state transitions.
 */
class Wallee_Webhook_Transaction extends Wallee_Webhook_OrderRelatedAbstract
{

    /**
     *
     * @see Wallee_Webhook_OrderRelatedAbstract::loadEntity()
     * @return \Wallee\Sdk\Model\Transaction
     */
    protected function loadEntity(Wallee_Webhook_Request $request)
    {
        $transactionService = new \Wallee\Sdk\Service\TransactionService(Wallee_Helper::getApiClient());
        return $transactionService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($transaction)
    {
        /* @var \Wallee\Sdk\Model\Transaction $transaction */
        return $transaction->getMerchantReference();
    }

    protected function getTransactionId($transaction)
    {
        /* @var \Wallee\Sdk\Model\Transaction $transaction */
        return $transaction->getId();
    }

    protected function processOrderRelatedInner(Order $order, $transaction)
    {
        /* @var \Wallee\Sdk\Model\Transaction $transaction */
        $transactionInfo = Wallee_Model_TransactionInfo::loadByOrderId($order->id);
        if ($transaction->getState() != $transactionInfo->getState()) {
            switch ($transaction->getState()) {
                case \Wallee\Sdk\Model\TransactionState::AUTHORIZED:
                    $this->authorize($transaction, $order);
                    break;
                case \Wallee\Sdk\Model\TransactionState::DECLINE:
                    $this->decline($transaction, $order);
                    break;
                case \Wallee\Sdk\Model\TransactionState::FAILED:
                    $this->failed($transaction, $order);
                    break;
                case \Wallee\Sdk\Model\TransactionState::FULFILL:
                    $this->authorize($transaction, $order);
                    $this->fulfill($transaction, $order);
                    break;
                case \Wallee\Sdk\Model\TransactionState::VOIDED:
                    $this->voided($transaction, $order);
                    break;
                case \Wallee\Sdk\Model\TransactionState::COMPLETED:
                    $this->waiting($transaction, $order);
                    break;
                default:
                    // Nothing to do.
                    break;
            }
        }
    }


    protected function authorize(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (Wallee_Helper::getOrderMeta($sourceOrder, 'authorized')) {
            return;
        }
        //Do not send emails for this status update
        Wallee::startRecordingMailMessages();
        Wallee_Helper::updateOrderMeta($sourceOrder, 'authorized', true);
        $authorizedStatusId = Configuration::get(Wallee::CK_STATUS_AUTHORIZED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($authorizedStatusId);
            $order->save();
        }
        Wallee::stopRecordingMailMessages();
        if (Configuration::get(Wallee::CK_MAIL, null, null, $sourceOrder->id_shop)) {
           //Send stored messages
            $messages = Wallee_Helper::getOrderEmails($sourceOrder);
            if (count($messages) > 0) {
                if (method_exists('Mail', 'sendMailMessageWithoutHook')) {
                    foreach ($messages as $message) {
                        Mail::sendMailMessageWithoutHook($message, false);
                    }
                }
            }
        }
        Wallee_Helper::deleteOrderEmails($order);
        //Cleanup carts
        $originalCartId = Wallee_Helper::getOrderMeta($order, 'originalCart');
        if (!empty($originalCartId)) {
            $cart = new Cart($originalCartId);
            $cart->delete();
        }
        Wallee_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function waiting(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        Wallee::startRecordingMailMessages();
        $waitingStatusId = Configuration::get(Wallee::CK_STATUS_COMPLETED);
        if (! Wallee_Helper::getOrderMeta($sourceOrder, 'manual_check')) {
            $orders = $sourceOrder->getBrother();
            $orders[] = $sourceOrder;
            foreach ($orders as $order) {
                $order->setCurrentState($waitingStatusId);
                $order->save();
            }
        }
        Wallee::stopRecordingMailMessages();
        Wallee_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function decline(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (!Configuration::get(Wallee::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            //Do not send email
            Wallee::startRecordingMailMessages();
        }
        $canceledStatusId = Configuration::get(Wallee::CK_STATUS_DECLINED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($canceledStatusId);
            $order->save();
        }
        Wallee::stopRecordingMailMessages();
        Wallee_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function failed(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        //Do not send email
        Wallee::startRecordingMailMessages();
        $errorStatusId = Configuration::get(Wallee::CK_STATUS_FAILED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($errorStatusId);
            $order->save();
        }
        Wallee::stopRecordingMailMessages();
        Wallee_Helper::deleteOrderEmails($sourceOrder);
        Wallee_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function fulfill(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (!Configuration::get(Wallee::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            //Do not send email
            Wallee::startRecordingMailMessages();
        }
        $payedStatusId = Configuration::get(Wallee::CK_STATUS_FULFILL);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($payedStatusId);
            $order->save();
        }
        Wallee::stopRecordingMailMessages();
        Wallee_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function voided(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (!Configuration::get(Wallee::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            //Do not send email
            Wallee::startRecordingMailMessages();
        }
        $canceledStatusId = Configuration::get(Wallee::CK_STATUS_VOIDED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($canceledStatusId);
            $order->save();
        }
        Wallee::stopRecordingMailMessages();
        Wallee_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }
}
