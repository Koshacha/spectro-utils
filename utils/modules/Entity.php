<?php

require_once KYUTILS_PATH . '/modules/Db.php';
require_once KYUTILS_PATH . '/exceptions/SpectroError.php';

class BaseEntity
{
    protected static $entityName;
    private const GROUP_SEPARATOR = ':-:';
    private static $tableColumnCache = [];

    private static function getTableColumns($tableName)
    {
        if (isset(self::$tableColumnCache[$tableName])) {
            return self::$tableColumnCache[$tableName];
        }

        $columns = Db::all("DESCRIBE `{$tableName}`");
        
        self::$tableColumnCache[$tableName] = $columns;
        return $columns;
    }

    protected static function getDefinition()
    {
        return Entity::getDefinition(static::$entityName);
    }

    private static function findFieldMapping($fieldName) {
        $definition = static::getDefinition();
        $storage_mode = $definition['storage_mode'] ?? 'grouped_string';

        if ($storage_mode === 'json') {
            if (in_array($fieldName, $definition['fields']['VALUE'] ?? [])) {
                return ['type' => 'json', 'column' => 'VALUE', 'field' => $fieldName];
            }
        } else { // grouped_string
            foreach ($definition['fields'] as $dbColumn => $prop) {
                if (is_array($prop)) {
                    $index = array_search($fieldName, $prop);
                    if ($index !== false) {
                        return ['type' => 'grouped', 'column' => $dbColumn, 'index' => $index];
                    }
                } else {
                    if ($prop === $fieldName) {
                        return ['type' => 'direct', 'column' => $dbColumn];
                    }
                }
            }
        }
        return ['type' => 'direct', 'column' => $fieldName];
    }

    protected static function buildWhereClause($conditions)
    {
        if (empty($conditions)) {
            return ['1', []];
        }

        $params = [];
        $paramIndex = 0;
        
        $operator = 'AND';
        if (!empty($conditions)) {
            $first = $conditions[0];
            if (is_string($first) && in_array(strtolower($first), ['and', 'or'])) {
                $operator = strtoupper(array_shift($conditions));
            }
        }

        $sqlParts = [];
        foreach ($conditions as $condition) {
            if (count($condition) !== 3) {
                throw new InvalidArgumentException('Invalid condition format.');
            }
            list($field, $op, $value) = $condition;
            $paramName = ":param_{$paramIndex}";
            $params[$paramName] = $value;
            $paramIndex++;

            $mapping = static::findFieldMapping($field);
            $fieldSql = '';

            switch ($mapping['type']) {
                case 'json':
                    $col = $mapping['column'];
                    $fld = $mapping['field'];
                    $fieldSql = "JSON_UNQUOTE(JSON_EXTRACT(`{$col}`, '$.{$fld}'))";
                    break;
                case 'grouped':
                    $col = $mapping['column'];
                    $idx = $mapping['index'] + 1;
                    $sep = self::GROUP_SEPARATOR;
                    $fieldSql = "SUBSTRING_INDEX(SUBSTRING_INDEX(`{$col}`, '{$sep}', {$idx}), '{$sep}', -1)";
                    break;
                case 'direct':
                default:
                    $col = preg_replace('/[^a-zA-Z0-9_]/', '', $mapping['column']);
                    $fieldSql = "`{$col}`";
                    break;
            }
            
            $sqlParts[] = "{$fieldSql} {$op} {$paramName}";
        }

        if (empty($sqlParts)) {
            return ['1', []];
        }

        $where = implode(" {$operator} ", $sqlParts);
        return [$where, $params];
    }

    protected static function hydrate($data)
    {
        if (!$data) {
            return null;
        }
        $definition = static::getDefinition();
        $storage_mode = $definition['storage_mode'] ?? 'grouped_string';
        $entity = [];

        if (isset($data['id'])) $entity['id'] = (int)$data['id'];
        if (isset($data['SEQUENCE'])) $entity['createdAt'] = $data['SEQUENCE'];

        if ($storage_mode === 'json') {
            if (isset($data['VALUE'])) {
                $decoded = json_decode($data['VALUE'], true);
                if (is_array($decoded)) {
                    $entity = array_merge($entity, $decoded);
                }
            }
        } else { // grouped_string
            foreach ($definition['fields'] as $dbColumn => $prop) {
                if (!isset($data[$dbColumn])) continue;

                if (is_array($prop)) {
                    $values = explode(self::GROUP_SEPARATOR, $data[$dbColumn]);
                    foreach ($prop as $index => $propName) {
                        $entity[$propName] = $values[$index] ?? null;
                    }
                } else {
                    $entity[$prop] = $data[$dbColumn];
                }
            }
        }
        
        $entityObject = (object)$entity;
        foreach ($definition['computed'] as $propName => $closure) {
            if ($closure instanceof Closure) {
                $entity[$propName] = $closure->bindTo($entityObject)();
            }
        }

        return $entity;
    }

