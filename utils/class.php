<?php

class Modules {
    public static function enable($module) {
        if (is_array($module)) {
            foreach ($module as $m) {
                self::enable($m);
            }
            return;
        }

        $module = ucfirst(strtolower($module));
        $file = __DIR__ . "/{$module}.php";
        if (file_exists($file)) {
            require_once $file;
        } else {
            throw new \Exception("Module {$module} not found");
        }
    }
}