<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle payment method configuration state transitions.
 */
class Wallee_Webhook_MethodConfiguration extends Wallee_Webhook_Abstract
{

    /**
     * Synchronizes the payment method configurations on state transition.
     *
     * @param Wallee_Webhook_Request $request
     */
    public function process(Wallee_Webhook_Request $request)
    {
        $paymentMethodConfigurationService = Wallee_Service_MethodConfiguration::instance();
        $paymentMethodConfigurationService->synchronize();
    }
}
