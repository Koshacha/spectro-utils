<?php

require_once(__DIR__ . "/strings/Catalog.php");

namespace Utils;

class Catalog {
  private $db = NULL;
  private $t = [];
  private $logging = false;
  private $log_eol = false;

  private $categories = [];


  function __construct($db1host, $db1base, $db1user, $db1pass) {
    $this->db = new Db($db1host, 3306, $db1base, $db1user, $db1pass);
  }

  public function set_logger($flag, $new_line_symbol = "\r\n") {
    $this->logging = $flag;
    $this->log_eol = $new_line_symbol;
  }

  public function log($message, $fields = []) {
    if ($this->logging === false) return;
    $time = date("Y-m-d H:i");
    if (!is_array($fields)) $fields = array($fields);
    $message = vsprintf($message, $fields);
    echo "[{$time}]: $message{$this->log_eol}";
  }

  public function getParentCategoryByUid($uid) {
    if (array_key_exists($uid, $this->categories)) return $this->categories[$uid];
    $id = $this->db->single("SELECT LINKID FROM {$this->t->blocks} WHERE PARAM LIKE '?'", [$uid]);
    $this->categories[$uid] = $id;
    return $id;
  }

  public function getProductUidById($id) {
    $uid = $this->db->single("SELECT PARAM FROM {$this->t->blocks} WHERE LINKID = ?", [$id]);
    return $uid;
  }

  public function getProductNds($id) {
    $nds = $this->db->single("SELECT N01 FROM {$this->t->catalog} WHERE ID = ?", [$id]);
    return $nds;
  }

  public function getProductNameById($id) {
    $name = $this->db->single("SELECT TITLE FROM {$this->t->pages} WHERE ID = ?", [$id]);
    return $name;
  }

  public function buildOrder($str, $for_receipt = false, $balance = 0) {
    $cartItems = explode('+|+', $str);
    $cartItems = array_map(function ($o) {
      return explode('-|-', $o);
    }, $cartItems);
  
    $items = [];
    $total = 0;
    $spentBalance = 0;
  
    foreach ($cartItems as $item) {
      list($type, $id, $count, $price) = $item;
      if ($type !== "good") continue;
      $uid = $this->getProductUidById($id);
  
      if ($balance > 1) {
        $discount = min($balance, intval($count) * intval($price) - 1);
        $price = max(1, intval($price) - $discount / intval($count));
        $spentBalance += $discount;
        $balance -= $discount;
      }
  
      $total += intval($count) * intval($price);
  
      $temp = [
        "GOODID" => $uid,
        "AMOUNT" => $count,
        "PRICE" => $price,
        "NDS" => $this->getProductNds($id),
      ];
  
      if ($for_receipt) {
        $temp['ID'] = $id;
      }
  
      $items[] = $temp;
    }
  
    return [
      "ITEMS" => $items,
      "TOTAL" => $total,
      "SPENT_BALANCE" => $spentBalance
    ];
  }

  public function getMaxDiscount($order, $money_amount = 0) {
    $min_price = array_reduce($order['ITEMS'], function ($a, $b) {
      return $a + $b['AMOUNT'];
    }, 0);

    if ($money_amount > $order['TOTAL']) {
      return $order['TOTAL'] - $min_price;
    }

    return min($order['TOTAL'] - $min_price, $money_amount);
  }

  public function buildReceipt($str, $delivery_price = 0, $balance = 0) {
    $items = $this->buildOrder($str, true, $balance)["ITEMS"];

    $items = array_map(function ($o) {
      return array(
        "description" => $this->getProductNameById($o['ID']),
        "quantity" => floatval($o['AMOUNT']),
        "amount" => array(
          "value" => number_format(floatval($o['PRICE']), 2, '.', ''),
          "currency" => "RUB"
        ),
        "vat_code" => get_vat_code($o['NDS']),
        "payment_mode" => "full_prepayment",
        "payment_subject" => "commodity"
      );
    }, $items);

    if ($delivery_price) {
      $items[] = array(
        "description" => "Доставка",
        "quantity" => 1,
        "amount" => array(
          "value" => number_format($delivery_price, 2, '.', ''),
          "currency" => "RUB"
        ),
        "vat_code" => "1",
        "payment_mode" => "full_prepayment",
        "payment_subject" => "service"
      );
    }

    return $items;
  }

