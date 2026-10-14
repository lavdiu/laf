<?php

declare(strict_types=1);

namespace Laf\UI\Form\Input;

use Laf\Database\Db;
use Laf\Database\Field\Field;
use Laf\Database\Query;
use Laf\Database\Table;
use Laf\UI\Form\FormElementInterface;
use Laf\Util\Settings;
use Laf\Util\Util;

/**
 * A foreign-key input that searches the referenced table while the user types, for tables too
 * big for a <select> (customers, products). It posts the key in a hidden input under the field's
 * name, so a form handles it exactly like a Select; the visible text box only searches.
 *
 * Pick it per field with the column comment {"form":{"element":"Autocomplete"}},
 * $layout->element('customer_id', 'Autocomplete') or $field->setFormElementClass('Autocomplete'),
 * or for every foreign key whose table has more rows than Settings form.select_autocomplete_threshold.
 *
 * The page answers the search itself, before any output (like a grid):
 *   $form = $obj->getForm(DrawMode::UPDATE);
 *   $form->handleLookupRequest();   // answers ?laf_lookup=table.field&laf_q=... with JSON and exits
 * Only autocomplete fields on that form are answered, filtered by the field's db selection criteria.
 *
 * The script (vanilla, no dependencies, Bootstrap 5 dropdown markup) is printed inline once per
 * request; setInlineScript(false) leaves it out for pages that load getScriptPath() themselves.
 */
class Autocomplete extends Select implements FormElementInterface
{
    public const REQUEST_KEY = 'laf_lookup';
    public const REQUEST_TERM = 'laf_q';

    /** Longest search term taken from the request */
    public const MAX_TERM_LENGTH = 100;

    private static bool $inlineScript = true;

    private static bool $scriptDrawn = false;

    /** @var array<string, int> referenced table => row count, per request */
    private static array $rowCounts = [];

    private int $minChars = 1;

    private int $limit = 20;

    /** @var string[] columns of the referenced table searched; empty = its display field */
    private array $searchColumns = [];

    /** @var callable|null fn(string $term, int $limit, Autocomplete $element): array */
    private $lookupCallback = null;

    /** @var callable|null fn(string $value, Autocomplete $element): ?string */
    private $labelCallback = null;

    private ?string $lookupUrl = null;

    private string $noResultsText = 'No matches';

    /* ───────────── configuration ───────────── */

    /** Characters typed before the first search (0 = search on focus) */
    public function setMinChars(int $minChars): static
    {
        $this->minChars = max(0, $minChars);
        return $this;
    }

    public function getMinChars(): int
    {
        return $this->minChars;
    }

