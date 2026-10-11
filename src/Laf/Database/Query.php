<?php

declare(strict_types=1);

namespace Laf\Database;

use Laf\Database\Field\Field;
use Laf\Exception\DatabaseException;
use Laf\Exception\InvalidValueException;

/**
 * Single-table query bound to a Table's metadata.
 *
 * Column names are checked against the table and quoted for the engine,
 * values are bound through the column's FieldType, so a criterion can come
 * from user input without ever reaching the SQL text. Terminals return
 * hydrated objects (get/first), plain rows (arrays/column), or scalars
 * (count/exists), all from one statement.
 *
 *   Customer::query()->where('city', 'Boston')->orderBy('name')->limit(10)->get();
 *   Customer::query()->where('balance', '>', 0)->whereNull('deleted_on')->count();
 *   Customer::query()->whereIn('id', $ids)->arrays();
 */
final class Query
{
    public const OPERATORS = ['=', '<>', '!=', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL', 'BETWEEN', 'NOT BETWEEN'];

    private Table $table;

    /** @var class-string<BaseObject>|null */
    private ?string $objectClass;

    private ?string $engine;

    /** @var string[] quoted column list; empty = * */
    private array $columns = [];

    /** @var array<int, array{0: string, 1: string}> [boolean, sql] */
    private array $wheres = [];

    /** @var array<string, array{0: mixed, 1: int}> placeholder => [value, PDO type] */
    private array $bindings = [];

    /** @var string[] */
    private array $orders = [];

    private ?int $limit = null;

    private int $offset = 0;

    private int $bindingCount = 0;

    /**
     * @param string|null $objectClass BaseObject subclass used by get()/first(); null = rows only
     * @param string|null $engine Identifier::MYSQL / Identifier::POSTGRES; null = from Settings
     */
    public function __construct(Table $table, ?string $objectClass = null, ?string $engine = null)
    {
        if ($objectClass !== null && !is_a($objectClass, BaseObject::class, true)) {
            throw new \InvalidArgumentException(sprintf('%s is not a %s', $objectClass, BaseObject::class));
        }
        $this->table = $table;
        $this->objectClass = $objectClass;
        $this->engine = $engine;
    }

    public function getTable(): Table
    {
        return $this->table;
    }

    /* ------------------------------------------------------------------ */
    /* building                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Columns to fetch (default: every column). Objects built from a partial
     * row only carry the selected columns.
     * @param string[] $columns
     */
    public function select(array $columns): self
    {
        $this->columns = [];
        foreach ($columns as $column) {
            $this->columns[] = $this->quoteColumn($column);
        }
        return $this;
    }

    /**
     * where('name', 'x')            -> name = :v
     * where('name', null)           -> name IS NULL
     * where('id', [1, 2])           -> id IN (:v, :v)
     * where('qty', '>', 5)          -> qty > :v
     * where('a', 'BETWEEN', [1, 9]) -> a BETWEEN :v AND :v
     * @param mixed $operatorOrValue
     * @param mixed $value
     */
    public function where(string $field, $operatorOrValue = null, $value = null): self
    {
        if (func_num_args() >= 3) {
            return $this->addCondition('AND', $field, (string)$operatorOrValue, $value);
        }
        return $this->addCondition('AND', $field, null, $operatorOrValue);
    }

    /**
     * @param mixed $operatorOrValue
     * @param mixed $value
     */
    public function orWhere(string $field, $operatorOrValue = null, $value = null): self
    {
        if (func_num_args() >= 3) {
            return $this->addCondition('OR', $field, (string)$operatorOrValue, $value);
        }
        return $this->addCondition('OR', $field, null, $operatorOrValue);
    }

    /** @param mixed[] $values */
    public function whereIn(string $field, array $values): self
    {
        return $this->addCondition('AND', $field, 'IN', $values);
    }

    /** @param mixed[] $values */
    public function whereNotIn(string $field, array $values): self
    {
        return $this->addCondition('AND', $field, 'NOT IN', $values);
    }

    public function whereNull(string $field): self
    {
        return $this->addCondition('AND', $field, 'IS NULL', null);
    }

    public function whereNotNull(string $field): self
    {
        return $this->addCondition('AND', $field, 'IS NOT NULL', null);
    }

    /**
     * @param mixed $low
     * @param mixed $high
     */
    public function whereBetween(string $field, $low, $high): self
    {
        return $this->addCondition('AND', $field, 'BETWEEN', [$low, $high]);
    }

    /**
     * A condition written by the developer (never from user input). Placeholders
     * in $sql are bound from $bindings (':name' => value, or ':name' => [value, PDO type]).
     * @param array<string, mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->wheres[] = [$this->boolean($boolean), '(' . $sql . ')'];
        foreach ($bindings as $placeholder => $binding) {
            $placeholder = ':' . ltrim((string)$placeholder, ':');
            $this->bindings[$placeholder] = is_array($binding) && array_keys($binding) === [0, 1] && is_int($binding[1])
                ? [$binding[0], $binding[1]]
                : [$binding, self::pdoTypeFor($binding)];
        }
        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $direction = strtoupper(trim($direction));
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid sort direction "%s"', $direction));
        }
        $this->orders[] = $this->quoteColumn($field) . ' ' . $direction;
        return $this;
    }

    public function limit(?int $limit): self
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('limit must be >= 0');
        }
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('offset must be >= 0');
        }
        $this->offset = $offset;
        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* terminals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Every matching row as an object of the bound class.
     * @return BaseObject[]
     */
    public function get(): array
    {
        $class = $this->requireObjectClass();
        $objects = [];
        foreach ($this->run($this->getSql())->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $objects[] = $class::hydrate($row);
        }
        return $objects;
    }

    /** The first matching row as an object, null when nothing matches. */
    public function first(): ?BaseObject
    {
        $class = $this->requireObjectClass();
        $single = clone $this;
        $single->limit = 1;
        $row = $this->run($single->getSql())->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $class::hydrate($row) : null;
    }

    /**
     * Every matching row as an associative array.
     * @return array<int, array<string, mixed>>
     */
    public function arrays(): array
    {
        return $this->run($this->getSql())->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * One column of every matching row.
     * @return mixed[]
     */
    public function column(string $field): array
    {
        $single = clone $this;
        $single->columns = [$this->quoteColumn($field)];
        return $this->run($single->getSql())->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Number of matching rows (ORDER BY / LIMIT ignored). */
    public function count(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->quoteTable() . $this->whereSql();
        return (int)$this->run($sql)->fetchColumn();
    }

    /** True when at least one row matches. */
    public function exists(): bool
    {
        $sql = 'SELECT 1 FROM ' . $this->quoteTable() . $this->whereSql() . ' LIMIT 1';
        return $this->run($sql)->fetchColumn() !== false;
    }

    /**
     * Delete every matching row; refuses to run without a condition.
     * @return int rows deleted
     */
    public function delete(): int
    {
        if (count($this->wheres) === 0) {
            throw new \LogicException('Query::delete() needs at least one condition');
        }
        $sql = 'DELETE FROM ' . $this->quoteTable() . $this->whereSql();
        $count = $this->run($sql)->rowCount();
        ForeignKey::clearValidationCache($this->table->getName());
        return $count;
    }

    /** The SELECT this query would run. */
    public function getSql(): string
    {
        $columns = count($this->columns) > 0 ? implode(', ', $this->columns) : '*';
        $sql = 'SELECT ' . $columns . ' FROM ' . $this->quoteTable() . $this->whereSql();
        if (count($this->orders) > 0) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset > 0) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        return $sql;
    }

    /**
     * placeholder => [value, PDO type]
     * @return array<string, array{0: mixed, 1: int}>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * placeholder => value
     * @return array<string, mixed>
     */
    public function getBindingValues(): array
    {
        return array_map(fn(array $binding) => $binding[0], $this->bindings);
    }

    /* ------------------------------------------------------------------ */
    /* internals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param mixed $value
     */
    private function addCondition(string $boolean, string $field, ?string $operator, $value): self
    {
        $column = $this->quoteColumn($field);
        $fieldMeta = $this->table->getField($field);

        if ($value instanceof BaseObject) {
            $value = $value->getPrimaryKeyValue();
        }

        if ($operator === null) {
            $operator = $value === null ? 'IS NULL' : (is_array($value) ? 'IN' : '=');
        }
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported operator "%s"', $operator));
        }
        if ($value === null && $operator === '=') {
            $operator = 'IS NULL';
        } else if ($value === null && in_array($operator, ['<>', '!='], true)) {
            $operator = 'IS NOT NULL';
        }

        switch ($operator) {
            case 'IS NULL':
            case 'IS NOT NULL':
                $sql = $column . ' ' . $operator;
                break;

            case 'IN':
            case 'NOT IN':
                if (!is_array($value)) {
                    $value = [$value];
                }
                if (count($value) === 0) {
                    $sql = $operator === 'IN' ? '1 = 0' : '1 = 1';
                    break;
                }
                $placeholders = [];
                foreach ($value as $item) {
                    $placeholders[] = $this->bind($fieldMeta, $item);
                }
                $sql = $column . ' ' . $operator . ' (' . implode(', ', $placeholders) . ')';
                break;

            case 'BETWEEN':
            case 'NOT BETWEEN':
                if (!is_array($value) || count($value) !== 2) {
                    throw new \InvalidArgumentException($operator . ' needs an array of two values');
                }
                $value = array_values($value);
                $sql = $column . ' ' . $operator . ' ' . $this->bind($fieldMeta, $value[0]) . ' AND ' . $this->bind($fieldMeta, $value[1]);
                break;

            case 'LIKE':
            case 'NOT LIKE':
                $sql = $column . ' ' . $operator . ' ' . $this->bind(null, (string)$value);
                break;

            default:
                if (is_array($value)) {
                    throw new \InvalidArgumentException(sprintf('Operator %s does not take an array', $operator));
                }
                $sql = $column . ' ' . $operator . ' ' . $this->bind($fieldMeta, $value);
        }

        $this->wheres[] = [$this->boolean($boolean), $sql];
        return $this;
    }

    /**
     * Register one bound value and return its placeholder. Typed through the
     * column's FieldType when it accepts the value; otherwise bound as a string,
     * which is what the legacy finders did.
     * @param mixed $value
     */
    private function bind(?Field $field, $value): string
    {
        $placeholder = ':v' . $this->bindingCount++;
        $binding = null;
        if ($field !== null && $field->hasType()) {
            try {
                $binding = $field->getType()->toDb($value);
            } catch (InvalidValueException $ex) {
                $binding = null;
            }
        }
        if (!is_array($binding)) {
            $binding = [self::scalar($value), self::pdoTypeFor($value)];
        }
        $this->bindings[$placeholder] = [$binding[0], (int)$binding[1]];
        return $placeholder;
    }

    private function boolean(string $boolean): string
    {
        return strtoupper(trim($boolean)) === 'OR' ? 'OR' : 'AND';
    }

    private function whereSql(): string
    {
        if (count($this->wheres) === 0) {
            return '';
        }
        $sql = '';
        foreach ($this->wheres as $i => [$boolean, $condition]) {
            $sql .= ($i === 0 ? '' : ' ' . $boolean . ' ') . $condition;
        }
        return ' WHERE ' . $sql;
    }

    private function quoteTable(): string
    {
        return Identifier::quote((string)$this->table->getName(), $this->engine);
    }

    private function quoteColumn(string $field): string
    {
        if (!$this->table->hasField($field)) {
            throw new \InvalidArgumentException(sprintf('Table %s has no column "%s"', $this->table->getName(), $field));
        }
        return Identifier::quote($field, $this->engine);
    }

    /** @return class-string<BaseObject> */
    private function requireObjectClass(): string
    {
        if ($this->objectClass === null) {
            throw new \LogicException('This query is not bound to a model class; use arrays() or column()');
        }
        return $this->objectClass;
    }

    /**
     * @param mixed $value
     * @return scalar|null
     */
    private static function scalar($value)
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }
        throw new \InvalidArgumentException('Cannot bind a value of type ' . gettype($value));
    }

    /**
     * @param mixed $value
     */
    private static function pdoTypeFor($value): int
    {
        if ($value === null) {
            return \PDO::PARAM_NULL;
        }
        if (is_int($value) || is_bool($value)) {
            return \PDO::PARAM_INT;
        }
        return \PDO::PARAM_STR;
    }

    private function run(string $sql): \PDOStatement
    {
        try {
            $statement = Db::getInstance()->prepare($sql);
            if (!$statement instanceof \PDOStatement) {
                throw new DatabaseException('No database connection', $sql, $this->bindings);
            }
            foreach ($this->bindings as $placeholder => [$value, $type]) {
                if ($type === \PDO::PARAM_INT && is_bool($value)) {
                    $value = $value ? 1 : 0;
                }
                $statement->bindValue($placeholder, $value, $type);
            }
            $statement->execute();
            return $statement;
        } catch (\PDOException $ex) {
            throw DatabaseException::fromPdo($ex, $sql, $this->bindings);
        }
    }
}
