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

/**
 * Webhook processor to handle token state transitions.
 */
class Wallee_Webhook_Token extends Wallee_Webhook_Abstract {

	public function process(Wallee_Webhook_Request $request){
		$tokenService = Wallee_Service_Token::instance();
		$tokenService->updateToken($request->getSpaceId(), $request->getEntityId());
	}
}