  public function createProduct($obj) {
    $id = $this->new_id($this->t->pages);
    if (empty($obj["UID_GROUP"])) {
      $this->log(MSG_EMPTY_CAT, $obj['UID']);
      return false;
    }

    $parent_id = strlen($obj['UID_GROUP']) ? $this->getParentCategoryByUid($obj['UID_GROUP']) : CATALOG_SECTION_ID;

    if ($parent_id == CATALOG_SECTION_ID) {
      $this->log(MSG_EMPTY_CAT, $obj['UID']);
      return false;
    }

    list($values, $params) = $this->build_fields([
      "ID" => $id,
      "LINKID" => $parent_id,
      "CHILDS" => 0,
      "URL" => $this->build_url($obj["NAME"], $parent_id),
      "TITLE" => $obj["NAME"],
      "H1" => $obj["NAME"],
      "ENABLE" => 1,
      "PENABLE" => 1
    ]);

    $this->db->query("INSERT INTO {$this->t->pages} {$values}", $params);

    $img = is_array($obj['PICTURE']) && !empty($obj['PICTURE']) ? $this->upload_images($obj['PICTURE']) : "";

    $arg = [
      "ID" => $id,
      "CATID" => CATALOG_GLOBAL_ID,
      "PRICE" => $obj['PRICE'],
      "N01" => $obj['NDS'],
      "A02" => round(1000 * ($obj['WEIGHT'] ? $obj['WEIGHT'] : 0)),
    ];

    if ($img) {
      $arg["IMG"] = $img;
    }

    list($values, $params) = $this->build_fields($arg);
    $this->db->query("INSERT INTO {$this->t->catalog} {$values}", $params);

    $good_id = $this->new_id($this->t->blocks);
    list($values, $params) = $this->build_fields([
      "ID" => $good_id,
      "LINKID" => $id,
      "POSITION" => 0,
      "PARAM" => $obj['UID'], 
      "TYPE" => "good",
      "VALUE" => $obj['DISCRIPTION'],
      "PAGE" => 1,
      "ENABLE" => 1,
      "SEQUENCE" => 0,
    ]);
    $this->db->query("INSERT INTO {$this->t->blocks} {$values}", $params);

    $this->log(MSG_PRODUCT_CREATED, $id);

    return $id;
  }

  public function updateProduct($uid, $obj) {
    $row = $this->db->row("SELECT ID, LINKID FROM {$this->t->blocks} WHERE PARAM LIKE ?", [ "%$uid%" ]);
    list($block_id, $id) = [$row['ID'], $row['LINKID']];

    $img = is_array($obj['PICTURE']) && !empty($obj['PICTURE']) ? $this->upload_images($obj['PICTURE']) : "";

    $arg = [
      "ID" => $id,
      "PRICE" => $obj['PRICE'],
      "N01" => $obj['NDS'],
      "A02" => round(1000 * ($obj['WEIGHT'] ? $obj['WEIGHT'] : 0)),
    ];

    if ($img) {
      $arg["IMG"] = $img;
    }

    list($updates, $params) = $this->build_fields_for_update($arg, ["id"]);

    $this->db->query("UPDATE {$this->t->catalog} SET {$updates} WHERE ID = :id", $params);

    if (!empty(trim($obj['DISCRIPTION']))) {
      list($updates, $params) = $this->build_fields_for_update([
        "ID" => $block_id,
        "VALUE" => $obj['DISCRIPTION'],
      ], ["id"]);
  
      $this->db->query("UPDATE {$this->t->blocks} SET {$updates} WHERE ID = :id", $params);
    }

    $this->log(MSG_PRODUCT_UPDATED, $id);

    if (empty($obj["UID_GROUP"])) {
      $this->log(MSG_EMPTY_CAT, $obj['UID']);
      return $id;
    }

    $parent_id = strlen($obj['UID_GROUP']) ? $this->getParentCategoryByUid($obj['UID_GROUP']) : CATALOG_SECTION_ID;

    if ($parent_id === CATALOG_SECTION_ID) {
      $this->log(MSG_WARN_CATEGORY_NOT_FOUND, [ $obj['UID'], $obj['UID_GROUP'] ]);
    }

    list($updates, $params) = $this->build_fields_for_update([
      "ID" => $id,
      "LINKID" => $parent_id,
      "URL" => $this->build_url($obj["NAME"], $parent_id),
      "TITLE" => $obj["NAME"],
      "H1" => $obj["NAME"]
    ], ['id']);
    $this->db->query("UPDATE {$this->t->pages} SET {$updates} WHERE ID = :id", $params);

    return $id;
  }

  public function updateProductStock($uid, $in_stock = 0) {
    $row = $this->db->row("SELECT ID, LINKID FROM {$this->t->blocks} WHERE PARAM LIKE ?", [ "%$uid%" ]);
    list($block_id, $id) = [$row['ID'], $row['LINKID']];

    list($updates, $params) = $this->build_fields_for_update([
      "ID" => $id,
      "A04" => $in_stock,
    ], ["id"]);

    $this->db->query("UPDATE {$this->t->catalog} SET {$updates} WHERE ID = :id", $params);

    return $id;
  }

