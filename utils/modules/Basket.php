<?php

class Basket {
    public static function getBasket() {
        if ($dbres = db_query("select BASKET from ALL_SESSIONS where ID=".ACCOUNT)) {
            $row = mysql_fetch_assoc($dbres);
            $blow = explode('+|+', $row['BASKET']);
            $basket = array_filter($blow);
            $basket = array_map(function($o) {
              return explode('-|-', $o);
            }, $basket);
            $json = [];
            foreach($basket as $item) {
              $json[$item[1]] = [
                'id' => $item[1],
                'quantity' => $item[2],
                'price' => $item[3]
              ];
            }
            return $json;
        } else {
            return [];
        }
    }

    public static function getSubmittedBasket() {
        if ($dbres = db_query("select UIN, BASKET, DATA from ALL_SESSIONS where DATA LIKE 'payform::%' AND ID=".ACCOUNT)) {
            $sess = mysql_fetch_assoc($dbres);
            list($_, $tempid) = explode('::', $sess['DATA']);
            if ($dbres = db_query("select VALUE from ALL_TEMP where ID=".$tempid." and TYPE='payform'")) {
               if ($row = mysql_fetch_assoc($dbres)) {
                  $basket = explode("+|+", $sess['BASKET']);
                  $basket = array_map(function ($o) {
                     $o = explode("-|-", $o);
                     if ($o[0] === "") return null;
                     return [
                        'type' => $o[0],
                        'id' => $o[1],
                        'quantity' => $o[2],
                        'price' => $o[3]
                     ];
                  }, $basket);
                  $basket = array_filter($basket);
                  $ids = array_map(function ($a) { return $a['id']; }, $basket);
                  if (count($ids) > 0 && $dbres = db_query("select ID, TITLE from ".PREFIX."PAGES where ID IN (".implode(',', $ids).")")) {
                     while ($row = mysql_fetch_assoc($dbres)) {
                        $index = array_search($row['ID'], $ids);
                        $basket[$index]['name'] = $row['TITLE'];
                     }
                  }
                  return $basket;
               }
            }
        }
      
        return null;
    }
}