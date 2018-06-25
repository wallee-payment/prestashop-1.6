<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class Wallee_VersionAdapter
{   
    
    public static function getConfigurationInterface(){
        return Adapter_ServiceLocator::get('Core_Business_ConfigurationInterface');
    }
    
    public static function getAddressFactory(){
        return Adapter_ServiceLocator::get('Adapter_AddressFactory');
    }
 
    public static function clearCartRuleStaticCache(){
        
    }
       
}