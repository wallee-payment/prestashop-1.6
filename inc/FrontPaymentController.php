<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

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
            $this->context->cookie->wallee_error = $this->module->l("Your session expired, please try again.");
            return $this->context->link->getPageLink('order', true, NULL, "step=1");
        }
        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module){
            
            if ($module['name'] == 'wallee_payment'){
                $authorized = true;
                break;
            }
        }
        if (!$authorized){
            $this->context->cookie->wallee_error = $this->module->l("This payment method is no longer available, please try another one.");
            return $this->context->link->getPageLink('order', true, NULL, "step=3");
        }
        
        if(!$this->module instanceof Wallee_Payment){
            $this->context->cookie->wallee_error = $this->module->l("There was a techincal issue, please try again.");
            return $this->context->link->getPageLink('order', true, NULL, "step=3");
        }
        return null;
    }
    
    
    protected function addFeeProductToCart(Wallee_Model_MethodConfiguration $methodConfiguration, Cart $cart){
        
        $feeProductId = Configuration::get(Wallee_Payment::CK_FEE_ITEM);
        
        if ($feeProductId != null) {
            $defaultAttributeId = Product::getDefaultAttribute($feeProductId);
            
            SpecificPrice::deleteByIdCart($cart->id, $feeProductId, $defaultAttributeId);
            $cart->deleteProduct($feeProductId, $defaultAttributeId);    
            
            $feeValues = Wallee_Helper::getWalleeFeeValues($cart, $methodConfiguration);           
            
            if ($feeValues['wallee_fee_total'] > 0) {
                $cart->updateQty(1, $feeProductId, $defaultAttributeId);
                $specificPrice = new SpecificPrice();
                $specificPrice->id_product = (int) $feeProductId;
                $specificPrice->id_product_attribute = (int) $defaultAttributeId;
                $specificPrice->id_cart = (int) $cart->id;
                $specificPrice->id_shop = (int) $this->context->shop->id;
                $specificPrice->id_currency = $cart->id_currency;
                $specificPrice->id_country = 0;
                $specificPrice->id_group = 0;
                $specificPrice->id_customer = 0;
                $specificPrice->from_quantity = 1;
                $specificPrice->price = $feeValues['wallee_fee_total'];
                $specificPrice->reduction_type = 'amount';
                $specificPrice->reduction_tax = 1;
                $specificPrice->reduction = 0;
                $specificPrice->from = date("Y-m-d H:i:s", time() - 3600);
                $specificPrice->to = date("Y-m-d H:i:s", time() + 48 * 3600);
                $specificPrice->add();
            }
        }
        
    }
}
