<?php

define('APP_DIR', __DIR__ . '/app');
define('LIB_DIR', __DIR__ . '/lib');

spl_autoload_register(function($className) {
        $paths = [APP_DIR, LIB_DIR];
        foreach ($paths as $path) {
            $classPath = $path . '/' . $className . '.php';
            if (!file_exists($classPath)) {
                throw new Exception("Class '{$className}' not found to auto load");
            }
            require_once $classPath;
        }
        return false;
    });
