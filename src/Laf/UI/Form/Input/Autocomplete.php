<?php

declare(strict_types=1);

namespace Laf\UI\Form\Input;

use Laf\Database\BaseObject;
use Laf\Database\Db;
use Laf\Database\Field\Field;
use Laf\Database\Query;
use Laf\Database\Table;
use Laf\Exception\DatabaseException;
use Laf\Exception\InvalidForeignKeyValue;
use Laf\Exception\InvalidValueException;
use Laf\Exception\MissingFieldValueException;
use Laf\Exception\UniqueFieldDuplicateValueException;
use Laf\Exception\ValidationException;
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
 *
 * setAllowCreate(true) (column comment {"form":{"create":true}}) ends the list with
 * '+ Create "typed text"' when nothing matches exactly: the script POSTs the text with the form's
 * CSRF token to the same lookup URL and the new row is chosen. The default stores a row of the
 * referenced table with the display field set to the text (and the field's db selection criteria);
 * setCreateCallback() takes over for tables with other required columns or rules.
 *
 * Always one value: a foreign key column holds one key. Several values come from a linker table
 * (ManyToManyAutocomplete).
 */
class Autocomplete extends Select implements FormElementInterface
{
    public const REQUEST_KEY = 'laf_lookup';
    public const REQUEST_TERM = 'laf_q';
    public const REQUEST_CREATE = 'laf_create';

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

    private bool $allowCreate = false;

    private string $createText = 'Create "%s"';

    /** @var callable|null fn(string $term, Autocomplete $element): BaseObject|array|string|null */
    private $createCallback = null;

    /** @var array{0: string, 1: string}|null CSRF input name and token the script posts a create with */
    private ?array $createCsrf = null;

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

    public function getNoResultsText(): string
    {
        return $this->noResultsText;
    }

    /**
     * Offer '+ Create "text"' when the typed text matches no row exactly (default false)
     */
    public function setAllowCreate(bool $allow): static
    {
        $this->allowCreate = $allow;
        return $this;
    }

    public function isCreateAllowed(): bool
    {
        return $this->allowCreate || $this->createCallback !== null;
    }

    /** Text of the create entry; %s is the typed text */
    public function setCreateText(string $text): static
    {
        $this->createText = $text;
        return $this;
    }

    public function getCreateText(): string
    {
        return $this->createText;
    }

    /**
     * Builds the row for a typed text instead of the default (implies setAllowCreate(true)):
     * fn(string $term, Autocomplete $el) returns the stored BaseObject, ['value' => ..., 'label' => ...],
     * or a string = the reason it was refused (shown in the list); null refuses without a reason.
     */
    public function setCreateCallback(?callable $callback): static
    {
        $this->createCallback = $callback;
        return $this;
    }

    /**
     * The CSRF input name and token the script sends with a create (Form sets them when it draws)
     */
    public function setCreateCsrfToken(?string $inputName, ?string $token): static
    {
        $this->createCsrf = ($inputName === null || $token === null) ? null : [$inputName, $token];
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
     * The table searched, its key column and its display column, or nulls without a usable foreign key
     * @return array{0: ?Table, 1: string, 2: string}
     */
    protected function referencedColumns(): array
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
        foreach ($this->selectionCriteria() as $column => $value) {
            if ($meta->hasField((string)$column)) {
                $query->where((string)$column, $value);
            }
        }
    }

    /* ───────────── create ───────────── */

    /**
     * Store a row for a typed text, or find the row that already carries it
     * @return array{value: string, label: string, created: bool}
     * @throws InvalidValueException when the row is refused (the message says why, for the user)
     */
    public function create(string $term): array
    {
        $term = trim(mb_substr($term, 0, self::MAX_TERM_LENGTH));
        if ($term === '') {
            throw new InvalidValueException('Type a name first');
        }
        if ($this->createCallback !== null) {
            return $this->createResult(($this->createCallback)($term, $this), $term);
        }

        [$meta, $key, $display] = $this->referencedColumns();
        $class = $this->createClass();
        if ($meta === null || $class === null || !class_exists($class)) {
            throw new InvalidValueException('New entries cannot be added here');
        }
        foreach ($this->searchReferencedTable($term, 100) as $row) {
            if (mb_strtolower($row['label']) === mb_strtolower($term)) {
                return ['value' => $row['value'], 'label' => $row['label'], 'created' => false];
            }
        }

        /** @var BaseObject $object */
        $object = new $class();
        try {
            $object->setFieldValue($display, $term);
            foreach ($this->selectionCriteria() as $column => $value) {
                if ($meta->hasField((string)$column)) {
                    $object->setFieldValue((string)$column, $value);
                }
            }
            $object->setValidateOnStore(true);
            $object->insert();
        } catch (MissingFieldValueException $ex) {
            throw new InvalidValueException(sprintf('"%s" cannot be added here: more details are required', $term), 0, $ex);
        } catch (UniqueFieldDuplicateValueException $ex) {
            throw new InvalidValueException(sprintf('"%s" already exists', $term), 0, $ex);
        } catch (ValidationException $ex) {
            throw new InvalidValueException(implode(' ', $ex->getMessages()) ?: sprintf('"%s" is not valid', $term), 0, $ex);
        } catch (DatabaseException | InvalidForeignKeyValue $ex) {
            throw new InvalidValueException(sprintf('"%s" could not be added', $term), 0, $ex);
        }
        return $this->createResult($object, $term);
    }

    /**
     * The model class a created row belongs to: the referenced table's model
     */
    protected function createClass(): ?string
    {
        $field = $this->getField();
        if ($field === null || !$field->hasTable()) {
            return null;
        }
        $fk = $field->getTable()->getForeignKey((string)$field->getName());
        return $fk !== null ? $fk->getReferencedClassName() : null;
    }

    /**
     * @return array<string, mixed> the field's db selection criteria (column => value)
     */
    protected function selectionCriteria(): array
    {
        $field = $this->getField();
        return $field !== null && $field->hasDbSelectionCriteria() ? (array)$field->getDbSelectionCriteria() : [];
    }

    /**
     * @param mixed $result what the create callback or the default produced
     * @return array{value: string, label: string, created: bool}
     * @throws InvalidValueException
     */
    private function createResult(mixed $result, string $term): array
    {
        if ($result instanceof BaseObject) {
            [, $key, $display] = $this->referencedColumns();
            $value = $key !== '' && $result->getTable()->hasField($key) ? $result->getFieldValue($key) : $result->getRecordId();
            $label = $display !== '' && $result->getTable()->hasField($display) ? $result->getFieldValue($display) : $term;
            $value = static::valueToString(is_array($value) ? null : $value);
            if ($value === null || $value === '') {
                throw new InvalidValueException(sprintf('"%s" could not be added', $term));
            }
            return ['value' => $value, 'label' => (string)(static::valueToString($label) ?? $term), 'created' => true];
        }
        if (is_array($result) && array_key_exists('value', $result) && is_scalar($result['value']) && (string)$result['value'] !== '') {
            return ['value' => (string)$result['value'], 'label' => (string)($result['label'] ?? $term), 'created' => (bool)($result['created'] ?? true)];
        }
        if (is_string($result) && $result !== '') {
            throw new InvalidValueException($result);
        }
        throw new InvalidValueException(sprintf('"%s" cannot be added here', $term));
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
        ] + $this->createAttributes();
        $box = "<div class=\"laf-autocomplete position-relative\"" . $this->renderAttributes($data) . ">"
            . "\n\t\t\t\t" . $hidden . "\n\t\t\t\t" . $input . "\n\t\t\t\t" . $menu . "\n\t\t\t\t</div>";

        return $this->renderWrapper($box, ['suggestions' => false]) . $this->drawScriptOnce();
    }

    /**
     * data-* attributes of the create entry (none when creating is off)
     * @return array<string, string>
     */
    protected function createAttributes(): array
    {
        if (!$this->isCreateAllowed()) {
            return [];
        }
        $attributes = ['data-create' => '1', 'data-create-text' => $this->createText];
        if ($this->createCsrf !== null) {
            $attributes['data-csrf-name'] = $this->createCsrf[0];
            $attributes['data-csrf-token'] = $this->createCsrf[1];
        }
        return $attributes;
    }

    protected function drawScriptOnce(): string
    {
        if (!self::$inlineScript || self::$scriptDrawn) {
            return '';
        }
        self::$scriptDrawn = true;
        return "\n<script>\n" . self::getScript() . "\n</script>\n";
    }
}
