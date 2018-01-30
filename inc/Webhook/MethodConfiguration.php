<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * Webhook processor to handle payment method configuration state transitions.
 */
class Wallee_Webhook_MethodConfiguration extends Wallee_Webhook_Abstract {

	/**
	 * Synchronizes the payment method configurations on state transition.
	 *
	 * @param Wallee_Webhook_Request $request
	 */
	public function process(Wallee_Webhook_Request $request){
	    $paymentMethodConfigurationService = Wallee_Service_MethodConfiguration::instance();
		$paymentMethodConfigurationService->synchronize();
	}
}