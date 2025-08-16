<?php

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/exceptions/SpectroError.php';

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
            'table' => $options['table'] ?? PREFIX . strtoupper($entityName) . 'S',
        ];

        $classCode = self::generateEntityClass($className, $entityName);
        eval($classCode);
    }

    public static function getDefinition($entityName)
    {
        return self::$entities[$entityName] ?? null;
    }

    private static function generateEntityClass($className, $entityName)
    {
        return " 
            class {$className}
            {
                private static $$entityName = '{$entityName}';

                private static function getDefinition()
                {
                    return Entity::getDefinition(self::$$entityName);
                }

                private static function buildWhereClause($$conditions)
                {
                    if (empty($$conditions)) {
                        return ['1', []];
                    }

                    $params = [];
                    $paramIndex = 0;
                    
                    $operator = 'AND';
                    $first = $$conditions[0];

                    if (is_string($first) && in_array(strtolower($first), ['and', 'or'])) {
                        $operator = strtoupper(array_shift($$conditions));
                    }

                    $sqlParts = [];
                    foreach ($$conditions as $condition) {
                        if (count($condition) !== 3) {
                            throw new InvalidArgumentException('Invalid condition format.');
                        }
                        list($field, $op, $value) = $condition;
                        $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                        $paramName = ":param_{ " . $paramIndex . " }";
                        $sqlParts[] = "`{""$field""}` {"" . $op . ""} {"" . $paramName . ""}";
                        $params[$paramName] = $value;
                        $paramIndex++;
                    }

                    if (empty($sqlParts)) {
                        return ['1', []];
                    }

                    $where = implode(" {" . $operator . "} ", $sqlParts);
                    return [$where, $params];
                }

                private static function hydrate($data)
                {
                    if (!$data) {
                        return null;
                    }
                    $definition = self::getDefinition();
                    $entity = [];

                    if (isset($data['id'])) $entity['id'] = (int)$data['id'];

                    foreach ($definition['fields'] as $dbColumn => $prop) {
                        if (!isset($data[$dbColumn])) continue;

                        if (is_array($prop)) {
                            $decoded = json_decode($data[$dbColumn], true);
                            if (is_array($decoded)) {
                                foreach ($prop as $p) {
                                    if (isset($decoded[$p])) {
                                        $entity[$p] = $decoded[$p];
                                    }
                                }
                            }
                        } else {
                            $entity[$prop] = $data[$dbColumn];
                        }
                    }
                    
                    if (isset($data['createdAt'])) $entity['createdAt'] = $data['createdAt'];

                    $entityObject = (object)$entity;
                    foreach ($definition['computed'] as $propName => $closure) {
                        if ($closure instanceof Closure) {
                            $entity[$propName] = $closure->bindTo($entityObject)();
                        }
                    }

                    return $entity;
                }

                private static function dehydrate($data)
                {
                    $definition = self::getDefinition();
                    $dbData = [];
                    $groupedData = [];

                    foreach ($definition['fields'] as $dbColumn => $prop) {
                        if (is_array($prop)) {
                            if (!isset($groupedData[$dbColumn])) $groupedData[$dbColumn] = [];
                            foreach ($prop as $p) {
                                if (isset($data[$p])) {
                                    $groupedData[$dbColumn][$p] = $data[$p];
                                }
                            }
                        } else {
                            if (isset($data[$prop])) {
                                $dbData[$dbColumn] = $data[$prop];
                            }
                        }
                    }

                    foreach ($groupedData as $dbColumn => $values) {
                        if (!empty($values)) {
                            $dbData[$dbColumn] = json_encode($values, JSON_UNESCAPED_UNICODE);
                        }
                    }

                    return $dbData;
                }

                public static function create($data)
                {
                    $definition = self::getDefinition();
                    $dbData = self::dehydrate($data);
                    if (!array_key_exists('createdAt', $data)) {
                        $dbData['createdAt'] = date('Y-m-d H:i:s');
                    }

                    return Db::insert($definition['table'], $dbData);
                }

                public static function getOne($conditions)
                {
                    $definition = self::getDefinition();
                    $table = $definition['table'];

                    if (is_numeric($conditions)) {
                        $where = 'id = :id';
                        $params = ['id' => $conditions];
                    } else {
                        list($where, $params) = self::buildWhereClause($conditions);
                    }

                    $row = Db::one("SELECT * FROM `{""$table""}` WHERE {"" . $where . ""} LIMIT 1", $params);
                    return self::hydrate($row);
                }

                public static function get($conditions = [])
                {
                    $definition = self::getDefinition();
                    $table = $definition['table'];

                    list($where, $params) = self::buildWhereClause($conditions);

                    $rows = Db::all("SELECT * FROM `{""$table""}` WHERE {"" . $where . ""}", $params);
                    
                    return array_map([self::class, 'hydrate'], $rows);
                }

                public static function update($id, $data)
                {
                    $definition = self::getDefinition();
                    $table = $definition['table'];
                    
                    $dehydratedData = self::dehydrate($data);

                    $jsonColumns = [];
                    foreach($definition['fields'] as $dbColumn => $prop) {
                        if (is_array($prop) && isset($dehydratedData[$dbColumn])) {
                            $jsonColumns[] = $dbColumn;
                        }
                    }

                    if (!empty($jsonColumns)) {
                        $existing = Db::one("SELECT " . implode(',', $jsonColumns) . " FROM `{""$table""}` WHERE id = :id", ['id' => $id]);
                        if ($existing) {
                            foreach($jsonColumns as $col) {
                                $existingJson = isset($existing[$col]) ? json_decode($existing[$col], true) : [];
                                $newJson = json_decode($dehydratedData[$col], true);
                                $mergedJson = array_merge($existingJson, $newJson);
                                $dehydratedData[$col] = json_encode($mergedJson, JSON_UNESCAPED_UNICODE);
                            }
                        }
                    }

                    if (empty($dehydratedData)) {
                        return 0;
                    }

                    return Db::update($table, $dehydratedData, 'id = :id', ['id' => $id]);
                }
                
                public static function updateAll($conditions, $data)
                {
                    $itemsToUpdate = self::get($conditions);
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
                            if (self::update($item['id'], $updateData)) {
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
                    $definition = self::getDefinition();
                    $table = $definition['table'];
                    return Db::delete($table, 'id = :id', ['id' => $id]);
                }
            }
        ";
    }
}
