<?php
if (! defined('_PS_VERSION_')) {
    exit();
}


spl_autoload_register(function ($class) {
    
    $prefix = 'Wallee_';
    
    // base directory for the namespace prefix
    $baseDir = __DIR__ . '/inc/';
    
    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }
    
    $cleanName = substr($class, $len);
    
    $replaced = str_replace("_", DIRECTORY_SEPARATOR, $cleanName);
   
    $file = $baseDir .$replaced . '.php';
    
    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});


