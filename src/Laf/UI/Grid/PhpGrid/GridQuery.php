<?php

declare(strict_types=1);

namespace Laf\UI\Grid\PhpGrid;

use Laf\Database\Identifier;

/**
 * Builds the three statements a grid request needs (page of rows, total count, column totals) around
 * the developer's SQL, which is wrapped as a derived table and never parsed. Filter values are bound
 * through unique placeholders (:f0, :f1, ...); the developer's own :params are left untouched.
 * Pure: no connection, the engine is a constructor argument.
 */
final class GridQuery
{
    private string $sql;
    /** @var array<string, Column> keyed by field name */
    private array $columns;
    private string $alias;
    private string $engine;

    /**
     * @param array<string, Column> $columns keyed by field name
     * @param string $alias subquery alias (sanitised with sanitizeAlias())
     * @param string $engine Identifier::MYSQL or Identifier::POSTGRES
     */
    public function __construct(string $sql, array $columns, string $alias, string $engine)
    {
        $this->sql = rtrim(trim($sql), "; \t\n\r");
        $this->columns = $columns;
        $this->alias = self::sanitizeAlias($alias);
        $this->engine = Identifier::isPostgres($engine) ? Identifier::POSTGRES : Identifier::MYSQL;
    }

    /**
     * @param list<array{property: string, operator: string, value: mixed}> $filters from GridRequest::$filters
     * @param array{sortField?: ?string, sortDir?: string, page?: int, rowsPerPage?: int, allRows?: bool, wildcardContains?: bool, totals?: string[]} $options
     * @return array{data: string, count: string, totals: ?string, bindings: array<string, mixed>}
     */
    public function build(array $filters, array $options = []): array
    {
        $bindings = [];
        $where = $this->buildWhere($filters, (bool)($options['wildcardContains'] ?? false), $bindings);
        $orderBy = $this->buildOrderBy($options['sortField'] ?? null, strtoupper((string)($options['sortDir'] ?? 'ASC')));
        $limit = '';
        $rowsPerPage = max(0, (int)($options['rowsPerPage'] ?? 0));
        if (!($options['allRows'] ?? false) && $rowsPerPage > 0) {
            $page = max(1, (int)($options['page'] ?? 1));
            $limit = 'LIMIT ' . $rowsPerPage . ' OFFSET ' . (($page - 1) * $rowsPerPage) . "\n";
        }

        $columnList = [];
        foreach ($this->columns as $column) {
            $columnList[] = self::quoteColumn($column->getFieldName(), $this->engine);
        }

        $from = "FROM (\n" . $this->sql . "\n) " . $this->alias . "\n" . $where;

        $data = "-- grid query: " . $this->alias . "\n"
            . "SELECT " . implode(', ', $columnList) . "\n"
            . $from . $orderBy . $limit;

        $count = "-- grid counter: " . $this->alias . "\n"
            . "SELECT COUNT(*) AS total_number_of_rows\n"
            . $from;

        $totals = null;
        $totalColumns = [];
        foreach ((array)($options['totals'] ?? []) as $name) {
            $quoted = self::quoteColumn((string)$name, $this->engine);
            $totalColumns[] = 'SUM(' . $quoted . ') AS ' . $quoted;
        }
        if ($totalColumns !== []) {
            $totals = "-- grid totals: " . $this->alias . "\n"
                . "SELECT " . implode(', ', $totalColumns) . "\n"
                . $from;
        }

        return ['data' => $data, 'count' => $count, 'totals' => $totals, 'bindings' => $bindings];
    }

