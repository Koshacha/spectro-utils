<?php

require_once __DIR__ . '/libs/db/PDO.class.php';

class Db {
    private static $initialized = false;
    private static $pdo = null;

    public static function init() {
        if (!self::$initialized) {
            self::$initialized = true;
            self::$pdo = new Db($GLOBALS['db1host'], 3306, $GLOBALS['db1base'], $GLOBALS['db1user'], $GLOBALS['db1pass']);
        }
    }

    public static function single($sql, $params = []) {
        self::init();
        return self::$pdo->single($sql, $params);
    }

    public static function row($sql, $params = []) {
        self::init();
        return self::$pdo->row($sql, $params);
    }

    public static function query($sql, $params = []) {
        self::init();
        return self::$pdo->query($sql, $params);
    }
}