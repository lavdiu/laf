<?php

namespace Laf\UI\Grid\PhpGrid;

use JsonSerializable;

/**
 * One column of a PhpGrid: which field of the grid SQL it shows, how grid.js renders it and how the
 * server searches and sorts it. The public properties are the JSON contract with grid.js
 * (see jsonSerialize()); the typed setters are the API for PHP code.
 */
class Column implements JsonSerializable
{
    public int $index = 0;
    public string $fieldName = '';
    public string $label = '';
    /** Detected from the result set when empty: integer, float, boolean, date, time, datetime, string */
    public string $format = '';
    /** Link template, {column} and [query_param] are replaced by grid.js; null = plain text */
    public ?string $href = null;
    public ?string $target = null;
    public string $innerElementCssStyle = '';
    public string $innerElementCssClass = '';
    public string $outerElementCssStyle = '';
    public string $outerElementCssClass = '';
    public bool $visible = true;
    public bool $exportable = true;
    /** Extra attributes for the cell content, as a JSON object string: '{"data-x":"1"}' */
    public string $innerElementAttributes = '';
    /** Extra attributes for the td, as a JSON object string */
    public string $outerElementAttributes = '';
    /** Draw a search input for this column and accept filters on it */
    public bool $searchable = true;
    /** Draw a sort button for this column and accept sort requests on it */
    public bool $sortable = true;
    // Optional per-column search tuning
    /** Force a case-insensitive comparison for = and != (LIKE is always case-insensitive) */
    public ?bool $caseInsensitive = null;
    /** Force a case-insensitive sort (PostgreSQL; MySQL collations already are) */
    public ?bool $caseInsensitiveSort = null;
    /** Override the operator sent by the client (like, eq, gt, in, between, ...) */
    public ?string $searchOperator = null;
    /** LIKE wildcard placement: contains (default when wildcard search is on), startswith, endswith */
    public ?string $wildcardMode = null;
    /** JS function name called per cell (dot-notation allowed): fn(cellValue, row, columnDef) */
    public ?string $jsCallback = null;

    public const FORMAT_INTEGER = 'integer';
    public const FORMAT_FLOAT = 'float';
    public const FORMAT_BOOLEAN = 'boolean';
    public const FORMAT_DATE = 'date';
    public const FORMAT_TIME = 'time';
    public const FORMAT_DATETIME = 'datetime';
    public const FORMAT_STRING = 'string';

    public const WILDCARD_CONTAINS = 'contains';
    public const WILDCARD_STARTS_WITH = 'startswith';
    public const WILDCARD_ENDS_WITH = 'endswith';

    /** The JSON keys, in output order (grid.js reads them by name) */
    private const JSON_KEYS = [
        'index', 'fieldName', 'label', 'format', 'href', 'target',
        'innerElementCssStyle', 'innerElementCssClass', 'outerElementCssStyle', 'outerElementCssClass',
        'visible', 'exportable', 'innerElementAttributes', 'outerElementAttributes',
        'caseInsensitive', 'caseInsensitiveSort', 'searchOperator', 'wildcardMode', 'jsCallback',
        'searchable', 'sortable',
    ];

    private const BOOL_PROPERTIES = ['visible', 'exportable', 'searchable', 'sortable'];
    private const NULLABLE_BOOL_PROPERTIES = ['caseInsensitive', 'caseInsensitiveSort'];
    private const NULLABLE_STRING_PROPERTIES = ['href', 'target', 'searchOperator', 'wildcardMode', 'jsCallback'];

    /**
     * The positional signature is the one the generated pages and the applications use:
     * new Column('name', 'Label', $visible, $exportable, $href, $target, $innerStyle, $innerClass, $outerStyle, ...)
     */
    public function __construct(
        ?string $fieldName = null,
        ?string $label = null,
        bool    $visible = true,
        bool    $exportable = true,
        ?string $href = null,
        ?string $target = null,
        ?string $innerElementCssStyle = null,
        ?string $innerElementCssClass = null,
        ?string $outerElementCssStyle = null,
        ?string $outerElementCssClass = null,
        ?string $innerElementAttributes = null,
        ?string $outerElementAttributes = null
    ) {
        $this->fieldName = trim($fieldName ?? '');
        $this->label = $label ?? '';
        $this->href = $href;
        $this->target = $target;
        $this->innerElementCssStyle = $innerElementCssStyle ?? '';
        $this->innerElementCssClass = $innerElementCssClass ?? '';
        $this->outerElementCssStyle = $outerElementCssStyle ?? '';
        $this->outerElementCssClass = $outerElementCssClass ?? '';
        $this->visible = $visible;
        $this->exportable = $exportable;
        $this->innerElementAttributes = $innerElementAttributes ?? '';
        $this->outerElementAttributes = $outerElementAttributes ?? '';
    }

    /**
     * Column from its JSON form (the output of json_encode($column))
     */
    public static function createFromJsonString(string $json_string): Column
    {
        $settings = json_decode($json_string, true);
        return self::createFromAssocArray(is_array($settings) ? $settings : []);
    }

    /**
     * Column from an array keyed by property name; unknown keys are ignored
     */
    public static function createFromAssocArray(array $settings): Column
    {
        $object = new self(isset($settings['fieldName']) ? (string)$settings['fieldName'] : '');
        foreach ($settings as $_property => $_value) {
            $object->setPropertyValue((string)$_property, $_value);
        }
        return $object;
    }