    protected static function dehydrate($data)
    {
        $definition = static::getDefinition();
        $storage_mode = $definition['storage_mode'] ?? 'grouped_string';
        $dbData = [];

        if ($storage_mode === 'json') {
            $jsonData = [];
            $field_list = $definition['fields']['VALUE'] ?? [];
            foreach ($field_list as $field) {
                if (isset($data[$field])) {
                    $jsonData[$field] = $data[$field];
                }
            }
            $dbData['VALUE'] = json_encode($jsonData, JSON_UNESCAPED_UNICODE);
        } else { // grouped_string
            foreach ($definition['fields'] as $dbColumn => $prop) {
                if (is_array($prop)) {
                    $values = [];
                    foreach ($prop as $p) {
                        $values[] = $data[$p] ?? '';
                    }
                    $dbData[$dbColumn] = implode(self::GROUP_SEPARATOR, $values);
                } else {
                    if (isset($data[$prop])) {
                        $dbData[$dbColumn] = $data[$prop];
                    }
                }
            }
        }

        return $dbData;
    }

    public static function create($data)
    {
        $definition = static::getDefinition();
        $table = $definition['table'];
        $dbData = static::dehydrate($data);
        
        $dbData['ID'] = GetUnicalIds($table, 1)[0];
        $dbData['TYPE'] = static::$entityName;
        $dbData['SEQUENCE'] = time();

        $tableColumns = static::getTableColumns($table);

        foreach ($tableColumns as $columnInfo) {
            $columnName = $columnInfo['Field'];

            if (isset($dbData[$columnName]) || $columnInfo['Key'] === 'PRI') {
                continue;
            }

            $columnType = strtolower($columnInfo['Type']);
            
            if (strpos($columnType, 'int') !== false || strpos($columnType, 'decimal') !== false || strpos($columnType, 'float') !== false || strpos($columnType, 'double') !== false) {
                $dbData[$columnName] = 0;
            } else {
                $dbData[$columnName] = '';
            }
        }

        return Db::insert($table, $dbData);
    }

    public static function getOne($conditions)
    {
        $definition = static::getDefinition();
        $table = $definition['table'];
        $params = ['_entity_type' => static::$entityName];
        $typeWhere = "`TYPE` = :_entity_type";
        $userWhere = '1';

        if (is_numeric($conditions)) {
            $userWhere = '`id` = :id';
            $params['id'] = $conditions;
        } else {
            list($userWhere, $userParams) = static::buildWhereClause($conditions);
            if ($userWhere !== '1') {
                $params = array_merge($params, $userParams);
            }
        }

        $finalWhere = $typeWhere;
        if ($userWhere !== '1') {
            $finalWhere .= " AND ({$userWhere})";
        }

        $row = Db::one("SELECT * FROM `{$table}` WHERE {$finalWhere} LIMIT 1", $params);
        return static::hydrate($row);
    }

    public static function get($conditions = [])
    {
        $definition = static::getDefinition();
        $table = $definition['table'];
        
        $params = ['_entity_type' => static::$entityName];
        $typeWhere = "`TYPE` = :_entity_type";

        list($userWhere, $userParams) = static::buildWhereClause($conditions);
        
        $finalWhere = $typeWhere;
        if ($userWhere !== '1') {
            $finalWhere .= " AND ({$userWhere})";
            $params = array_merge($params, $userParams);
        }

        $rows = Db::all("SELECT * FROM `{$table}` WHERE {$finalWhere}", $params);
        
        return array_map([static::class, 'hydrate'], $rows);
    }

