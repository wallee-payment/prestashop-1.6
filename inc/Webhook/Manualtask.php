<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle manual task state transitions.
 */
class WalleeWebhookManualtask extends WalleeWebhookAbstract
{

    /**
     * Updates the number of open manual tasks.
     *
     * @param WalleeWebhookRequest $request
     */
    public function process(WalleeWebhookRequest $request)
    {
        $manualTaskService = WalleeServiceManualtask::instance();
        $manualTaskService->update();
    }
}
