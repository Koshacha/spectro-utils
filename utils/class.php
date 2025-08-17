<?php

class Modules {
    private static $loaded_classes = [];

    public static function enable($modules = []) {
        $autorun_classes = [];

        if (empty($modules)) {
            $modules = self::discoverModules();
        }

        if (is_string($modules)) {
            $modules = [$modules];
        }

        foreach ($modules as $module) {
            $moduleName = ucfirst(strtolower($module));
            $file = __DIR__ . "/modules/{$moduleName}.php";

            if (!file_exists($file)) {
                $file = __DIR__ . "/{$moduleName}.php";
            }

            if (file_exists($file)) {
                require_once $file;
                if (class_exists($moduleName) && method_exists($moduleName, 'autorun')) {
                    $autorun_classes[] = $moduleName;
                }
                self::$loaded_classes[] = $moduleName;
            } else {
                throw new \Exception("Module {$moduleName} not found");
            }
        }

        foreach ($autorun_classes as $class) {
            $class::autorun();
        }

        define('SPECTRO_MODULES', count(self::$loaded_classes));
    }

    private static function discoverModules() {
        $modules = [];
        $moduleFiles = glob(__DIR__ . '/modules/*.php');
        foreach ($moduleFiles as $file) {
            $modules[] = basename($file, '.php');
        }
        return $modules;
    }
}