    public static function update($id, $data)
    {
        $definition = static::getDefinition();
        $table = $definition['table'];
        $storage_mode = $definition['storage_mode'] ?? 'grouped_string';
        $dehydratedData = [];

        $where = "`id` = :id AND `TYPE` = :_entity_type";
        $params = ['id' => $id, '_entity_type' => static::$entityName];

        if ($storage_mode === 'json') {
            $existing = Db::one("SELECT VALUE FROM `{$table}` WHERE {$where}", $params);
            $existingData = $existing ? json_decode($existing['VALUE'], true) : [];
            $newData = array_merge($existingData, $data);
            $dehydratedData['VALUE'] = json_encode($newData, JSON_UNESCAPED_UNICODE);
        } else { // grouped_string
            $groupedDbColumns = [];
            $directDbColumns = [];

            foreach($definition['fields'] as $dbColumn => $prop) {
                if (is_array($prop)) {
                    $hasData = false;
                    foreach($prop as $p) {
                        if(isset($data[$p])) {
                            $hasData = true;
                            break;
                        }
                    }
                    if($hasData) $groupedDbColumns[] = $dbColumn;
                } else {
                    if(isset($data[$prop])) {
                        $directDbColumns[$dbColumn] = $data[$prop];
                    }
                }
            }
            $dehydratedData = $directDbColumns;

            if (!empty($groupedDbColumns)) {
                $existing = Db::one("SELECT " . implode(',', $groupedDbColumns) . " FROM `{$table}` WHERE {$where}", $params);
                if ($existing) {
                    foreach($groupedDbColumns as $col) {
                        $old_values = explode(self::GROUP_SEPARATOR, $existing[$col] ?? '');
                        $merged_data_for_group = [];
                        
                        foreach($definition['fields'][$col] as $index => $propName) {
                            $merged_data_for_group[$propName] = $old_values[$index] ?? null;
                        }
                        foreach($definition['fields'][$col] as $propName) {
                            if (isset($data[$propName])) {
                                $merged_data_for_group[$propName] = $data[$propName];
                            }
                        }

                        $final_values = [];
                        foreach($definition['fields'][$col] as $propName) {
                            $final_values[] = $merged_data_for_group[$propName] ?? '';
                        }
                        $dehydratedData[$col] = implode(self::GROUP_SEPARATOR, $final_values);
                    }
                }
            }
        }

        if (empty($dehydratedData)) {
            return 0;
        }

        return Db::update($table, $dehydratedData, $where, $params);
    }
    
    public static function updateAll($conditions, $data)
    {
        $itemsToUpdate = static::get($conditions);
        if (empty($itemsToUpdate)) {
            return 0;
        }

        $updatedCount = 0;
        Db::begin();
        try {
            foreach ($itemsToUpdate as $item) {
                $updateData = [];
                $itemObject = (object)$item;
                foreach ($data as $key => $value) {
                    if ($value instanceof Closure) {
                        $updateData[$key] = $value->bindTo($itemObject)();
                    } else {
                        $updateData[$key] = $value;
                    }
                }
                if (static::update($item['id'], $updateData)) {
                    $updatedCount++;
                }
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return $updatedCount;
    }

    public static function deleteById($id)
    {
        $definition = static::getDefinition();
        $table = $definition['table'];
        $where = "`id` = :id AND `TYPE` = :_entity_type";
        $params = [
            'id' => $id,
            '_entity_type' => static::$entityName
        ];
        return Db::delete($table, $where, $params);
    }
}


class Entity
{
    private static $entities = [];

    public static function register($entityName, $definition, $options = [])
    {
        if (!is_string($entityName) || empty($entityName)) {
            throw new InvalidArgumentException('Entity name must be a non-empty string.');
        }

        $className = ucfirst($entityName) . 'Entity';
        if (class_exists($className)) {
            return;
        }

        $is_simple_array = empty(array_filter(array_keys($definition), 'is_string'));

        if ($is_simple_array) {
            $table = $options['table'] ?? PREFIX . 'BLOCKS';
            if ($table !== PREFIX . 'BLOCKS') {
                throw new InvalidArgumentException("Simple array definition is only allowed for the 'BLOCKS' table.");
            }

            $fields = array_filter($definition, 'is_string');
            $computed_array = array_filter($definition, 'is_array');
            $computed = !empty($computed_array) ? array_pop($computed_array) : [];
            
            $field_mapping = ['VALUE' => $fields];

            self::$entities[$entityName] = [
                'name' => $entityName,
                'fields' => $field_mapping,
                'computed' => $computed,
                'table' => $table,
                'storage_mode' => 'json'
            ];
        } else {
            $fields = [];
            $computed = [];
            
            $unnamed_definitions = array_filter($definition, 'is_int', ARRAY_FILTER_USE_KEY);
            if (!empty($unnamed_definitions)) {
                $computed = array_pop($unnamed_definitions);
            }

            $named_definitions = array_filter($definition, 'is_string', ARRAY_FILTER_USE_KEY);
            $fields = $named_definitions;

            self::$entities[$entityName] = [
                'name' => $entityName,
                'fields' => $fields,
                'computed' => $computed,
                'table' => $options['table'] ?? PREFIX . 'BLOCKS',
                'storage_mode' => 'grouped_string'
            ];
        }

        $classCode = "class {$className} extends BaseEntity { protected static \$entityName = '{$entityName}'; }" ;
        eval($classCode);
    }

    public static function getDefinition($entityName)
    {
        return self::$entities[$entityName] ?? null;
    }
}