    /**
     * @param list<array{property: string, operator: string, value: mixed}> $filters
     * @param array<string, mixed> $bindings filled with :fN => value
     */
    private function buildWhere(array $filters, bool $wildcardContains, array &$bindings): string
    {
        $conditions = [];
        $n = 0;
        foreach ($filters as $filter) {
            $column = $this->columns[$filter['property']] ?? null;
            if ($column === null || !$column->isSearchable()) {
                continue;
            }
            $operator = $column->getSearchOperator() ?? $filter['operator'];
            if (!in_array($operator, GridRequest::OPERATORS, true)) {
                $operator = 'like';
            }
            $expr = self::quoteColumn($column->getFieldName(), $this->engine);
            $value = $filter['value'];
            $condition = null;

            switch ($operator) {
                case 'isnull':
                    $condition = $expr . ' IS NULL';
                    break;
                case 'notnull':
                    $condition = $expr . ' IS NOT NULL';
                    break;
                case 'in':
                    $values = self::listValue($value, ',');
                    if ($values === []) {
                        break;
                    }
                    $names = [];
                    foreach ($values as $v) {
                        $name = ':f' . $n++;
                        $names[] = $name;
                        $bindings[$name] = $v;
                    }
                    $condition = $expr . ' IN (' . implode(', ', $names) . ')';
                    break;
                case 'between':
                    $values = self::listValue($value, '..');
                    if (count($values) < 2) {
                        break;
                    }
                    $low = ':f' . $n++;
                    $high = ':f' . $n++;
                    $bindings[$low] = $values[0];
                    $bindings[$high] = $values[1];
                    $condition = $expr . ' BETWEEN ' . $low . ' AND ' . $high;
                    break;
                case 'like':
                case 'notlike':
                    if (is_array($value)) {
                        break;
                    }
                    $mode = $column->getWildcardMode() ?? ($wildcardContains ? Column::WILDCARD_CONTAINS : Column::WILDCARD_STARTS_WITH);
                    $text = self::scalarToString($value);
                    if ($mode === Column::WILDCARD_STARTS_WITH) {
                        $text .= '%';
                    } elseif ($mode === Column::WILDCARD_ENDS_WITH) {
                        $text = '%' . $text;
                    } else {
                        $text = '%' . $text . '%';
                    }
                    $name = ':f' . $n++;
                    $bindings[$name] = $text;
                    $not = $operator === 'notlike' ? 'NOT ' : '';
                    if ($this->engine === Identifier::POSTGRES) {
                        $condition = self::castToText($expr, $this->engine) . ' ' . $not . 'ILIKE ' . $name;
                    } else {
                        $condition = $expr . ' ' . $not . 'LIKE ' . $name;
                    }
                    break;
                default:
                    if (is_array($value)) {
                        break;
                    }
                    $sqlOperator = self::operatorSql($operator);
                    if ($value === null) {
                        if ($sqlOperator === '=') {
                            $condition = $expr . ' IS NULL';
                        } elseif ($sqlOperator === '!=') {
                            $condition = $expr . ' IS NOT NULL';
                        }
                        break;
                    }
                    $name = ':f' . $n++;
                    if ($column->isCaseInsensitive() === true && ($sqlOperator === '=' || $sqlOperator === '!=')) {
                        $bindings[$name] = mb_strtolower(self::scalarToString($value));
                        $condition = 'LOWER(' . self::castToText($expr, $this->engine) . ') ' . $sqlOperator . ' ' . $name;
                    } else {
                        $bindings[$name] = $value;
                        $condition = $expr . ' ' . $sqlOperator . ' ' . $name;
                    }
            }

            if ($condition !== null) {
                $conditions[] = $condition;
            }
        }

        if ($conditions === []) {
            return '';
        }
        return "WHERE " . implode("\n\tAND ", $conditions) . "\n";
    }

    private function buildOrderBy(?string $field, string $dir): string
    {
        if ($field === null || $field === '' || !isset($this->columns[$field])) {
            return '';
        }
        $column = $this->columns[$field];
        $expr = self::quoteColumn($column->getFieldName(), $this->engine);
        if ($this->engine === Identifier::POSTGRES && $column->isCaseInsensitiveSort() === true) {
            $expr = 'LOWER(' . self::castToText($expr, $this->engine) . ')';
        }
        return 'ORDER BY ' . $expr . ' ' . ($dir === 'DESC' ? 'DESC' : 'ASC') . "\n";
    }

    /**
     * The SQL operator for a client operator (comparison operators only; like/in/between/null are
     * rendered by build()). Unknown operators are treated as 'like' by the caller.
     */
    public static function operatorSql(string $operator): string
    {
        return match ($operator) {
            'lt' => '<',
            'gt' => '>',
            'lteq' => '<=',
            'gteq' => '>=',
            'eq', 'stricteq' => '=',
            'noteq', 'notstricteq' => '!=',
            'notlike' => 'NOT LIKE',
            default => 'LIKE',
        };
    }

    /**
     * Quote a column name only when quoting does not change its meaning: on MySQL every plain
     * identifier gets backticks; on PostgreSQL only an all-lower-case identifier gets double quotes
     * (a mixed-case alias in the developer's SQL is folded to lower case by the server, so it must stay
     * unquoted to keep matching). Anything else is returned as written, exactly as before.
     */
    public static function quoteColumn(string $name, string $engine): string
    {
        if (Identifier::isPostgres($engine)) {
            return preg_match('/^[a-z_][a-z0-9_]*$/', $name) === 1 ? '"' . $name . '"' : $name;
        }
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1 ? '`' . $name . '`' : $name;
    }

    /**
     * Text cast of an expression: CAST(x AS CHAR) on MySQL, CAST(x AS TEXT) on PostgreSQL
     */
    public static function castToText(string $expr, string $engine): string
    {
        return 'CAST(' . $expr . ' AS ' . (Identifier::isPostgres($engine) ? 'TEXT' : 'CHAR') . ')';
    }

    /**
     * A grid name reduced to a safe derived-table alias
     */
    public static function sanitizeAlias(string $name): string
    {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '_', $name) ?? '';
        if ($alias === '' || preg_match('/^[0-9]/', $alias) === 1) {
            $alias = 'grid_' . $alias;
        }
        return $alias;
    }

    /**
     * A list value for IN / BETWEEN: an array of scalars, or a string split on the separator
     * @param mixed $value
     * @return list<scalar>
     */
    private static function listValue($value, string $separator): array
    {
        if (is_array($value)) {
            $values = array_values(array_filter($value, 'is_scalar'));
        } elseif ($value === null) {
            return [];
        } else {
            $values = explode($separator, self::scalarToString($value));
        }
        $out = [];
        foreach ($values as $v) {
            $v = is_string($v) ? trim($v) : $v;
            if ($v === '') {
                continue;
            }
            $out[] = $v;
        }
        return $out;
    }

    /**
     * @param mixed $value
     */
    private static function scalarToString($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return is_scalar($value) ? (string)$value : '';
    }
}
