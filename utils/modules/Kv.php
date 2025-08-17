<?php

class KV {

    private static $data = [];
    private static $file;

    private static function load() {
        if (self::$data) {
            return;
        }

        self::$file = dirname(__FILE__) . '/kv_data.json';

        if (file_exists(self::$file)) {
            $json = file_get_contents(self::$file);
            self::$data = json_decode($json, true);
            if (!is_array(self::$data)) {
                self::$data = [];
            }
        } else {
            self::$data = [];
        }
    }

    private static function save() {
        $json = json_encode(self::$data);
        file_put_contents(self::$file, $json);
    }

    public static function set($key, $val, $options = []) {
        self::load();

        $expire = isset($options['expire']) ? (int)$options['expire'] : null;

        if ($expire !== null) {
            $expire_time = time() + $expire;
            self::$data[$key] = ['value' => $val, 'expire' => $expire_time];
        } else {
            self::$data[$key] = ['value' => $val];
        }

        self::save();
    }

    public static function get($key, $fallback = null) {
        self::load();

        if (self::has($key)) {
            if (isset(self::$data[$key]['expire'])) {
                if (self::$data[$key]['expire'] > time()) {
                    return self::$data[$key]['value'];
                } else {
                    self::delete($key);
                    return $fallback;
                }
            } else {
                return self::$data[$key]['value'];
            }
        }

        return $fallback;
    }

    public static function has($key) {
        self::load();

        if (!isset(self::$data[$key])) {
            return false;
        }

        if (isset(self::$data[$key]['expire'])) {
            if (self::$data[$key]['expire'] > time()) {
                return true;
            } else {
                self::delete($key);
                return false;
            }
        }

        return true;
    }

    public static function delete($key) {
        self::load();

        if (isset(self::$data[$key])) {
            unset(self::$data[$key]);
            self::save();
        }
    }

    public static function deleteExpired() {
        self::load();

        foreach (self::$data as $key => $value) {
            if (isset($value['expire'])) {
                if ($value['expire'] <= time()) {
                    unset(self::$data[$key]);
                }
            }
        }
        self::save();
    }
}