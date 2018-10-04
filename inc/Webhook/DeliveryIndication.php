<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2018 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle delivery indication state transitions.
 */
class Wallee_Webhook_DeliveryIndication extends Wallee_Webhook_OrderRelatedAbstract
{

    /**
     *
     * @see Wallee_Webhook_OrderRelatedAbstract::loadEntity()
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    protected function loadEntity(Wallee_Webhook_Request $request)
    {
        $deliveryIndicationService = new \Wallee\Sdk\Service\DeliveryIndicationService(
            Wallee_Helper::getApiClient()
        );
        return $deliveryIndicationService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($deliveryIndication)
    {
        /* @var \Wallee\Sdk\Model\DeliveryIndication $deliveryIndication */
        return $deliveryIndication->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($deliveryIndication)
    {
        /* @var \Wallee\Sdk\Model\DeliveryIndication $delivery_indication */
        return $deliveryIndication->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Order $order, $deliveryIndication)
    {
        /* @var \Wallee\Sdk\Model\DeliveryIndication $deliveryIndication */
        switch ($deliveryIndication->getState()) {
            case \Wallee\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED:
                $this->review($order);
                break;
            default:
                break;
        }
    }

    protected function review(Order $sourceOrder)
    {
        Wallee::startRecordingMailMessages();
        $manualStatusId = Configuration::get(Wallee::CK_STATUS_MANUAL);
        Wallee_Helper::updateOrderMeta($sourceOrder, 'manual_check', true);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($manualStatusId);
            $order->save();
        }
        Wallee::stopRecordingMailMessages();
    }
}
