<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2024 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle token version state transitions.
 */
class WalleeWebhookTokenversion extends WalleeWebhookAbstract
{
    public function process(WalleeWebhookRequest $request)
    {
        $tokenService = WalleeServiceToken::instance();
        $tokenService->updateTokenVersion($request->getSpaceId(), $request->getEntityId());
    }
}
