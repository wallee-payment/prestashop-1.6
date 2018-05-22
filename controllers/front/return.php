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

class WalleeReturnModuleFrontController extends ModuleFrontController
{

    public $ssl = true;

    /**
     *
     * @see FrontController::initContent()
     */
    public function postProcess()
    {
        $orderId = Tools::getValue('order_id', null);
        $orderKey = Tools::getValue('secret', null);
        $action = Tools::getValue('action', null);
        
        if ($orderId != null) {
            $order = new Order($orderId);
            if ($orderKey == null || $orderKey != Wallee_Helper::computeOrderSecret($order)) {
                $error = Tools::displayError('Invalid Secret.');
                die($error);
            }
            switch ($action) {
                case 'success':
                    $this->processSuccess($order);
                    
                    return;
                case 'failure':
                    self::process_failure($order);
                    
                    return;
                default:
            }
        }
        $error = Tools::displayError('Invalid Request.');
        die($error);
    }

    private function processSuccess(Order $order)
    {
        $transactionService = Wallee_Service_Transaction::instance();
        $transactionService->waitForTransactionState($order,
            array(
                \Wallee\Sdk\Model\TransactionState::CONFIRMED,
                \Wallee\Sdk\Model\TransactionState::PENDING,
                \Wallee\Sdk\Model\TransactionState::PROCESSING
            ), 5);
        $cartId = $order->id_cart;
        $customer = new Customer($order->id_customer);
        
        $this->redirect_after = $this->context->link->getPageLink('order-confirmation', true, null,
            array(
                'id_cart' => $cartId,
                'id_module' => $this->module->id,
                'id_order' => $order->id,
                'key' => $customer->secure_key
            ));
    }

    private function process_failure(Order $order)
    {
        $transactionService = Wallee_Service_Transaction::instance();
        $transactionService->waitForTransactionState($order,
            array(
                \Wallee\Sdk\Model\TransactionState::FAILED
            ), 5);
        $transaction = Wallee_Model_TransactionInfo::loadByOrderId($order->id);        
        $failureReason = $transaction->getFailureReason();
        
        if ($failureReason !== null) {       
            $this->context->cookie->wle_error = Wallee_Helper::translate($failureReason);
        }
        $this->redirect_after = $this->context->link->getPageLink('order', true, NULL, "step=3");

    }

    public function setMedia()
    {
        // We do not need styling here
    }
    
    protected function displayMaintenancePage()
    {
        // We never display the maintenance page.
    }
    
    protected function displayRestrictedCountryPage()
    {
        // We do not want to restrict the content by any country.
    }
    
    protected function canonicalRedirection($canonical_url = '')
    {
        // We do not need any canonical redirect
    }
}
