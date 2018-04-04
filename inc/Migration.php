<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Wallee_Migration extends Wallee_AbstractMigration{
    
    protected static function getMigrations(){
        return array(
            '1.0.0' => 'initialize_1_0_0',
            '1.0.1' => 'orderstatus_1_0_1'
        );
    }
    
    public static function initialize_1_0_0()
    {
        static::installBase();
        $result = Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "wle_cron_job(
                `id_cron_job` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `constraint_key` int(10),
                `state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                `security_token` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                `date_scheduled` datetime,
                `date_started` datetime,
                `date_finished` datetime,
                `error_msg` longtext COLLATE utf8_unicode_ci,
                PRIMARY KEY (`id_cron_job`),
                UNIQUE KEY `unq_constraint_key` (`constraint_key`),
                INDEX `idx_state` (`state`),
                INDEX `idx_security_token` (`security_token`),
                INDEX `idx_date_scheduled` (`date_scheduled`),
                INDEX `idx_date_started` (`date_started`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        
        if ($result === false) {
            throw new Exception(DB::getMsgError());
        }
    }
    
    public static function orderstatus_1_0_1()
    {
        static::installOrderStatusConfig();
        static::installOrderPaymentSaveHook();
    }
}
