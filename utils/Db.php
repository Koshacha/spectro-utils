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

    public static function one($sql, $params = []) {
        self::init();
        return self::$pdo->row($sql, $params);
    }

    public static function all($sql, $params = []) {
        self::init();
        return self::$pdo->all($sql, $params);
    }

    public static function insert($table, $data) {
        self::init();
        return self::$pdo->insert($table, $data);
    }

    public static function update($table, $data, $where, $params = []) {
        self::init();
        return self::$pdo->update($table, $data, $where, $params);
    }

    public static function delete($table, $where, $params = []) {
        self::init();
        return self::$pdo->delete($table, $where, $params);
    }

    public static function begin() {
        self::init();
        return self::$pdo->beginTransaction();
    }

    public static function commit() {
        self::init();
        return self::$pdo->commit();
    }

    public static function rollback() {
        self::init();
        return self::$pdo->rollBack();
    }
}