    /** Most results per search (1–100) */
    public function setLimit(int $limit): static
    {
        $this->limit = max(1, min(100, $limit));
        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Columns of the referenced table to search (any of them may contain the text), e.g. ['code', 'name'];
     * empty = the table's display field. Unknown columns are ignored.
     * @param string[] $columns
     */
    public function setSearchColumns(array $columns): static
    {
        $this->searchColumns = array_values(array_map('strval', $columns));
        return $this;
    }

    /** @return string[] */
    public function getSearchColumns(): array
    {
        return $this->searchColumns;
    }

    /**
     * A custom source instead of the referenced table: fn(string $term, int $limit, Autocomplete $el)
     * returns value => label, or a list of ['value' => ..., 'label' => ...]. Pair it with
     * setLabelCallback() so a stored value shows its label.
     */
    public function setLookupCallback(?callable $callback): static
    {
        $this->lookupCallback = $callback;
        return $this;
    }

    /** fn(string $value, Autocomplete $el): ?string, the label of a stored value (custom sources) */
    public function setLabelCallback(?callable $callback): static
    {
        $this->labelCallback = $callback;
        return $this;
    }

    /** Where the script sends the search (default: the current page URL with the lookup parameters) */
    public function setLookupUrl(?string $url): static
    {
        $this->lookupUrl = $url;
        return $this;
    }

    public function getLookupUrl(): ?string
    {
        return $this->lookupUrl;
    }

    public function setNoResultsText(string $text): static
    {
        $this->noResultsText = $text;
        return $this;
    }

    /** The value of the laf_lookup parameter this element answers: "table.field" */
    public function getLookupKey(): string
    {
        $field = $this->getField();
        if ($field === null) {
            return (string)$this->getName();
        }
        return ($field->hasTable() ? (string)$field->getTableName() . '.' : '') . (string)$field->getName();
    }

    /** Print the script inline with the first autocomplete of the request (default true) */
    public static function setInlineScript(bool $inline): void
    {
        self::$inlineScript = $inline;
    }

    /** The script file, for pages that serve it themselves (setInlineScript(false), a CSP without inline scripts) */
    public static function getScriptPath(): string
    {
        return __DIR__ . '/autocomplete.js';
    }

    public static function getScript(): string
    {
        return (string)file_get_contents(self::getScriptPath());
    }

    /** Forget that the script was printed (tests, long-running workers) */
    public static function resetRequestState(): void
    {
        self::$scriptDrawn = false;
        self::$rowCounts = [];
    }

    /**
     * Whether a foreign key gets an Autocomplete instead of a Select: Settings
     * form.select_autocomplete_threshold > 0 and the referenced table has more rows (counted once per request)
     */
    public static function suitsField(Field $field): bool
    {
        $threshold = Settings::getInt('form.select_autocomplete_threshold', 0);
        if ($threshold <= 0 || !$field->hasTable() || !$field->isForeignKey()) {
            return false;
        }
        $fk = $field->getTable()->getForeignKey((string)$field->getName());
        if ($fk === null) {
            return false;
        }
        $table = (string)$fk->getReferencingTable();
        if (!isset(self::$rowCounts[$table])) {
            try {
                self::$rowCounts[$table] = (int)Db::getInstance()->getOne('SELECT COUNT(*) FROM ' . \Laf\Database\Identifier::quote($table));
            } catch (\Throwable $ex) {
                self::$rowCounts[$table] = 0;
            }
        }
        return self::$rowCounts[$table] > $threshold;
    }

    /* ───────────── search ───────────── */

    /**
     * The matches for a search term, in display order
     * @return array{results: array<int, array{value: string, label: string}>, more: bool}
     */
    public function search(string $term, ?int $limit = null): array
    {
        $term = trim(mb_substr($term, 0, self::MAX_TERM_LENGTH));
        $limit = $limit === null ? $this->limit : max(1, min(100, $limit));
        if ($this->lookupCallback !== null) {
            $rows = self::normalizeResults((array)($this->lookupCallback)($term, $limit + 1, $this));
        } elseif ($this->hasStaticOptions()) {
            $rows = [];
            foreach ((array)$this->getStaticOptions() as $value => $label) {
                if ($term === '' || mb_stripos((string)$label, $term) !== false) {
                    $rows[] = ['value' => (string)$value, 'label' => (string)$label];
                }
            }
        } else {
            $rows = $this->searchReferencedTable($term, $limit + 1);
        }
        $more = count($rows) > $limit;
        return ['results' => array_slice($rows, 0, $limit), 'more' => $more];
    }

    /**
     * @param array<int|string, mixed> $rows
     * @return array<int, array{value: string, label: string}>
     */
    private static function normalizeResults(array $rows): array
    {
        $results = [];
        foreach ($rows as $key => $row) {
            if (is_array($row) && array_key_exists('value', $row)) {
                $results[] = ['value' => (string)$row['value'], 'label' => (string)($row['label'] ?? $row['value'])];
            } elseif (is_scalar($row) || $row === null) {
                $results[] = ['value' => (string)$key, 'label' => (string)$row];
            }
        }
        return $results;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function searchReferencedTable(string $term, int $limit): array
    {
        [$meta, $key, $display] = $this->referencedColumns();
        if ($meta === null) {
            return [];
        }
        $query = new Query($meta);
        $this->applySelectionCriteria($query, $meta);
        if ($term !== '') {
            $columns = array_values(array_filter($this->searchColumns, fn(string $c) => $meta->hasField($c)));
            if ($columns === []) {
                $columns = [$display];
            }
            $query->whereGroup(function (Query $q) use ($columns, $term) {
                foreach ($columns as $column) {
                    $q->whereContains($column, $term, 'OR');
                }
            });
        }
        $results = [];
        foreach ($query->orderBy($display)->limit($limit)->select([$key, $display])->arrays() as $row) {
            $results[] = ['value' => (string)static::valueToString($row[$key] ?? null), 'label' => (string)static::valueToString($row[$display] ?? null)];
        }
        return $results;
    }

    /**
     * The referenced table, its key column and its display column, or nulls without a usable foreign key
     * @return array{0: ?Table, 1: string, 2: string}
     */
    private function referencedColumns(): array
    {
        $field = $this->getField();
        if ($field === null || !$field->hasTable()) {
            return [null, '', ''];
        }
        $fk = $field->getTable()->getForeignKey((string)$field->getName());
        $meta = $fk !== null ? $fk->getReferencedTableMeta() : null;
        $displayField = $meta !== null ? $meta->getDisplayField() : null;
        if ($fk === null || $meta === null || $displayField === null) {
            return [null, '', ''];
        }
        return [$meta, $fk->resolveReferencedFieldName(), (string)$displayField->getName()];
    }

    private function applySelectionCriteria(Query $query, Table $meta): void
    {
        $field = $this->getField();
        if ($field === null || !$field->hasDbSelectionCriteria()) {
            return;
        }
        foreach ($field->getDbSelectionCriteria() as $column => $value) {
            if ($meta->hasField((string)$column)) {
                $query->where((string)$column, $value);
            }
        }
    }

    /* ───────────── labels ───────────── */

    /** The options of a <select> are never loaded for an autocomplete: only setOptions() lists */
    public function getOptions(): array
    {
        return $this->hasStaticOptions() ? (array)$this->getStaticOptions() : [];
    }

    /** Label of a stored value: the static option, the label callback or one row of the referenced table */
    protected function labelFor(string $value): string
    {
        if ($this->hasStaticOptions()) {
            return parent::labelFor($value);
        }
        if ($this->labelCallback !== null) {
            $label = ($this->labelCallback)($value, $this);
            return $label === null ? $value : (string)$label;
        }
        $field = $this->getField();
        if ($field !== null && $field->hasTable() && $field->isForeignKey() && static::valueToString($field->getValue()) === $value) {
            $label = static::valueToString($field->getReferencedValue());
            return $label === null ? $value : $label;
        }
        [$meta, $key, $display] = $this->referencedColumns();
        if ($meta === null) {
            return $value;
        }
        $query = new Query($meta);
        $label = $query->where($key, $value)->limit(1)->column($display);
        return isset($label[0]) ? (string)static::valueToString($label[0]) : $value;
    }

    /* ───────────── markup ───────────── */

    public function drawUpdateMode(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $id = (string)$this->getId();
        $name = (string)$this->getName();
        $value = (string)($this->getValue() ?? '');
        $label = $value === '' ? '' : $this->getSelectedLabel();

        $attributes = $this->controlAttributes(['name', 'value', 'type', 'multiple', 'size', 'list']);
        $attributes['autocomplete'] = 'off';
        $attributes['role'] = 'combobox';
        $attributes['aria-autocomplete'] = 'list';
        $attributes['aria-expanded'] = 'false';
        $attributes['aria-controls'] = $id . '_listbox';

        $hidden = "<input type=\"hidden\" class=\"laf-autocomplete-value\" id=\"" . Util::attr($id . '_value') . "\" name=\"" . Util::attr($name) . "\" value=\"" . Util::attr($value) . "\""
            . ($this->isDisabled() ? ' disabled="disabled"' : '') . " />";
        $input = "<input type=\"text\" class=\"" . Util::attr(join(' ', array_merge($this->controlClasses('form-control'), ['laf-autocomplete-input']))) . "\" style=\"" . Util::attr($this->controlStyle()) . "\""
            . " value=\"" . Util::attr($label) . "\""
            . $this->renderAttributes($attributes)
            . $this->describedBy() . " />";
        $menu = "<div class=\"dropdown-menu w-100 laf-autocomplete-menu\" id=\"" . Util::attr($id . '_listbox') . "\" role=\"listbox\" style=\"max-height:20rem;overflow-y:auto\"></div>";

        $data = [
            'data-laf-autocomplete' => $this->getLookupKey(),
            'data-min-chars' => (string)$this->minChars,
            'data-url' => $this->lookupUrl,
            'data-no-results' => $this->noResultsText,
        ];
        $box = "<div class=\"laf-autocomplete position-relative\"" . $this->renderAttributes($data) . ">"
            . "\n\t\t\t\t" . $hidden . "\n\t\t\t\t" . $input . "\n\t\t\t\t" . $menu . "\n\t\t\t\t</div>";

        return $this->renderWrapper($box, ['suggestions' => false]) . $this->drawScriptOnce();
    }

    private function drawScriptOnce(): string
    {
        if (!self::$inlineScript || self::$scriptDrawn) {
            return '';
        }
        self::$scriptDrawn = true;
        return "\n<script>\n" . self::getScript() . "\n</script>\n";
    }
}
