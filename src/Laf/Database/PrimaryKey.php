<?php

namespace Laf\Database;

use Laf\Database\Field\Field;

/**
 * The primary key of a table: one or more fields.
 *
 * Single-column keys are the common case and every legacy call site
 * (getFirstField()) keeps working. Composite keys get getWhereClause() /
 * getBindings() so BaseObject can address exactly one row.
 */
class PrimaryKey
{
    /** @var Field[] */
    private $fields = [];

    /** @var Table|null */
    private $table = null;

    /**
     * @param Field[]|null $fields
     * @param Table|null $table (older code passed a string here; it is ignored)
     */
    public function __construct(?array $fields = [], $table = null)
    {
        $this->fields = $fields ?? [];
        if ($table instanceof Table) {
            $this->table = $table;
        }
    }

    public function addField(Field $field): PrimaryKey
    {
        $this->fields[] = $field;
        return $this;
    }

    /** @return Field[] */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getFirstField(): ?Field
    {
        return $this->fields[0] ?? null;
    }

    public function hasMoreThanOneField(): bool
    {
        return count($this->fields) > 1;
    }

    /** Alias of hasMoreThanOneField() with the usual name. */
    public function isComposite(): bool
    {
        return $this->hasMoreThanOneField();
    }

    public function getFieldCount(): int
    {
        return count($this->fields);
    }

    public function hasField(string $fieldName): bool
    {
        foreach ($this->fields as $f) {
            if ($f->getName() == $fieldName) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] column names in key order */
    public function getFieldNames(): array
    {
        return array_map(fn(Field $f) => $f->getName(), $this->fields);
    }

    /** True when the (first) key column is auto-increment / serial. */
    public function isAutoIncrement(): bool
    {
        $first = $this->getFirstField();
        return $first !== null && $first->isAutoIncrement();
    }

    /** @return array<string, mixed> name => current value */
    public function getValues(): array
    {
        $values = [];
        foreach ($this->fields as $f) {
            $values[$f->getName()] = $f->getValue();
        }
        return $values;
    }

    /** @return array<string, mixed> name => value as loaded from the DB (falls back to the current value) */
    public function getOldValues(): array
    {
        $values = [];
        foreach ($this->fields as $f) {
            $old = $f->getOldValue();
            $values[$f->getName()] = ($old === null || $old === '') ? $f->getValue() : $old;
        }
        return $values;
    }

    /** True when every key column has a non-empty value. */
    public function hasValue(): bool
    {
        if (count($this->fields) === 0) {
            return false;
        }
        foreach ($this->fields as $f) {
            $v = $f->getValue();
            if ($v === null || $v === '' || $v === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Assign key values. A scalar goes to the first column; an array may be
     * keyed by column name or positional in key order. Values bypass FK
     * validation (setValueRaw) because a key is an address, not user input.
     * @param mixed $values
     */
    public function setValues($values): PrimaryKey
    {
        if (!is_array($values)) {
            $first = $this->getFirstField();
            if ($first !== null) {
                $first->setValueRaw($first->getType() ? $first->getType()->normalize($values) : $values);
            }
            return $this;
        }
        $positional = array_values($values);
        foreach ($this->fields as $i => $f) {
            if (array_key_exists($f->getName(), $values)) {
                $v = $values[$f->getName()];
            } else if (array_key_exists($i, $positional) && !array_key_exists($f->getName(), $values) && array_keys($values) === range(0, count($values) - 1)) {
                $v = $positional[$i];
            } else {
                continue;
            }
            $f->setValueRaw($f->getType() ? $f->getType()->normalize($v) : $v);
        }
        return $this;
    }

    /**
     * "`a` = :pk_a AND `b` = :pk_b" with engine-appropriate quoting.
     */
    public function getWhereClause(string $placeholderPrefix = 'pk_', ?string $engine = null): string
    {
        $parts = [];
        foreach ($this->fields as $f) {
            $parts[] = Identifier::quote($f->getName(), $engine) . ' = :' . $placeholderPrefix . Identifier::sanitize($f->getName());
        }
        return implode(' AND ', $parts);
    }

    /**
     * Bindings for getWhereClause(): [':pk_a' => [value, PDO::PARAM_*], ...].
     * Uses the values the row was loaded with (so a changed key column still
     * addresses the original row), falling back to the current values.
     * @return array<string, array{0: mixed, 1: int}>
     */
    public function getBindings(string $placeholderPrefix = 'pk_', bool $useOldValues = true): array
    {
        $bindings = [];
        $values = $useOldValues ? $this->getOldValues() : $this->getValues();
        foreach ($this->fields as $f) {
            $value = $values[$f->getName()] ?? null;
            $type = $f->getType();
            $bindings[':' . $placeholderPrefix . Identifier::sanitize($f->getName())] = $type ? $type->toDb($value) : [$value, \PDO::PARAM_STR];
        }
        return $bindings;
    }

    public function getTable(): ?Table
    {
        return $this->table;
    }

    public function setTable(Table $table): void
    {
        $this->table = $table;
    }
}
