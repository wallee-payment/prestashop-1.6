<?php

if (!defined('_PS_VERSION_')) {
    exit;
}


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