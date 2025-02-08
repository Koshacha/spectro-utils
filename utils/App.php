<?php
namespace Utils;

class App {
    private static $config = [];
    private static $labels = [];

    public static function getSiteConfig() {
        if (isset(self::$config)) {
            return self::$config;
        }

        $options = [];
        $labels = [];

        if ($dbres = db_query("select S01, S02, NAME from ".PREFIX."ROWS where TYPE='site'"))
        while ($row = mysql_fetch_array($dbres)) {
            $options[$row['S01']] = $row['S02'];
            $labels[$row['S01']] = $row['NAME'];
        }

        self::$config = $options;
        self::$labels = $labels;

        return $options;
    }

    public static function getSiteEmail() {
        if (isset(self::$config['sitemail'])) {
            return self::$config['sitemail'];
        }

        if ($dbres = db_query("select * from ".PREFIX."ROWS where TYPE='site' and S01='sitemail'"))
        if ($row = mysql_fetch_array($dbres)) {
            return $row['S02'];
        }
    
        return "contact@siteconst.ru";
    }
}