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
 * This service provides functions to deal with wallee transaction completions.
 */
class WalleeServiceTransactioncompletion extends WalleeServiceAbstract
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
            WalleeHelper::startDBTransaction();
            $transactionInfo = WalleeHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    WalleeHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction.',
                        'transactioncompletion'
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

            if ($transactionInfo->getState() != \Wallee\Sdk\Model\TransactionState::AUTHORIZED) {
                throw new Exception(
                    WalleeHelper::getModuleInstance()->l(
                        'The transaction is not in a state to be completed.',
                        'transactioncompletion'
                    )
                );
            }

            if (WalleeModelCompletionjob::isCompletionRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    WalleeHelper::getModuleInstance()->l(
                        'Please wait until the existing completion is processed.',
                        'transactioncompletion'
                    )
                );
            }

            if (WalleeModelVoidjob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    WalleeHelper::getModuleInstance()->l(
                        'There is a void in process. The order can not be completed.',
                        'transactioncompletion'
                    )
                );
            }

            $completionJob = new WalleeModelCompletionjob();
            $completionJob->setSpaceId($spaceId);
            $completionJob->setTransactionId($transactionId);
            $completionJob->setState(WalleeModelCompletionjob::STATE_CREATED);
            $completionJob->setOrderId(
                WalleeHelper::getOrderMeta($order, 'walleeMainOrderId')
            );
            $completionJob->save();
            $currentCompletionJob = $completionJob->getId();
            WalleeHelper::commitDBTransaction();
        } catch (Exception $e) {
            WalleeHelper::rollbackDBTransaction();
            throw $e;
        }

        try {
            $this->updateLineItems($currentCompletionJob);
            $this->sendCompletion($currentCompletionJob);
        } catch (Exception $e) {
            throw $e;
        }
    }

    protected function updateLineItems($completionJobId)
    {
        $completionJob = new WalleeModelCompletionjob($completionJobId);
        WalleeHelper::startDBTransaction();
        WalleeHelper::lockByTransactionId(
            $completionJob->getSpaceId(),
            $completionJob->getTransactionId()
        );
        // Reload completion job;
        $completionJob = new WalleeModelCompletionjob($completionJobId);

        if ($completionJob->getState() != WalleeModelCompletionjob::STATE_CREATED) {
            // Already updated in the meantime
            WalleeHelper::rollbackDBTransaction();
            return;
        }
        try {
            $baseOrder = new Order($completionJob->getOrderId());
            $collected = $baseOrder->getBrother()->getResults();
            $collected[] = $baseOrder;

            $lineItems = WalleeServiceLineitem::instance()->getItemsFromOrders($collected);
            WalleeServiceTransaction::instance()->updateLineItems(
                $completionJob->getSpaceId(),
                $completionJob->getTransactionId(),
                $lineItems
            );
            $completionJob->setState(WalleeModelCompletionjob::STATE_ITEMS_UPDATED);
            $completionJob->save();
            WalleeHelper::commitDBTransaction();
        } catch (\Wallee\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \Wallee\Sdk\Model\ClientError) {
                $completionJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            WalleeHelper::getModuleInstance()->l(
                                'Could not update the line items. Error: %s',
                                'transactioncompletion'
                            ),
                            WalleeHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $completionJob->setState(WalleeModelCompletionjob::STATE_FAILURE);
                $completionJob->save();
                WalleeHelper::commitDBTransaction();
            } else {
                $completionJob->save();
                WalleeHelper::commitDBTransaction();
                $message = sprintf(
                    WalleeHelper::getModuleInstance()->l(
                        'Error updating line items for completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $completionJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WalleeModelCompletionjob');
                throw $e;
            }
        } catch (Exception $e) {
            $completionJob->save();
            WalleeHelper::commitDBTransaction();
            $message = sprintf(
                WalleeHelper::getModuleInstance()->l(
                    'Error updating line items for completion job with id %d: %s',
                    'transactioncompletion'
                ),
                $completionJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'WalleeModelCompletionjob');
            throw $e;
        }
    }

    protected function sendCompletion($completionJobId)
    {
        $completionJob = new WalleeModelCompletionjob($completionJobId);
        WalleeHelper::startDBTransaction();
        WalleeHelper::lockByTransactionId(
            $completionJob->getSpaceId(),
            $completionJob->getTransactionId()
        );
        // Reload completion job;
        $completionJob = new WalleeModelCompletionjob($completionJobId);

        if ($completionJob->getState() != WalleeModelCompletionjob::STATE_ITEMS_UPDATED) {
            // Already sent in the meantime
            WalleeHelper::rollbackDBTransaction();
            return;
        }
        try {
            $completion = $this->getCompletionService()->completeOnline(
                $completionJob->getSpaceId(),
                $completionJob->getTransactionId()
            );
            $completionJob->setCompletionId($completion->getId());
            $completionJob->setState(WalleeModelCompletionjob::STATE_SENT);
            $completionJob->save();
            WalleeHelper::commitDBTransaction();
        } catch (\Wallee\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \Wallee\Sdk\Model\ClientError) {
                $completionJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            WalleeHelper::getModuleInstance()->l(
                                'Could not send the completion to %s. Error: %s',
                                'transactioncompletion'
                            ),
                            'wallee',
                            WalleeHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $completionJob->setState(WalleeModelCompletionjob::STATE_FAILURE);
                $completionJob->save();
                WalleeHelper::commitDBTransaction();
            } else {
                $completionJob->save();
                WalleeHelper::commitDBTransaction();
                $message = sprintf(
                    WalleeHelper::getModuleInstance()->l(
                        'Error sending completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $completionJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WalleeModelCompletionjob');
                throw $e;
            }
        } catch (Exception $e) {
            $completionJob->save();
            WalleeHelper::commitDBTransaction();
            $message = sprintf(
                WalleeHelper::getModuleInstance()->l(
                    'Error sending completion job with id %d: %s',
                    'transactioncompletion'
                ),
                $completionJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'WalleeModelCompletionjob');
            throw $e;
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = WalleeHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $completionJob = WalleeModelCompletionjob::loadRunningCompletionForTransaction(
            $spaceId,
            $transactionId
        );
        $this->updateLineItems($completionJob->getId());
        $this->sendCompletion($completionJob->getId());
    }

    public function updateCompletions($endTime = null)
    {
        $toProcess = WalleeModelCompletionjob::loadNotSentJobIds();
        foreach ($toProcess as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->updateLineItems($id);
                $this->sendCompletion($id);
            } catch (Exception $e) {
                $message = sprintf(
                    WalleeHelper::getModuleInstance()->l(
                        'Error updating completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WalleeModelCompletionjob');
            }
        }
    }

    public function hasPendingCompletions()
    {
        $toProcess = WalleeModelCompletionjob::loadNotSentJobIds();
        return ! empty($toProcess);
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
                WalleeHelper::getApiClient()
            );
        }
        return $this->completionService;
    }
}
