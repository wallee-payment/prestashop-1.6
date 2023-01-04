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
 * This service provides functions to deal with wallee transaction voids.
 */
class WalleeServiceTransactionvoid extends WalleeServiceAbstract
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
            WalleeHelper::startDBTransaction();
            $transactionInfo = WalleeHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    WalleeHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction.',
                        'transactionvoid'
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
                        'The transaction is not in a state to be voided.',
                        'transactionvoid'
                    )
                );
            }
            if (WalleeModelVoidjob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    WalleeHelper::getModuleInstance()->l(
                        'Please wait until the existing void is processed.',
                        'transactionvoid'
                    )
                );
            }
            if (WalleeModelCompletionjob::isCompletionRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    WalleeHelper::getModuleInstance()->l(
                        'There is a completion in process. The order can not be voided.',
                        'transactionvoid'
                    )
                );
            }

            $voidJob = new WalleeModelVoidjob();
            $voidJob->setSpaceId($spaceId);
            $voidJob->setTransactionId($transactionId);
            $voidJob->setState(WalleeModelVoidjob::STATE_CREATED);
            $voidJob->setOrderId(
                WalleeHelper::getOrderMeta($order, 'walleeMainOrderId')
            );
            $voidJob->save();
            $currentVoidId = $voidJob->getId();
            WalleeHelper::commitDBTransaction();
        } catch (Exception $e) {
            WalleeHelper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendVoid($currentVoidId);
    }

    protected function sendVoid($voidJobId)
    {
        $voidJob = new WalleeModelVoidjob($voidJobId);
        WalleeHelper::startDBTransaction();
        WalleeHelper::lockByTransactionId($voidJob->getSpaceId(), $voidJob->getTransactionId());
        // Reload void job;
        $voidJob = new WalleeModelVoidjob($voidJobId);
        if ($voidJob->getState() != WalleeModelVoidjob::STATE_CREATED) {
            // Already sent in the meantime
            WalleeHelper::rollbackDBTransaction();
            return;
        }
        try {
            $void = $this->getVoidService()->voidOnline($voidJob->getSpaceId(), $voidJob->getTransactionId());
            $voidJob->setVoidId($void->getId());
            $voidJob->setState(WalleeModelVoidjob::STATE_SENT);
            $voidJob->save();
            WalleeHelper::commitDBTransaction();
        } catch (\Wallee\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \Wallee\Sdk\Model\ClientError) {
                $voidJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            WalleeHelper::getModuleInstance()->l(
                                'Could not send the void to %s. Error: %s',
                                'transactionvoid'
                            ),
                            'wallee',
                            WalleeHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $voidJob->setState(WalleeModelVoidjob::STATE_FAILURE);
                $voidJob->save();
                WalleeHelper::commitDBTransaction();
            } else {
                $voidJob->save();
                WalleeHelper::commitDBTransaction();
                $message = sprintf(
                    WalleeHelper::getModuleInstance()->l(
                        'Error sending void job with id %d: %s',
                        'transactionvoid'
                    ),
                    $voidJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WalleeModelVoidjob');
                throw $e;
            }
        } catch (Exception $e) {
            $voidJob->save();
            WalleeHelper::commitDBTransaction();
            $message = sprintf(
                WalleeHelper::getModuleInstance()->l(
                    'Error sending void job with id %d: %s',
                    'transactionvoid'
                ),
                $voidJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'WalleeModelVoidjob');
            throw $e;
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = WalleeHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $voidJob = WalleeModelVoidjob::loadRunningVoidForTransaction($spaceId, $transactionId);
        if ($voidJob->getState() == WalleeModelVoidjob::STATE_CREATED) {
            $this->sendVoid($voidJob->getId());
        }
    }

    public function updateVoids($endTime = null)
    {
        $toProcess = WalleeModelVoidjob::loadNotSentJobIds();

        foreach ($toProcess as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->sendVoid($id);
            } catch (Exception $e) {
                $message = sprintf(
                    WalleeHelper::getModuleInstance()->l(
                        'Error updating void job with id %d: %s',
                        'transactionvoid'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WalleeModelVoidjob');
            }
        }
    }

    public function hasPendingVoids()
    {
        $toProcess = WalleeModelVoidjob::loadNotSentJobIds();
        return ! empty($toProcess);
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
                WalleeHelper::getApiClient()
            );
        }

        return $this->voidService;
    }
}
