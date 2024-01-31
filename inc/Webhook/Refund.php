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
 * Webhook processor to handle refund state transitions.
 */
class WalleeWebhookRefund extends WalleeWebhookOrderrelatedabstract
{

    /**
     * Processes the received order related webhook request.
     *
     * @param WalleeWebhookRequest $request
     */
    public function process(WalleeWebhookRequest $request)
    {
        parent::process($request);
        $refund = $this->loadEntity($request);
        $refundJob = WalleeModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getState() == WalleeModelRefundjob::STATE_APPLY) {
            WalleeServiceRefund::instance()->applyRefundToShop($refundJob->getId());
        }
    }

    /**
     *
     * @see WalleeWebhookOrderrelatedabstract::loadEntity()
     * @return \Wallee\Sdk\Model\Refund
     */
    protected function loadEntity(WalleeWebhookRequest $request)
    {
        $refundService = new \Wallee\Sdk\Service\RefundService(
            WalleeHelper::getApiClient()
        );
        return $refundService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($refund)
    {
        /* @var \Wallee\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($refund)
    {
        /* @var \Wallee\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getId();
    }

    protected function processOrderRelatedInner(Order $order, $refund)
    {
        /* @var \Wallee\Sdk\Model\Refund $refund */
        switch ($refund->getState()) {
            case \Wallee\Sdk\Model\RefundState::FAILED:
                $this->failed($refund, $order);
                break;
            case \Wallee\Sdk\Model\RefundState::SUCCESSFUL:
                $this->refunded($refund, $order);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function failed(\Wallee\Sdk\Model\Refund $refund, Order $order)
    {
        $refundJob = WalleeModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getId()) {
            $refundJob->setState(WalleeModelRefundjob::STATE_FAILURE);
            $refundJob->setRefundId($refund->getId());
            if ($refund->getFailureReason() != null) {
                $refundJob->setFailureReason($refund->getFailureReason()
                    ->getDescription());
            }
            $refundJob->save();
        }
    }

    protected function refunded(\Wallee\Sdk\Model\Refund $refund, Order $order)
    {
        $refundJob = WalleeModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getId()) {
            $refundJob->setState(WalleeModelRefundjob::STATE_APPLY);
            $refundJob->setRefundId($refund->getId());
            $refundJob->save();
        }
    }
}
