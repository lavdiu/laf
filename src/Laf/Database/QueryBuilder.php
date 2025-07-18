<?php

namespace Laf\Database;

use PDO;
use Laf\Database\Db;
use Laf\Database\Table;
use Laf\Database\Field\Field;

/**
 * Advanced Query Builder for ORM
 * Supports fluent filtering, sorting, joins, eager loading, and more.
 */
class QueryBuilder
{
    /**
     * @var bool Debug flag for outputting queries and bindings
     */
    protected $debug = false;

    /**
     * @var \Laf\Logger\LoggerInterface|null
     */
    protected $logger = null;

    /**
     * @var string|null
     */
    protected $customSql = null;

    /**
     * Set a custom SQL statement to be executed by get()
     * @param string $sql
     * @return $this
     */
    public function setCustomSql(string $sql)
    {
        $this->customSql = $sql;
        return $this;
    }

    /**
     * @var Table
     */
    protected $table;

    /**
     * @var string
     */
    protected $alias;

    /**
     * @var array
     */
    protected $select = [];

    /**
     * @var array
     */
    protected $wheres = [];

    /**
     * @var array
     */
    protected $bindings = [];

    /**
     * @var array
     */
    protected $orders = [];

    /**
     * @var int|null
     */
    protected $limit = null;

    /**
     * @var int|null
     */
    protected $offset = null;

    /**
     * @var array
     */
    protected $joins = [];

    /**
     * @var array
     */
    protected $with = [];

    /**
     * @var string|null
     */
    protected $asObjectClass = null;

    /**
     * Enable or disable debug output
     * @param bool $debug
     * @return $this
     */
    public function setDebug(bool $debug = true)
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Attach a PSR logger for debug/info/error output
     * @param \Laf\Logger\LoggerInterface $logger
     * @return $this
     */
    public function setLogger(\Laf\Logger\LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Log a debug/info message (uses logger if set, otherwise echo if debug)
     * @param string $message
     * @param array $context
     */
    protected function logDebug($message, array $context = [])
    {
        if ($this->logger) {
            $this->logger->debug($message, $context);
        } elseif ($this->debug) {
            echo "[QueryBuilder DEBUG] $message\n";
            if (!empty($context)) {
                echo print_r($context, true) . "\n";
            }
        }
    }

    public function __construct(Table $table, $alias = null)
    {
        $this->table = $table;
        $this->alias = $alias ?: $table->getName();
    }

    public function select($fields)
    {
        if (is_array($fields)) {
            $this->select = $fields;
        } else {
            $this->select = func_get_args();
        }
        return $this;
    }

    public function where($field, $operator, $value)
    {
        $param = ':w_' . count($this->bindings);
        $this->wheres[] = ["{$this->alias}.{$field}", $operator, $param];
        $this->bindings[$param] = $value;
        return $this;
    }

    public function orWhere($field, $operator, $value)
    {
        $param = ':w_' . count($this->bindings);
        $this->wheres[] = ['OR', "{$this->alias}.{$field}", $operator, $param];
        $this->bindings[$param] = $value;
        return $this;
    }

    public function orderBy($field, $direction = 'ASC')
    {
        $this->orders[] = ["{$this->alias}.{$field}", strtoupper($direction)];
        return $this;
    }

    public function limit($limit, $offset = null)
    {
        $this->limit = (int)$limit;
        if ($offset !== null) {
            $this->offset = (int)$offset;
        }
        return $this;
    }

    public function join($table, $first, $operator, $second, $type = 'INNER')
    {
        $this->joins[] = compact('type', 'table', 'first', 'operator', 'second');
        return $this;
    }

    public function leftJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function with($relation)
    {
        $this->with[] = $relation;
        return $this;
    }

    public function asObject($className)
    {
        $this->asObjectClass = $className;
        return $this;
    }

    protected function buildSelectSql()
    {
        $fields = $this->select ? join(', ', $this->select) : "{$this->alias}.*";
        $sql = "SELECT {$fields} FROM `{$this->table->getName()}` AS {$this->alias}";
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN `{$join['table']}` ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        if ($this->wheres) {
            $whereSql = [];
            foreach ($this->wheres as $where) {
                if (isset($where[0]) && $where[0] === 'OR') {
                    $whereSql[] = 'OR ' . $where[1] . ' ' . $where[2] . ' ' . $where[3];
                } else {
                    $whereSql[] = $where[0] . ' ' . $where[1] . ' ' . $where[2];
                }
            }
            $sql .= ' WHERE ' . ltrim(join(' ', $whereSql), 'OR ');
        }
        if ($this->orders) {
            $orderSql = [];
            foreach ($this->orders as $order) {
                $orderSql[] = $order[0] . ' ' . $order[1];
            }
            $sql .= ' ORDER BY ' . join(', ', $orderSql);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT :_limit';
            if ($this->offset !== null) {
                $sql .= ' OFFSET :_offset';
            }
        }
        return $sql;
    }

    public function get()
    {
        $sql = $this->customSql ?? $this->buildSelectSql();
        $this->logDebug('Executing query', ['sql' => $sql, 'bindings' => $this->bindings]);
        $db = Db::getInstance();
        $stmt = $db->prepare($sql);
        // Bind all where/orWhere values
        foreach ($this->bindings as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        // Bind limit/offset as integers if set
        if ($this->limit !== null) {
            $stmt->bindValue(':_limit', $this->limit, PDO::PARAM_INT);
        }
        if ($this->offset !== null) {
            $stmt->bindValue(':_offset', $this->offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->logDebug('Query executed, result count: ' . count($results));
        if ($this->asObjectClass) {
            $objects = [];
            foreach ($results as $row) {
                $obj = new $this->asObjectClass();
                foreach ($row as $key => $val) {
                    if (method_exists($obj, 'setFieldValue')) {
                        $obj->setFieldValue($key, $val);
                    } else {
                        $obj->$key = $val;
                    }
                }
                $objects[] = $obj;
            }
            // Eager loading
            foreach ($this->with as $relation) {
                foreach ($objects as $obj) {
                    if (method_exists($obj, 'loadRelation')) {
                        $obj->loadRelation($relation);
                    }
                }
            }
            return $objects;
        }
        return $results;
    }

    public function first()
    {
        $this->limit(1);
        $results = $this->get();
        return $results ? $results[0] : null;
    }
}
