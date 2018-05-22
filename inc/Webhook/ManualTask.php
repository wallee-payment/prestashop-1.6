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
 * Webhook processor to handle manual task state transitions.
 */
class Wallee_Webhook_ManualTask extends Wallee_Webhook_Abstract {

	/**
	 * Updates the number of open manual tasks.
	 *
	 * @param Wallee_Webhook_Request $request
	 */
	public function process(Wallee_Webhook_Request $request){
		$manualTaskService = Wallee_Service_ManualTask::instance();
		$manualTaskService->update();
	}
}