  public function createCategory($group, $hasGoods = false) {
    $id = $this->new_id($this->t->pages);
    $parent_id = strlen($group['UID_PARENT']) > 0 ? $this->getParentCategoryByUid($group['UID_PARENT']) : CATALOG_SECTION_ID;

    list($values, $params) = $this->build_fields([
      "ID" => $id,
      "LINKID" => $parent_id,
      "CHILDS" => 1,
      "URL" => $this->build_url($group["NAME"], $parent_id),
      "TITLE" => $group["NAME"],
      "H1" => $group["NAME"],
      "ENABLE" => 1,
      "PENABLE" => 1
    ]);
    $this->db->query("INSERT INTO {$this->t->pages} {$values}", $params);
    $this->categories[$group['UID']] = $id;

    $block_id = $this->new_id($this->t->blocks);

    if ($hasGoods) {
      list($values, $params) = $this->build_fields([
        "ID" => $block_id,
        "LINKID" => $id,
        "POSITION" => 0,
        "PARAM" => $group['UID'], 
        "TYPE" => "goods",
        "VALUE" => "-|-{$id}-|--|--|-sequence-|-section-|-40-|-4-|-yes-|-1",
        "PAGE" => 1,
        "ENABLE" => 1,
        "SEQUENCE" => 20
      ]);
    } else {
      list($values, $params) = $this->build_fields([
        "ID" => $block_id,
        "LINKID" => $id,
        "POSITION" => 0,
        "PARAM" => $group['UID'], 
        "TYPE" => "subsec",
        "VALUE" => "{$id}-|-100",
        "PAGE" => 1,
        "ENABLE" => 1,
        "SEQUENCE" => 10,
      ]);
    }

    $this->db->query("INSERT INTO {$this->t->blocks} {$values}", $params);

    $this->log(MSG_CATEGORY_CREATED, $id);

    return $id;
  }

  public function updateCategory($uid, $group, $hasGoods = false) {
    $id = $this->categories[$uid];

    $parent_id = strlen($group['UID_PARENT']) > 0 ? $this->getParentCategoryByUid($group['UID_PARENT']) : CATALOG_SECTION_ID;

    list($updates, $params) = $this->build_fields_for_update([
      "ID" => $id,
      "LINKID" => $parent_id,
      "URL" => $this->build_url($group["NAME"], $parent_id),
      "TITLE" => $group["NAME"],
      "H1" => $group["NAME"]
    ], ["id"]);
    $this->db->query("UPDATE {$this->t->pages} SET {$updates} WHERE ID = :id", $params);

    if ($hasGoods && $id) {
      list($updates, $params) = $this->build_fields_for_update([
        "LINKID" => $id,
        "TYPE" => "goods",
        "VALUE" => "-|-{$id}-|--|--|-sequence-|-section-|-40-|-4-|-yes-|-1"
      ], [ "linkid" ]);
    } else {
      list($updates, $params) = $this->build_fields_for_update([
        "LINKID" => $id,
        "TYPE" => "subsec",
        "VALUE" => "{$id}-|-100"
      ], [ "linkid" ]);
    }

    $this->db->query("UPDATE {$this->t->blocks} SET {$updates} WHERE LINKID = :linkid", $params);
    $this->log(MSG_CATEGORY_UPDATED, $id);

    return $id;
  }

  public function removeProduct($id) {
    $this->db->query("DELETE FROM {$this->t->blocks} WHERE LINKID = ?", [$id]);
    $this->db->query("DELETE FROM {$this->t->catalog} WHERE ID = ?", [$id]);
    $this->db->query("DELETE FROM {$this->t->pages} WHERE ID = ?", [$id]);

    $this->log(MSG_PRODUCT_REMOVED, $id);
  }

  public function removeCatalog($id) {
    $this->db->query("DELETE FROM {$this->t->blocks} WHERE LINKID = ?", [$id]);
    $this->db->query("DELETE FROM {$this->t->pages} WHERE ID = ?", [$id]);

    $this->log(MSG_CATEGORY_REMOVED, $id);
  }
  
  public function isProductExists($uid) {
    $id = $this->db->single("SELECT LINKID FROM {$this->t->blocks} WHERE PARAM LIKE ?", [ "%$uid%" ]);
    return $id !== false;
  }
  
  public function isCategoryExists($uid) {
    if (array_key_exists($uid, $this->categories)) return true;
    $id = $this->db->single("SELECT LINKID FROM {$this->t->blocks} WHERE PARAM LIKE ?", [ "%$uid%" ]);
    if ($id === false) return false;
    $this->categories[$uid] = $id;
    return true;
  }
  
