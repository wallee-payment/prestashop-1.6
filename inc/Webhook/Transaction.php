<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2024 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle transaction state transitions.
 */
class WalleeWebhookTransaction extends WalleeWebhookOrderrelatedabstract
{

    /**
     *
     * @see WalleeWebhookOrderrelatedabstract::loadEntity()
     * @return \Wallee\Sdk\Model\Transaction
     */
    protected function loadEntity(WalleeWebhookRequest $request)
    {
        $transactionService = new \Wallee\Sdk\Service\TransactionService(
            WalleeHelper::getApiClient()
        );
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
        $transactionInfo = WalleeModelTransactioninfo::loadByOrderId($order->id);
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
        if (WalleeHelper::getOrderMeta($sourceOrder, 'authorized')) {
            return;
        }
        // Do not send emails for this status update
        WalleeBasemodule::startRecordingMailMessages();
        WalleeHelper::updateOrderMeta($sourceOrder, 'authorized', true);
        $authorizedStatusId = Configuration::get(WalleeBasemodule::CK_STATUS_AUTHORIZED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($authorizedStatusId);
            $order->save();
        }
        WalleeBasemodule::stopRecordingMailMessages();
        if (Configuration::get(WalleeBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Send stored messages
            $messages = WalleeHelper::getOrderEmails($sourceOrder);
            if (count($messages) > 0) {
                if (method_exists('Mail', 'sendMailMessageWithoutHook')) {
                    foreach ($messages as $message) {
                        Mail::sendMailMessageWithoutHook($message, false);
                    }
                }
            }
        }
        WalleeHelper::deleteOrderEmails($order);
        // Cleanup carts
        $originalCartId = WalleeHelper::getOrderMeta($order, 'originalCart');
        if (! empty($originalCartId)) {
            $cart = new Cart($originalCartId);
            $cart->delete();
        }
        WalleeServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function waiting(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        WalleeBasemodule::startRecordingMailMessages();
        $waitingStatusId = Configuration::get(WalleeBasemodule::CK_STATUS_COMPLETED);
        if (! WalleeHelper::getOrderMeta($sourceOrder, 'manual_check')) {
            $orders = $sourceOrder->getBrother();
            $orders[] = $sourceOrder;
            foreach ($orders as $order) {
                $order->setCurrentState($waitingStatusId);
                $order->save();
            }
        }
        WalleeBasemodule::stopRecordingMailMessages();
        WalleeServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function decline(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (! Configuration::get(WalleeBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Do not send email
            WalleeBasemodule::startRecordingMailMessages();
        }

        $canceledStatusId = Configuration::get(WalleeBasemodule::CK_STATUS_DECLINED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($canceledStatusId);
            $order->save();
        }
        WalleeBasemodule::stopRecordingMailMessages();
        WalleeServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function failed(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        // Do not send email
        WalleeBasemodule::startRecordingMailMessages();
        $errorStatusId = Configuration::get(WalleeBasemodule::CK_STATUS_FAILED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($errorStatusId);
            $order->save();
        }
        WalleeBasemodule::stopRecordingMailMessages();
        WalleeHelper::deleteOrderEmails($sourceOrder);
        WalleeServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function fulfill(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (! Configuration::get(WalleeBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Do not send email
            WalleeBasemodule::startRecordingMailMessages();
        }
        $payedStatusId = Configuration::get(WalleeBasemodule::CK_STATUS_FULFILL);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($payedStatusId);
            if (empty($order->invoice_date) || $order->invoice_date == '0000-00-00 00:00:00') {
                // Make sure invoice date is set, otherwise prestashop ignores the order in the statistics
                $order->invoice_date = date('Y-m-d H:i:s');
            }
            $order->save();
        }
        WalleeBasemodule::stopRecordingMailMessages();
        WalleeServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function voided(\Wallee\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (! Configuration::get(WalleeBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Do not send email
            WalleeBasemodule::startRecordingMailMessages();
        }
        $canceledStatusId = Configuration::get(WalleeBasemodule::CK_STATUS_VOIDED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($canceledStatusId);
            $order->save();
        }
        WalleeBasemodule::stopRecordingMailMessages();
        WalleeServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }
}
