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
 * This provider allows to create a Wallee_ShopRefund_IStrategy.
 * The implementation of
 * the strategy depends on the actual prestashop version.
 */
class WalleeBackendStrategyprovider
{

    /**
     * Returns the refund strategy to use
     *
     * @return WalleeBackendIstrategy
     */
    public static function getStrategy()
    {
        return new WalleeBackendDefaultstrategy();
    }
}
