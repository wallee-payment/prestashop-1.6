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
 * This class provides function to download documents from wallee
 */
class Wallee_CartRuleAccessor extends CartRule{


    public static  function checkProductRestrictionsStatic(CartRule $cartRule, Cart $cart)
    {
        $context = Context::getContext()->cloneContext();
        $context->cart = $cart;
        return $cartRule->checkProductRestrictions($context, true);
    }
}