<?php

class Request {
    private static $fetched = false;
    private static $get = null;
    private static $post = null;
    private static $request = null;
    private static $files = null;

    private static function fetch() {
        self::$get = $_GET;
        self::$post = $_POST;
        self::$files = self::normalizeFiles($_FILES);

        $postPhpInput = file_get_contents('php://input');
        self::$request = array_merge(self::$get, self::$post, json_decode($postPhpInput, true));
    }

    public static function get($key, $fallback = null) {
        if (!self::$fetched) {
            self::fetch();
        }

        return isset(self::$request[$key]) ? self::$request[$key] : $fallback;
    }

    public static function files() {
        if (!self::$fetched) {
            self::fetch();
        }

        return self::$files;
    }

    private static function normalizeFiles($files) {
        $out = [];
        foreach ($files as $key => $file) {
            if (isset($file['name']) && is_array($file['name'])) {
                $new = [];
                foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $k) {
                    array_walk_recursive($file[$k], function (&$data, $key, $k) {
                        $data = [$k => $data];
                    }, $k);
                    $new = array_replace_recursive($new, $file[$k]);
                }
                $out[$key] = $new;
            } else {
                $out[$key] = $file;
            }
        }
        return $out;
    }
}