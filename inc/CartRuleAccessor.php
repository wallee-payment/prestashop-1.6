<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

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