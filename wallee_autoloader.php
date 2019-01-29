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

spl_autoload_register(function ($class) {
    
    $prefix = 'Wallee_';
    
    // base directory for the namespace prefix
    $baseDir = __DIR__ . '/inc/';
    
    // does the class use the namespace prefix?
    $len = Tools::strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }
    
    $cleanName = Tools::substr($class, $len);
    
    $replaced = str_replace("_", DIRECTORY_SEPARATOR, $cleanName);
   
    $file = $baseDir .$replaced . '.php';
    
    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
