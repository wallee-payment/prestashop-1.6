<?php
if (! defined('_PS_VERSION_')) {
    exit();
}
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