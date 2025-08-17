<?php

require_once KYUTILS_PATH . '/libs/db/PDO.class.php';

class DB {
    private static $initialized = false;
    private static $pdo = null;

    public static function autorun() {
        if (!self::$initialized) {
            self::$initialized = true;
            self::$pdo = new PDO_DB($GLOBALS['db1host'], 3306, $GLOBALS['db1base'], $GLOBALS['db1user'], $GLOBALS['db1pass']);
        }
    }

    public static function single($sql, $params = []) {
        self::autorun();
        return self::$pdo->single($sql, $params);
    }

    public static function row($sql, $params = []) {
        self::autorun();
        return self::$pdo->row($sql, $params);
    }

    public static function query($sql, $params = []) {
        self::autorun();
        return self::$pdo->query($sql, $params);
    }

    public static function one($sql, $params = []) {
        self::autorun();
        return self::$pdo->row($sql, $params);
    }

    public static function all($sql, $params = []) {
        self::autorun();
        return self::$pdo->all($sql, $params);
    }

    public static function insert($table, $data) {
        self::autorun();
        return self::$pdo->insert($table, $data);
    }

    public static function update($table, $data, $where, $params = []) {
        self::autorun();
        return self::$pdo->update($table, $data, $where, $params);
    }

    public static function delete($table, $where, $params = []) {
        self::autorun();
        return self::$pdo->delete($table, $where, $params);
    }

    public static function begin() {
        self::autorun();
        return self::$pdo->beginTransaction();
    }

    public static function commit() {
        self::autorun();
        return self::$pdo->commit();
    }

    public static function rollback() {
        self::autorun();
        return self::$pdo->rollBack();
    }
}
