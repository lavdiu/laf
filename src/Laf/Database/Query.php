<?php

namespace Laf\Database\Query;

use Laf\Database\BaseObject;
use Laf\Database\Db;

/**
 * A fluent query builder for Laf ORM objects.
 */
class Builder
{
    protected string $model;
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;

    /**
     * Builder constructor.
     * @param string $model The fully qualified class name of the model.
     */
    public function __construct(string $model)
    {
        $this->model = $model;
    }

    /**
     * Add a basic WHERE clause to the query.
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return $this
     */
    public function where(string $column, string $operator, $value): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];
        return $this;
    }

    /**
     * Add an OR WHERE clause to the query.
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return $this
     */
    public function orWhere(string $column, string $operator, $value): self
    {
        $this->wheres[] = [
            'type' => 'OR',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];
        return $this;
    }

    /**
     * Add an ORDER BY clause to the query.
     *
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "`{$column}` {$direction}";
        return $this;
    }

    /**
     * Set the LIMIT for the query.
     *
     * @param int $count
     * @return $this
     */
    public function limit(int $count): self
    {
        $this->limit = $count;
        return $this;
    }

    /**
     * Set the OFFSET for the query.
     *
     * @param int $count
     * @return $this
     */
    public function offset(int $count): self
    {
        $this->offset = $count;
        return $this;
    }

    /**
     * Execute the query and return a collection of hydrated model objects.
     *
     * @return array An array of model instances.
     * @throws \Exception
     */
    public function get(): array
    {
        [$sql, $bindings] = $this->toSql();
        $db = Db::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $objects = [];

        foreach ($results as $row) {
            /** @var BaseObject $object */
            $object = new $this->model();
            $object->populateFromArray($row);
            $objects[] = $object;
        }

        return $objects;
    }

    /**
     * Execute the query and get the first result.
     *
     * @return BaseObject|null
     * @throws \Exception
     */
    public function first(): ?BaseObject
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Get the SQL representation of the query and its bindings.
     *
     * @return array [string $sql, array $bindings]
     */
    public function toSql(): array
    {
        /** @var BaseObject $modelInstance */
        $modelInstance = new $this->model();
        $tableName = $modelInstance->getTable()->getName();

        $sql = "SELECT * FROM `{$tableName}`";
        $bindings = [];

        if (!empty($this->wheres)) {
            $sql .= " WHERE ";
            foreach ($this->wheres as $i => $where) {
                if ($i > 0) {
                    $sql .= " {$where['type']} ";
                }
                // Use named placeholders for security and clarity
                $placeholder = ":where_{$i}";
                $sql .= "`{$where['column']}` {$where['operator']} {$placeholder}";
                $bindings[$placeholder] = $where['value'];
            }
        }

        if (!empty($this->orders)) {
            $sql .= " ORDER BY " . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT " . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET " . $this->offset;
        }

        return [$sql . ';', $bindings];
    }
}
