<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * This service provides functions to deal with wallee refunds.
 */
class WalleeServiceRefund extends WalleeServiceAbstract
{
    private static $refundableStates = array(
        \Wallee\Sdk\Model\TransactionState::COMPLETED,
        \Wallee\Sdk\Model\TransactionState::DECLINE,
        \Wallee\Sdk\Model\TransactionState::FULFILL
    );

    /**
     * The refund API service.
     *
     * @var \Wallee\Sdk\Service\RefundService
     */
    private $refundService;

    /**
     * Returns the refund by the given external id.
     *
     * @param int $spaceId
     * @param string $externalId
     * @return \Wallee\Sdk\Model\Refund
     */
    public function getRefundByExternalId($spaceId, $externalId)
    {
        $query = new \Wallee\Sdk\Model\EntityQuery();
        $query->setFilter($this->createEntityFilter('externalId', $externalId));
        $query->setNumberOfEntities(1);
        $result = $this->getRefundService()->search($spaceId, $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            throw new Exception('The refund could not be found.');
        }
    }

    public function executeRefund(Order $order, array $parsedParameters)
    {
        $currentRefundJob = null;
        try {
            WalleeHelper::startDBTransaction();
            $transactionInfo = WalleeHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    WalleeHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction',
                        'refund'
                    )
                );
            }

            WalleeHelper::lockByTransactionId(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            // Reload after locking
            $transactionInfo = WalleeModelTransactioninfo::loadByTransaction(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            $spaceId = $transactionInfo->getSpaceId();
            $transactionId = $transactionInfo->getTransactionId();

            if (! in_array($transactionInfo->getState(), self::$refundableStates)) {
                throw new Exception(
                    WalleeHelper::getModuleInstance()->l(
                        'The transaction is not in a state to be refunded.',
                        'refund'
                    )
                );
            }

            if (WalleeModelRefundjob::isRefundRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    WalleeHelper::getModuleInstance()->l(
                        'Please wait until the existing refund is processed.',
                        'refund'
                    )
                );
            }

            $refundJob = new WalleeModelRefundjob();
            $refundJob->setState(WalleeModelRefundjob::STATE_CREATED);
            $refundJob->setOrderId($order->id);
            $refundJob->setSpaceId($transactionInfo->getSpaceId());
            $refundJob->setTransactionId($transactionInfo->getTransactionId());
            $refundJob->setExternalId(uniqid($order->id . '-'));
            $refundJob->setRefundParameters($parsedParameters);
            $refundJob->save();
            // validate Refund Job
            $this->createRefundObject($refundJob);
            $currentRefundJob = $refundJob->getId();
            WalleeHelper::commitDBTransaction();
        } catch (Exception $e) {
            WalleeHelper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendRefund($currentRefundJob);
    }

    protected function sendRefund($refundJobId)
    {
        $refundJob = new WalleeModelRefundjob($refundJobId);
        WalleeHelper::startDBTransaction();
        WalleeHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
        // Reload refund job;
        $refundJob = new WalleeModelRefundjob($refundJobId);
        if ($refundJob->getState() != WalleeModelRefundjob::STATE_CREATED) {
            // Already sent in the meantime
            WalleeHelper::rollbackDBTransaction();
            return;
        }
        try {
            $executedRefund = $this->refund($refundJob->getSpaceId(), $this->createRefundObject($refundJob));
            $refundJob->setState(WalleeModelRefundjob::STATE_SENT);
            $refundJob->setRefundId($executedRefund->getId());

            if ($executedRefund->getState() == \Wallee\Sdk\Model\RefundState::PENDING) {
                $refundJob->setState(WalleeModelRefundjob::STATE_PENDING);
            }
            $refundJob->save();
            WalleeHelper::commitDBTransaction();
        } catch (\Wallee\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \Wallee\Sdk\Model\ClientError) {
                $refundJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            WalleeHelper::getModuleInstance()->l(
                                'Could not send the refund to %s. Error: %s',
                                'refund'
                            ),
                            'wallee',
                            WalleeHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $refundJob->setState(WalleeModelRefundjob::STATE_FAILURE);
                $refundJob->save();
                WalleeHelper::commitDBTransaction();
            } else {
                $refundJob->save();
                WalleeHelper::commitDBTransaction();
                $message = sprintf(
                    WalleeHelper::getModuleInstance()->l(
                        'Error sending refund job with id %d: %s',
                        'refund'
                    ),
                    $refundJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WalleeModelRefundjob');
                throw $e;
            }
        } catch (Exception $e) {
            $refundJob->save();
            WalleeHelper::commitDBTransaction();
            $message = sprintf(
                WalleeHelper::getModuleInstance()->l('Error sending refund job with id %d: %s', 'refund'),
                $refundJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'WalleeModelRefundjob');
            throw $e;
        }
    }

    public function applyRefundToShop($refundJobId)
    {
        $refundJob = new WalleeModelRefundjob($refundJobId);
        WalleeHelper::startDBTransaction();
        WalleeHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
        // Reload refund job;
        $refundJob = new WalleeModelRefundjob($refundJobId);
        if ($refundJob->getState() != WalleeModelRefundjob::STATE_APPLY) {
            // Already processed in the meantime
            WalleeHelper::rollbackDBTransaction();
            return;
        }
        try {
            $order = new Order($refundJob->getOrderId());
            $strategy = WalleeBackendStrategyprovider::getStrategy();
            $appliedData = $strategy->applyRefund($order, $refundJob->getRefundParameters());
            $refundJob->setState(WalleeModelRefundjob::STATE_SUCCESS);
            $refundJob->save();
            WalleeHelper::commitDBTransaction();
            try {
                $strategy->afterApplyRefundActions($order, $refundJob->getRefundParameters(), $appliedData);
            } catch (Exception $e) {
                // We ignore errors in the after apply actions
            }
        } catch (Exception $e) {
            WalleeHelper::rollbackDBTransaction();
            WalleeHelper::startDBTransaction();
            WalleeHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
            $refundJob = new WalleeModelRefundjob($refundJobId);
            $refundJob->increaseApplyTries();
            if ($refundJob->getApplyTries() > 3) {
                $refundJob->setState(WalleeModelRefundjob::STATE_FAILURE);
                $refundJob->setFailureReason(array(
                    'en-US' => sprintf($e->getMessage())
                ));
            }
            $refundJob->save();
            WalleeHelper::commitDBTransaction();
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = WalleeHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $refundJob = WalleeModelRefundjob::loadRunningRefundForTransaction($spaceId, $transactionId);
        if ($refundJob->getState() == WalleeModelRefundjob::STATE_CREATED) {
            $this->sendRefund($refundJob->getId());
        } elseif ($refundJob->getState() == WalleeModelRefundjob::STATE_APPLY) {
            $this->applyRefundToShop($refundJob->getId());
        }
    }

    public function updateRefunds($endTime = null)
    {
        $toSend = WalleeModelRefundjob::loadNotSentJobIds();
        foreach ($toSend as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->sendRefund($id);
            } catch (Exception $e) {
                $message = sprintf(
                    WalleeHelper::getModuleInstance()->l(
                        'Error updating refund job with id %d: %s',
                        'refund'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WalleeModelRefundjob');
            }
        }
        $toApply = WalleeModelRefundjob::loadNotAppliedJobIds();
        foreach ($toApply as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->applyRefundToShop($id);
            } catch (Exception $e) {
                $message = sprintf(
                    WalleeHelper::getModuleInstance()->l(
                        'Error applying refund job with id %d: %s',
                        'refund'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WalleeModelRefundjob');
            }
        }
    }

    public function hasPendingRefunds()
    {
        $toSend = WalleeModelRefundjob::loadNotSentJobIds();
        $toApply = WalleeModelRefundjob::loadNotAppliedJobIds();
        return ! empty($toSend) || ! empty($toApply);
    }

    /**
     * Creates a refund request model for the given parameters.
     *
     * @param Order $order
     * @param array $refund
     *            Refund data to be determined
     * @return \Wallee\Sdk\Model\RefundCreate
     */
    protected function createRefundObject(WalleeModelRefundjob $refundJob)
    {
        $order = new Order($refundJob->getOrderId());

        $strategy = WalleeBackendStrategyprovider::getStrategy();

        $spaceId = $refundJob->getSpaceId();
        $transactionId = $refundJob->getTransactionId();
        $externalRefundId = $refundJob->getExternalId();
        $parsedData = $refundJob->getRefundParameters();
        $amount = $strategy->getRefundTotal($parsedData);
        $type = $strategy->getWalleeRefundType($parsedData);

        $reductions = $strategy->createReductions($order, $parsedData);
        $reductions = $this->fixReductions($amount, $spaceId, $transactionId, $reductions);

        $remoteRefund = new \Wallee\Sdk\Model\RefundCreate();
        $remoteRefund->setExternalId($externalRefundId);
        $remoteRefund->setReductions($reductions);
        $remoteRefund->setTransaction($transactionId);
        $remoteRefund->setType($type);

        return $remoteRefund;
    }

    /**
     * Returns the fixed line item reductions for the refund.
     *
     * If the amount of the given reductions does not match the refund's grand total, the amount to refund is
     * distributed equally to the line items.
     *
     * @param float $refundTotal
     * @param int $spaceId
     * @param int $transactionId
     * @param \Wallee\Sdk\Model\LineItemReductionCreate[] $reductions
     * @return \Wallee\Sdk\Model\LineItemReductionCreate[]
     */
    protected function fixReductions($refundTotal, $spaceId, $transactionId, array $reductions)
    {
        $baseLineItems = $this->getBaseLineItems($spaceId, $transactionId);
        $reductionAmount = WalleeHelper::getReductionAmount($baseLineItems, $reductions);

        $configuration = WalleeVersionadapter::getConfigurationInterface();
        $computePrecision = $configuration->get('_PS_PRICE_COMPUTE_PRECISION_');

        if (Tools::ps_round($refundTotal, $computePrecision) != Tools::ps_round($reductionAmount, $computePrecision)) {
            $fixedReductions = array();
            $baseAmount = WalleeHelper::getTotalAmountIncludingTax($baseLineItems);
            $rate = $refundTotal / $baseAmount;
            foreach ($baseLineItems as $lineItem) {
                $reduction = new \Wallee\Sdk\Model\LineItemReductionCreate();
                $reduction->setLineItemUniqueId($lineItem->getUniqueId());
                $reduction->setQuantityReduction(0);
                $reduction->setUnitPriceReduction(
                    round($lineItem->getAmountIncludingTax() * $rate / $lineItem->getQuantity(), 8)
                );
                $fixedReductions[] = $reduction;
            }

            return $fixedReductions;
        } else {
            return $reductions;
        }
    }

    /**
     * Sends the refund to the gateway.
     *
     * @param int $spaceId
     * @param \Wallee\Sdk\Model\RefundCreate $refund
     * @return \Wallee\Sdk\Model\Refund
     */
    public function refund($spaceId, \Wallee\Sdk\Model\RefundCreate $refund)
    {
        return $this->getRefundService()->refund($spaceId, $refund);
    }

    /**
     * Returns the line items that are to be used to calculate the refund.
     *
     * This returns the line items of the latest refund if there is one or else of the completed transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \Wallee\Sdk\Model\Refund $refund
     * @return \Wallee\Sdk\Model\LineItem[]
     */
    protected function getBaseLineItems($spaceId, $transactionId, \Wallee\Sdk\Model\Refund $refund = null)
    {
        $lastSuccessfulRefund = $this->getLastSuccessfulRefund($spaceId, $transactionId, $refund);
        if ($lastSuccessfulRefund) {
            return $lastSuccessfulRefund->getReducedLineItems();
        } else {
            return $this->getTransactionInvoice($spaceId, $transactionId)->getLineItems();
        }
    }

    /**
     * Returns the transaction invoice for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @throws Exception
     * @return \Wallee\Sdk\Model\TransactionInvoice
     */
    protected function getTransactionInvoice($spaceId, $transactionId)
    {
        $query = new \Wallee\Sdk\Model\EntityQuery();

        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter(
                    'state',
                    \Wallee\Sdk\Model\TransactionInvoiceState::CANCELED,
                    \Wallee\Sdk\Model\CriteriaOperator::NOT_EQUALS
                ),
                $this->createEntityFilter('completion.lineItemVersion.transaction.id', $transactionId)
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $invoiceService = new \Wallee\Sdk\Service\TransactionInvoiceService(
            WalleeHelper::getApiClient()
        );
        $result = $invoiceService->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            throw new Exception('The transaction invoice could not be found.');
        }
    }

    /**
     * Returns the last successful refund of the given transaction, excluding the given refund.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \Wallee\Sdk\Model\Refund $refund
     * @return \Wallee\Sdk\Model\Refund
     */
    protected function getLastSuccessfulRefund(
        $spaceId,
        $transactionId,
        \Wallee\Sdk\Model\Refund $refund = null
    ) {
        $query = new \Wallee\Sdk\Model\EntityQuery();

        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
        $filters = array(
            $this->createEntityFilter('state', \Wallee\Sdk\Model\RefundState::SUCCESSFUL),
            $this->createEntityFilter('transaction.id', $transactionId)
        );
        if ($refund != null) {
            $filters[] = $this->createEntityFilter(
                'id',
                $refund->getId(),
                \Wallee\Sdk\Model\CriteriaOperator::NOT_EQUALS
            );
        }

        $filter->setChildren($filters);
        $query->setFilter($filter);

        $query->setOrderBys(
            array(
                $this->createEntityOrderBy('createdOn', \Wallee\Sdk\Model\EntityQueryOrderByType::DESC)
            )
        );
        $query->setNumberOfEntities(1);

        $result = $this->getRefundService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns the refund API service.
     *
     * @return \Wallee\Sdk\Service\RefundService
     */
    protected function getRefundService()
    {
        if ($this->refundService == null) {
            $this->refundService = new \Wallee\Sdk\Service\RefundService(
                WalleeHelper::getApiClient()
            );
        }

        return $this->refundService;
    }
}
