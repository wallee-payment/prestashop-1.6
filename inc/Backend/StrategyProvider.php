<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * This provider allows to create a Wallee_ShopRefund_IStrategy. The implementation of 
 * the strategy depends on the actual prestashop version.
 */
class Wallee_Backend_StrategyProvider {

    
    /**
     * Returns the refund strategy to use
     * 
     * @return Wallee_Backend_IStrategy
     */
    public static function getStrategy(){
        
        return new Wallee_Backend_DefaultStrategy();
       
    }
    
    
    
}
    
    