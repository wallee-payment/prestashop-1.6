<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * This service provides functions to deal with Wallee refunds.
 */
class Wallee_Service_Refund extends Wallee_Service_Abstract
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
        }
        else {
            throw new Exception('The refund could not be found.');
        }
    }

    public function executeRefund(Order $order, array $parsedParameters)
    {
        $currentRefundJob = null;
        try {
            Wallee_Helper::startDBTransaction();
            $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    Wallee_Helper::translatePS('Could not load corresponding wallee transaction'));
            }
            
            Wallee_Helper::lockByTransactionId($transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId());
            // Reload after locking
            $transactionInfo = Wallee_Model_TransactionInfo::loadByTransaction(
                $transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            $spaceId = $transactionInfo->getSpaceId();
            $transactionId = $transactionInfo->getTransactionId();
            
            if (! in_array($transactionInfo->getState(), self::$refundableStates)) {
                throw new Exception(
                    Wallee_Helper::translatePS('The transaction is not in a state to be refunded.'));
            }
            
            if (Wallee_Model_RefundJob::isRefundRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    Wallee_Helper::translatePS(
                        'Please wait until the existing refund is processed.'));
            }
            $strategy = Wallee_Backend_StrategyProvider::getStrategy();
            
            $refundJob = new Wallee_Model_RefundJob();
            $refundJob->setState(Wallee_Model_RefundJob::STATE_CREATED);
            $refundJob->setOrderId($order->id);
            $refundJob->setSpaceId($transactionInfo->getSpaceId());
            $refundJob->setTransactionId($transactionInfo->getTransactionId());
            $refundJob->setExternalId(uniqid($order->id . '-'));
            $refundJob->setRefundParameters($parsedParameters);
            $refundJob->save();
            $currentRefundJob = $refundJob->getId();
            Wallee_Helper::commitDBTransaction();
        }
        catch (Exception $e) {
            Wallee_Helper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendRefund($currentRefundJob);
    }

    protected function sendRefund($refundJobId)
    {
        $refundJob = new Wallee_Model_RefundJob($refundJobId);
        Wallee_Helper::startDBTransaction();
        Wallee_Helper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
        // Reload refund job;
        $refundJob = new Wallee_Model_RefundJob($refundJobId);
        if ($refundJob->getState() != Wallee_Model_RefundJob::STATE_CREATED) {
            // Already sent in the meantime
            Wallee_Helper::rollbackDBTransaction();
            return;
        }
        try {
            $refundService = Wallee_Service_Refund::instance();
            $executedRefund = $refundService->refund($refundJob->getSpaceId(),
                $this->createRefundObject($refundJob));
            $refundJob->setState(Wallee_Model_RefundJob::STATE_SENT);
            $refundJob->setRefundId($executedRefund->getId());
            
            if ($executedRefund->getState() == \Wallee\Sdk\Model\RefundState::PENDING) {
                $refundJob->setState(Wallee_Model_RefundJob::STATE_PENDING);
            }
            $refundJob->save();
            Wallee_Helper::commitDBTransaction();
        }
        catch (Exception $e) {
            $refundJob->setFailureReason(
                array(
                    'en-US' => sprintf(
                        Wallee_Helper::translatePS("Could not send the refund to wallee. Error: %s"),
                        Wallee_Helper::cleanWalleeExceptionMessage($e->getMessage()))
                ));
            $refundJob->setState(Wallee_Model_RefundJob::STATE_FAILURE);
            $refundJob->save();
            Wallee_Helper::commitDBTransaction();
            throw $e;
        }
    }

    public function applyRefundToShop($refundJobId)
    {
        $refundJob = new Wallee_Model_RefundJob($refundJobId);
        Wallee_Helper::startDBTransaction();
        Wallee_Helper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
        // Reload refund job;
        $refundJob = new Wallee_Model_RefundJob($refundJobId);
        if ($refundJob->getState() != Wallee_Model_RefundJob::STATE_APPLY) {
            // Already processed in the meantime
            Wallee_Helper::rollbackDBTransaction();
            return;
        }
        try {
            $order = new Order($refundJob->getOrderId());
            $strategy = Wallee_Backend_StrategyProvider::getStrategy();
            $appliedData = $strategy->applyRefund($order, $refundJob->getRefundParameters());
            $refundJob->setState(Wallee_Model_RefundJob::STATE_SUCCESS);
            $refundJob->save();
            Wallee_Helper::commitDBTransaction();
            try {
                $strategy->afterApplyRefundActions($order, $refundJob->getRefundParameters(), $appliedData);
            }
            catch (Exception $e) {
                // We ignore errors in the after apply actions
            }
        }
        catch (Exception $e) {
            Wallee_Helper::rollbackDBTransaction();
            Wallee_Helper::startDBTransaction();
            Wallee_Helper::lockByTransactionId($refundJob->getSpaceId(),
                $refundJob->getTransactionId());
            $refundJob = new Wallee_Model_RefundJob($refundJobId);
            $refundJob->increaseApplyTries();
            if ($refundJob->getApplyTries() > 3) {
                $refundJob->setState(Wallee_Model_RefundJob::STATE_FAILURE);
                $refundJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            Wallee_Helper::translatePS("Could not apply refund in shop. Error: %s"),
                            $e->getMessage())
                    ));
            }
            $refundJob->save();
            Wallee_Helper::commitDBTransaction();
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $refundJob = Wallee_Model_RefundJob::loadRunningRefundForTransaction($spaceId,
            $transactionId);
        if ($refundJob->getState() == Wallee_Model_RefundJob::STATE_CREATED) {
            $this->sendRefund($refundJob->getId());
        }
        elseif ($refundJob->getState() == Wallee_Model_RefundJob::STATE_APPLY) {
            $this->applyRefundToShop($refundJob->getId());
        }
    }

    public function updateRefunds($endTime)
    {
        $toSend = Wallee_Model_RefundJob::loadNotSentJobIds();
        foreach ($toSend as $id) {
            if (time() + 15 > $endTime) {
                return;
            }
            try {
                $this->sendRefund($id);
            }
            catch (Exception $e) {
                $message = sprintf(
                    Wallee_Helper::translatePS('Error updating refund job with id %d: %s'), $id,
                    $e->getMessage());
                PrestaShopLogger::addLog($message, 3, null, 'Wallee_Model_RefundJob');
            }
        }
        $toApply = Wallee_Model_RefundJob::loadNotAppliedJobIds();
        foreach ($toApply as $id) {
            if (time() + 15 > $endTime) {
                return;
            }
            try {
                $this->applyRefundToShop($id);
            }
            catch (Exception $e) {
                $message = sprintf(
                    Wallee_Helper::translatePS('Error applying refund job with id %d: %s'), $id,
                    $e->getMessage());
                PrestaShopLogger::addLog($message, 3, null, 'Wallee_Model_RefundJob');
            }
        }
    }

    public function hasPendingRefunds()
    {
        $toSend = Wallee_Model_RefundJob::loadNotSentJobIds();
        $toApply = Wallee_Model_RefundJob::loadNotAppliedJobIds();
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
    protected function createRefundObject(Wallee_Model_RefundJob $refundJob)
    {
        $order = new Order($refundJob->getOrderId());
        
        $strategy = Wallee_Backend_StrategyProvider::getStrategy();
        
        $spaceId = $refundJob->getSpaceId();
        $transactionId = $refundJob->getTransactionId();
        $externalRefundId = $refundJob->getExternalId();
        $parsedData = $refundJob->getRefundParameters();
        $amount = $strategy->getRefundTotal($parsedData);
        $type = $strategy->getWalleeRefundType($parsedData);
        
        $reductions = $strategy->createReductions($order, $parsedData);
        $reductions = $this->fixReductions($amount, $spaceId, $transactionId, $reductions);
                
        $walleeRefund = new \Wallee\Sdk\Model\RefundCreate();
        $walleeRefund->setExternalId($externalRefundId);
        $walleeRefund->setReductions($reductions);
        $walleeRefund->setTransaction($transactionId);
        $walleeRefund->setType($type);
        
        return $walleeRefund;
    }

    /**
     * Returns the fixed line item reductions for the refund.
     *
     * If the amount of the given reductions does not match the refund's grand total, the amount to refund is distributed equally to the line items.
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
        $reductionAmount = Wallee_Helper::getReductionAmount($baseLineItems, $reductions);
        
        $configuration = Adapter_ServiceLocator::get('Core_Business_ConfigurationInterface');
        $computePrecision = $configuration->get('_PS_PRICE_COMPUTE_PRECISION_');
        
        if (Tools::ps_round($refundTotal, $computePrecision) !=
             Tools::ps_round($refundTotal, $computePrecision)) {
            $fixedReductions = array();
            $baseAmount = Wallee_Helper::getTotalAmountIncludingTax($baseLineItems);
            $rate = $refundTotal / $baseAmount;
            foreach ($baseLineItems as $lineItem) {
                $reduction = new \Wallee\Sdk\Model\LineItemReductionCreate();
                $reduction->setLineItemUniqueId($lineItem->getUniqueId());
                $reduction->setQuantityReduction(0);
                $reduction->setUnitPriceReduction(
                    round($lineItem->getAmountIncludingTax() * $rate / $lineItem->getQuantity(), 8));
                $fixedReductions[] = $reduction;
            }
            
            return $fixedReductions;
        }
        else {
            return $reductions;
        }
    }

    /**
     * Returns the line item reductions for the creditmemo's items.
     *
     * @param Order $order
     * @param array $refund
     *            Refund data to be determined
     * @return \Wallee\Sdk\Model\LineItemReductionCreate[]
     */
    protected function getReductions(Order $order, array $refundData)
    {
        $amount = 0;
        $reductions = array();
        if (isset($refundData['partialRefundProduct'])) {
            $quantities = $refundData['partialRefundProductQuantity'];
            
            foreach ($refundData['partialRefundProduct'] as $idOrderDetail => $amountDetail) {
                if (! $quantities[$idOrderDetail]) {
                    continue;
                }
                $quantity = (int) $quantities[$idOrderDetail];
                $orderDetail = new OrderDetail((int) $idOrderDetail);
                $uniqueId = 'order-' . $order->id . '-item-' . $orderDetail->product_id . '-' .
                     $orderDetail->product_attribute_id;
                
                $reduction = new \Wallee\Sdk\Model\LineItemReductionCreate();
                $reduction->setLineItemUniqueId($uniqueId);
                
                if (empty($amountDetail)) {
                    $reduction->setQuantityReduction((int) $quantity);
                    $reduction->setUnitPriceReduction(0);
                }
                else {
                    // Merchant did most likely not refund complete amount
                    $amount = (float) str_replace(',', '.', $amountDetail);
                    $unitPrice = $amount / $quantity;
                    $originalUnitPrice = (! $refundData['TaxMethod'] ? $orderDetail->unit_price_tax_excl : $orderDetail->unit_price_tax_incl);
                    if (Tools::ps_round($originalUnitPrice, $computePrecision) !=
                         Tools::ps_round($unitPrice, $computePrecision)) {
                        $reduction->setQuantityReduction(0);
                        $reduction->setUnitPriceReduction(
                            round($amount / $orderDetail->product_quantity, 8));
                    }
                    else {
                        $reduction->setQuantityReduction((int) $quantity);
                        $reduction->setUnitPriceReduction(0);
                    }
                }
                $reductions[] = $reduction;
            }
            $shippingCostAmount = (float) str_replace(',', '.',
                $refundData['partialRefundShippingCost']);
            
            if ($shippingCostAmount > 0) {
                $uniqueId = 'order-' . $order->id . '-shipping';
                if (! $refundData['TaxMethod']) {
                    $tax = new Tax();
                    $tax->rate = $order->carrier_tax_rate;
                    $taxCalculator = new TaxCalculator(array(
                        $tax
                    ));
                    $totalShippingCost = $taxCalculator->addTaxes($shippingCostAmount);
                }
                else {
                    $totalShippingCost = $shippingCostAmount;
                }
                $reduction = new \Wallee\Sdk\Model\LineItemReductionCreate();
                $reduction->setLineItemUniqueId($uniqueId);
                $reduction->setQuantityReduction(0);
                $reduction->setUnitPriceReduction(round($totalShippingCost, 8));
                $reductions[] = $reduction;
            }
        }
        return $reductions;
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
    protected function getBaseLineItems($spaceId, $transactionId,
        \Wallee\Sdk\Model\Refund $refund = null)
    {
        $lastSuccessfulRefund = $this->getLastSuccessfulRefund($spaceId, $transactionId, $refund);
        if ($lastSuccessfulRefund) {
            return $lastSuccessfulRefund->getReducedLineItems();
        }
        else {
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
                $this->createEntityFilter('state',
                    \Wallee\Sdk\Model\TransactionInvoiceState::CANCELED,
                    \Wallee\Sdk\Model\CriteriaOperator::NOT_EQUALS),
                $this->createEntityFilter('completion.lineItemVersion.transaction.id',
                    $transactionId)
            ));
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $invoiceService = new \Wallee\Sdk\Service\TransactionInvoiceService(
            Wallee_Helper::getApiClient());
        $result = $invoiceService->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        }
        else {
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
    protected function getLastSuccessfulRefund($spaceId, $transactionId,
        \Wallee\Sdk\Model\Refund $refund = null)
    {
        $query = new \Wallee\Sdk\Model\EntityQuery();
        
        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
        $filters = array(
            $this->createEntityFilter('state', \Wallee\Sdk\Model\RefundState::SUCCESSFUL),
            $this->createEntityFilter('transaction.id', $transactionId)
        );
        if ($refund != null) {
            $filters[] = $this->createEntityFilter('id', $refund->getId(),
                \Wallee\Sdk\Model\CriteriaOperator::NOT_EQUALS);
        }
        
        $filter->setChildren($filters);
        $query->setFilter($filter);
        
        $query->setOrderBys(
            array(
                $this->createEntityOrderBy('createdOn',
                    \Wallee\Sdk\Model\EntityQueryOrderByType::DESC)
            ));
        $query->setNumberOfEntities(1);
        
        $result = $this->getRefundService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        }
        else {
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
                Wallee_Helper::getApiClient());
        }
        
        return $this->refundService;
    }
}