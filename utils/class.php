<?php
namespace Utils;

class Utils {
    public static function include($module) {
        $module = ucfirst(strtolower($module));
        $file = __DIR__ . "/{$module}.php";
        if (file_exists($file)) {
            require_once $file;
        } else {
            throw new \Exception("Module {$module} not found");
        }
    }
}