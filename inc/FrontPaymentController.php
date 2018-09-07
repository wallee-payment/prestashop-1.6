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

class Wallee_FrontPaymentController extends ModuleFrontController {
    

    /**
     * Checks if the module is still active and various checkout specfic values.
     * Returns a redirect URL where the customer has to be redirected, if there is an issue.
     * 
     * @param Cart $cart
     * @return string|NULL
     */
    protected function checkAvailablility(Cart $cart){
        if ($cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active || !Validate::isLoadedObject(new Customer($cart->id_customer))){
            $this->context->cookie->wle_error = $this->module->l('Your session expired, please try again.','frontpaymentcontroller');
            return $this->context->link->getPageLink('order', true, NULL, "step=1");
        }
        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module){
            
            if ($module['name'] == 'wallee'){
                $authorized = true;
                break;
            }
        }
        if (!$authorized){
            $this->context->cookie->wle_error = $this->module->l('This payment method is no longer available, please try another one.','frontpaymentcontroller');
            return $this->context->link->getPageLink('order', true, NULL, "step=3");
        }
        
        if(!$this->module instanceof Wallee){
            $this->context->cookie->wle_error = $this->module->l('There was a technical issue, please try again.', 'frontpaymentcontroller');
            return $this->context->link->getPageLink('order', true, NULL, "step=3");
        }
        return null;
    }
}
