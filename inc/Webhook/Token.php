<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * Webhook processor to handle token state transitions.
 */
class Wallee_Webhook_Token extends Wallee_Webhook_Abstract {

	public function process(Wallee_Webhook_Request $request){
		$tokenService = Wallee_Service_Token::instance();
		$tokenService->updateToken($request->getSpaceId(), $request->getEntityId());
	}
}