    /**
     * Assign one property by name, casting the value to the property's type (so a decoded JSON
     * column round-trips); unknown names are ignored.
     * @param mixed $value
     */
    public function setPropertyValue(string $property, $value): void
    {
        if (!in_array($property, self::JSON_KEYS, true)) {
            return;
        }
        if ($property === 'index') {
            $this->index = (int)$value;
        } elseif (in_array($property, self::BOOL_PROPERTIES, true)) {
            $this->$property = self::toBool($value) ?? true;
        } elseif (in_array($property, self::NULLABLE_BOOL_PROPERTIES, true)) {
            $this->$property = self::toBool($value);
        } elseif (in_array($property, self::NULLABLE_STRING_PROPERTIES, true)) {
            $this->$property = ($value === null || $value === '') ? null : self::toString($value);
        } else {
            $this->$property = $value === null ? '' : self::toString($value);
        }
        if ($property === 'fieldName') {
            $this->fieldName = trim($this->fieldName);
        }
    }

    /**
     * @param mixed $value
     */
    private static function toBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$value;
        }
        return (bool)$value;
    }

    /**
     * @param mixed $value
     */
    private static function toString($value): string
    {
        if (is_array($value)) {
            return (string)json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $out = [];
        foreach (self::JSON_KEYS as $key) {
            $out[$key] = $this->$key;
        }
        return $out;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function setIndex(int $index): static
    {
        $this->index = $index;
        return $this;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): static
    {
        $this->fieldName = trim($fieldName);
        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;
        return $this;
    }

    public function isNumeric(): bool
    {
        return $this->format === self::FORMAT_INTEGER || $this->format === self::FORMAT_FLOAT;
    }

    public function getHref(): ?string
    {
        return $this->href;
    }

    public function setHref(?string $href): static
    {
        $this->href = ($href === '') ? null : $href;
        return $this;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $target): static
    {
        $this->target = ($target === '') ? null : $target;
        return $this;
    }

    public function getInnerElementCssStyle(): string
    {
        return $this->innerElementCssStyle;
    }

    public function setInnerElementCssStyle(string $innerElementCssStyle): static
    {
        $this->innerElementCssStyle = $innerElementCssStyle;
        return $this;
    }

    public function getInnerElementCssClass(): string
    {
        return $this->innerElementCssClass;
    }

    public function setInnerElementCssClass(string $innerElementCssClass): static
    {
        $this->innerElementCssClass = $innerElementCssClass;
        return $this;
    }

    public function getOuterElementCssStyle(): string
    {
        return $this->outerElementCssStyle;
    }

    public function setOuterElementCssStyle(string $outerElementCssStyle): static
    {
        $this->outerElementCssStyle = $outerElementCssStyle;
        return $this;
    }

    public function getOuterElementCssClass(): string
    {
        return $this->outerElementCssClass;
    }

    public function setOuterElementCssClass(string $outerElementCssClass): static
    {
        $this->outerElementCssClass = $outerElementCssClass;
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;
        return $this;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }

    public function setExportable(bool $exportable): static
    {
        $this->exportable = $exportable;
        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function setSearchable(bool $searchable): static
    {
        $this->searchable = $searchable;
        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function setSortable(bool $sortable): static
    {
        $this->sortable = $sortable;
        return $this;
    }

    public function getInnerElementAttributes(): string
    {
        return $this->innerElementAttributes;
    }

    /**
     * @param string|array $innerElementAttributes a JSON object string, or an array name => value
     */
    public function setInnerElementAttributes($innerElementAttributes): static
    {
        $this->innerElementAttributes = self::toString($innerElementAttributes);
        return $this;
    }

    public function getOuterElementAttributes(): string
    {
        return $this->outerElementAttributes;
    }

    /**
     * @param string|array $outerElementAttributes a JSON object string, or an array name => value
     */
    public function setOuterElementAttributes($outerElementAttributes): static
    {
        $this->outerElementAttributes = self::toString($outerElementAttributes);
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getJsCallback(): ?string
    {
        return $this->jsCallback;
    }

    /**
     * @param string|null $jsCallback JS function name (dot-notation allowed, e.g. "MyApp.columns.formatStatus")
     */
    public function setJsCallback(?string $jsCallback): static
    {
        $this->jsCallback = ($jsCallback === '') ? null : $jsCallback;
        return $this;
    }

    public function getSearchOperator(): ?string
    {
        return $this->searchOperator;
    }

    /**
     * @param string|null $operator one of GridRequest::OPERATORS (like, eq, noteq, lt, gt, lteq, gteq, in, between, isnull, notnull, notlike)
     */
    public function setSearchOperator(?string $operator): static
    {
        $this->searchOperator = ($operator === '') ? null : $operator;
        return $this;
    }

    public function getWildcardMode(): ?string
    {
        return $this->wildcardMode;
    }

    /**
     * @param string|null $mode Column::WILDCARD_CONTAINS, WILDCARD_STARTS_WITH, WILDCARD_ENDS_WITH or null for the grid default
     */
    public function setWildcardMode(?string $mode): static
    {
        $this->wildcardMode = ($mode === '') ? null : $mode;
        return $this;
    }

    public function isCaseInsensitive(): ?bool
    {
        return $this->caseInsensitive;
    }

    public function setCaseInsensitive(?bool $caseInsensitive): static
    {
        $this->caseInsensitive = $caseInsensitive;
        return $this;
    }

    public function isCaseInsensitiveSort(): ?bool
    {
        return $this->caseInsensitiveSort;
    }

    public function setCaseInsensitiveSort(?bool $caseInsensitiveSort): static
    {
        $this->caseInsensitiveSort = $caseInsensitiveSort;
        return $this;
    }
}
