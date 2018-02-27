<?php
if (! defined('_PS_VERSION_')) {
    exit();
}



class WalleeOrderModuleFrontController extends Wallee_FrontPaymentController
{
	public $ssl = true;

    public function postProcess() {
		$methodId = Tools::getValue('methodId', null);
		$cartHash = Tools::getValue('cartHash', null);
		if ($methodId == null || $cartHash == null) {
		    $this->context->cookie->wle_error = $this->module->l("There was a techincal issue, please try again.");
		    echo json_encode(array('result' => 'failure', 'redirect' => $this->context->link->getPageLink('order', true, NULL, "step=3"), 'type' => 'hash'));
		    die();
		}
		$cart = $this->context->cart;	
		$redirect = $this->checkAvailablility($cart);
		if(!empty($redirect)){
		    echo json_encode(array('result' => 'failure', 'redirect' => $redirect,  'type' => 'availability'));
		    die();
		}
	
		$spaceId = Configuration::get(Wallee::CK_SPACE_ID, null, null, $cart->id_shop);
		$methodConfiguration = new Wallee_Model_MethodConfiguration($methodId);
		if (! $methodConfiguration->isActive() || $methodConfiguration->getSpaceId() != $spaceId) {
		    $this->context->cookie->wle_error = $this->module->l("This payment method is no longer available, please try another one.");
		    echo json_encode(array('result' => 'failure', 'redirect' => $this->context->link->getPageLink('order', true, NULL, "step=3")));
		    die();
		}
		
		$cmsId = Configuration::get('PS_CONDITIONS_CMS_ID', null, null, $cart->id_shop);
		$conditions = Configuration::get('PS_CONDITIONS', null, null, $cart->id_shop);
		$showTos = Configuration::get(Wallee::CK_SHOW_TOS, null, null, $cart->id_shop);
		
		if($cmsId && $conditions && $showTos){
		    $agreed = Tools::getValue('cgv');
		    
		    if(!$agreed){
		        $this->context->cookie->checkedTOS = null;
		        $this->context->cookie->wle_error = $this->module->l("Please accept the terms of service.");
		        echo json_encode(array('result' => 'failure', 'reload' => 'true'));
		        die();
		    }
		    $this->context->cookie->checkedTOS = 1;
		}
				
		$this->addFeeProductToCart($methodConfiguration, $cart);
		if($cartHash != Wallee_Helper::calculateCartHash($cart)){
		    $this->context->cookie->wle_error = $this->module->l("The cart was changed, please try again.");
		    echo json_encode(array('result' => 'failure', 'reload' => 'true'));
		    die();
		}		
		
		$orderState = Wallee_OrderStatus::getRedirectOrderStatus();
		try{
		    $this->module->validateOrder($cart->id, $orderState->id, $cart->getOrderTotal(true, Cart::BOTH, null, null, false),
		    'wallee_'.$methodId);
		      echo json_encode(array('result' => 'success'));
		      die();
		}
		catch(Exception $e){
		    $this->context->cookie->wle_error = Wallee_Helper::cleanExceptionMessage($e->getMessage());
		    echo json_encode(array('result' => 'failure', 'redirect' => $this->context->link->getPageLink('order', true, NULL, "step=3")));
		    die();
		}

	}
	
	
	public function setMedia(){
	    //We do not need styling here
	}
	
	protected function displayMaintenancePage() {
	    // We want never to see here the maintenance page.
	}
	
	protected function displayRestrictedCountryPage() {
	    // We do not want to restrict the content by any country.
	}
	
	protected function canonicalRedirection($canonical_url = '') {
	    // We do not need any canonical redirect
	}

}
