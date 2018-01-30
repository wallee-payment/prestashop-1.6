<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * Webhook processor to handle token version state transitions.
 */
class Wallee_Webhook_TokenVersion extends Wallee_Webhook_Abstract {

	public function process(Wallee_Webhook_Request $request){
		$tokenService = Wallee_Service_Token::instance();
		$tokenService->updateTokenVersion($request->getSpaceId(), $request->getEntityId());
	}
}