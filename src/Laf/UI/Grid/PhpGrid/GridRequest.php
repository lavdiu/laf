<?php

declare(strict_types=1);

namespace Laf\UI\Grid\PhpGrid;

/**
 * The grid parameters of one request (page, limit, sort, dir, searchParams, load_grid_by_name,
 * export_grid_to_excel / export_grid_to_csv) as typed values. Built from an already-decoded query
 * array such as $_GET; nothing here is trusted, every value is validated or dropped.
 */
final class GridRequest
{
    /** Operators the client may send; anything else falls back to 'like' (as the previous versions did) */
    public const OPERATORS = [
        'lt', 'gt', 'lteq', 'gteq', 'eq', 'stricteq', 'noteq', 'notstricteq',
        'like', 'notlike', 'in', 'between', 'isnull', 'notnull',
    ];

    public const EXPORT_XLSX = 'xlsx';
    public const EXPORT_CSV = 'csv';

    /** 1-based page, never below 1 */
    public int $page = 1;
    /** Rows per page requested by the client: null = not sent, 0 = all rows, else 1..$maxRowsPerPage */
    public ?int $limit = null;
    /** Sort column as sent (validated against the columns by PhpGrid) */
    public ?string $sort = null;
    /** Sort direction exactly as sent ('asc', 'DESC', ...) when it is one of the two, else null */
    public ?string $dir = null;
    /** @var list<array{property: string, operator: string, value: mixed}> */
    public array $filters = [];
    /** load_grid_by_name */
    public ?string $gridName = null;
    /** self::EXPORT_XLSX, self::EXPORT_CSV or null for JSON */
    public ?string $export = null;

    /**
     * @param array<string, mixed> $query the decoded query string ($_GET or a copy)
     */
    public static function fromArray(array $query, int $maxRowsPerPage = 1000): self
    {
        $r = new self();

        if (isset($query['page']) && is_numeric($query['page'])) {
            $r->page = max(1, (int)$query['page']);
        }

        if (isset($query['limit']) && is_numeric($query['limit'])) {
            $limit = (int)$query['limit'];
            if ($limit > 0) {
                $limit = max(1, min($limit, max(1, $maxRowsPerPage)));
            }
            $r->limit = $limit < 0 ? null : $limit;
        }

        if (isset($query['sort']) && is_string($query['sort']) && trim($query['sort']) !== '') {
            $r->sort = trim($query['sort']);
        }

        if (isset($query['dir']) && is_string($query['dir']) && in_array(strtolower(trim($query['dir'])), ['asc', 'desc'], true)) {
            $r->dir = trim($query['dir']);
        }

        if (isset($query['searchParams'])) {
            $r->filters = self::parseFilters($query['searchParams']);
        }

        if (isset($query['load_grid_by_name']) && is_scalar($query['load_grid_by_name'])) {
            $r->gridName = (string)$query['load_grid_by_name'];
        }

        if (isset($query['export_grid_to_excel']) && is_scalar($query['export_grid_to_excel']) && (int)$query['export_grid_to_excel'] >= 1) {
            $r->export = self::EXPORT_XLSX;
        } elseif (array_key_exists('export_grid_to_csv', $query)) {
            $r->export = self::EXPORT_CSV;
        }

        return $r;
    }

    /**
     * 'ASC' / 'DESC' for the SQL, null when the request did not send a valid direction
     */
    public function dirSql(): ?string
    {
        return $this->dir === null ? null : strtoupper($this->dir);
    }

    public function isExport(): bool
    {
        return $this->export !== null;
    }

    /**
     * searchParams is a JSON list of {property, operator, value}. It is decoded once; a value that is
     * not valid JSON is tried again after urldecode() for callers that encoded it themselves.
     * @param mixed $raw
     * @return list<array{property: string, operator: string, value: mixed}>
     */
    private static function parseFilters($raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw)) {
            $items = json_decode($raw, true);
            if (!is_array($items)) {
                $items = json_decode(urldecode($raw), true);
            }
            if (!is_array($items)) {
                return [];
            }
        } else {
            return [];
        }

        $filters = [];
        foreach ($items as $item) {
            if (is_object($item)) {
                $item = get_object_vars($item);
            }
            if (!is_array($item) || !isset($item['property']) || !is_string($item['property']) || trim($item['property']) === '') {
                continue;
            }
            $operator = isset($item['operator']) && is_string($item['operator']) ? strtolower(trim($item['operator'])) : 'like';
            if (!in_array($operator, self::OPERATORS, true)) {
                $operator = 'like';
            }
            $value = $item['value'] ?? null;
            if (is_array($value)) {
                if (array_keys($value) !== range(0, count($value) - 1) && $value !== []) {
                    continue;   // a JSON object is not a value
                }
                $value = array_values(array_filter($value, 'is_scalar'));
            } elseif ($value !== null && !is_scalar($value)) {
                continue;
            }
            $filters[] = ['property' => trim($item['property']), 'operator' => $operator, 'value' => $value];
        }
        return $filters;
    }
}