  public function addToMenu($group_id) {
    $id = $this->new_id($this->t->rows);
    $sequence = $this->db->single("SELECT MAX(SEQUENCE) FROM {$this->t->rows} WHERE TYPE = ?", ["menu" . CATALOG_MENU_ID]);
    if (!$sequence) $sequence = 0;

    list($values, $params) = $this->build_fields([
      "ID" => $id,
      "TYPE" => "menu" . CATALOG_MENU_ID,
      "S01" => 1,
      "N01" => $group_id,
      "N02" => 1,
      "SEQUENCE" => intval($sequence) + 10,
    ]);

    $this->db->query("INSERT INTO {$this->t->rows} {$values}", $params);
  }

  public function isInMenu($group_id) {
    $id = $this->db->single("SELECT ID FROM {$this->t->rows} WHERE N01 = ? AND TYPE = ?", [$group_id, "menu" . CATALOG_MENU_ID]);
    return $id;
  }

  public function setChildrenInCatalog() {
    $this->db->query("UPDATE {$this->t->pages} SET CHILDS = 1 WHERE ID = ?", [CATALOG_SECTION_ID]);
  }
  
  public function build_table_names($prefix) {
    $tables = [
      "arows" => "ALL_ROWS",
      "aseo" => "ALL_SEO",
      "asessions" => "ALL_SESSIONS",
      "atemp" => "ALL_TEMP",
      "blocks" => "{$prefix}BLOCKS",
      "catalog" => "{$prefix}CATALOG",
      "pages" => "{$prefix}PAGES",
      "rows" => "{$prefix}ROWS"
    ];

    $this->t = (object) $tables;
  }

  public function add_image_to_base($id, $options = []) {
    list($values, $params) = $this->build_fields([
      "ID" => $id,
      "TYPE" => "img",
      "S01" => implode('-', [$options['ext'], $options['size'][0], $options['size'][1]]) . '-|-',
      "S02" => implode('-', [$options['ext'], $options['small_size'][0], $options['small_size'][1]]) . '-|-',
      "NAME" => $options['original_name'] . "-|-",
      "SEQUENCE" => time(),
    ]);

    $this->db->query("INSERT INTO {$this->t->rows} {$values}", $params);
  }

  public function db_has_image($filename) {
    $id = $this->db->single("SELECT ID FROM {$this->t->rows} WHERE NAME LIKE ?", ["{$filename}-|-"]);
    return $id;
  }

  public function upload_images($images) {
    $img = [];
    foreach ($images as $picture) {
      try {
        $db_id = $this->db_has_image($picture['guid_1C_file']);
        if ($db_id !== false) {
          $img[] = "{$db_id}.jpg";
          continue;
        }

        $id = $this->new_id($this->t->rows);
        $file_destination = SITE_IMG_DIR . "/{$id}.jpg";
        $image = base64_to_jpeg($picture['binary_file_data'], $file_destination);
        $small_image = @compress($file_destination, addPostfix($file_destination, "_1"), 75);

        $this->add_image_to_base($id, [
          'ext' => '.jpg',
          'size' => [$image['sizes'][0], $image['sizes'][1]],
          'small_size' => [$small_image['sizes'][0], $small_image['sizes'][1]],
          'original_name' => $picture['guid_1C_file']
        ]);

        $img[] = basename($file_destination);
      } catch(Exception $e) {}
    }

    $img = implode('-|-', $img);
    return $img;
  }

  private function build_fields($coll) {
    $keys = []; 
    $values = [];

    foreach ($coll as $key => $value) {
      $loweredKey = strtolower($key);
      $keys[] = $key;
      $values[] = ":" . $loweredKey;
      $coll[$loweredKey] = $value;
      unset($coll[$key]);
    }

    $fields = implode(",", $keys);
    $values = implode(",", $values);
    
    return ["({$fields}) VALUES ({$values})", $coll];
  }

  private function build_fields_for_update($coll, $filtered_fields = []) {
    $sets = []; 

    foreach ($coll as $key => $value) {
      $loweredKey = strtolower($key);
      if (!in_array($key, $filtered_fields) && !in_array($loweredKey, $filtered_fields)) {
        $sets[] = "{$key} = :{$loweredKey}";
      }
      $coll[$loweredKey] = $value;
      unset($coll[$key]);
    }

    $sets = implode(", ", $sets);
    
    return [$sets, $coll];
  }

  private function new_id($table, $count = 1) {
    $res = GetUnicalIds($table, $count);
    if ($count === 1) return $res[0];
    return $res;
  }

  private function build_url($name, $parent_id) {
    $slug = mb_strtolower($name);
    $slug = trim($slug);
    $slug = replaceAllNonWordSymbols($slug);
    $slug = ru2lat($slug);

    $parent_url = $this->db->single("SELECT URL FROM {$this->t->pages} WHERE ID = ?", [$parent_id]);
    return "{$parent_url}/{$slug}";
  }

  public function close() {
    $this->db->closeConnection();
  }
}