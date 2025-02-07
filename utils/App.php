<?php
namespace Utils;

class App {
    public static function getSiteConfig() {
        $options = [];
        $labels = [];

        if ($dbres = db_query("select S01, S02, NAME from ".PREFIX."ROWS where TYPE='site'"))
        while ($row = mysql_fetch_array($dbres)) {
            $options[$row['S01']] = $row['S02'];
            $labels[$row['S01']] = $row['NAME'];
        }

        return [$options, $labels];
    }
}