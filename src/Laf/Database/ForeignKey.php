<?php

namespace Laf\Database;

use Laf\Database\Field\Field;
use Laf\Database\Field\FieldType;
use Laf\Exception\InvalidValueException;
use Laf\Util\Settings;
use Laf\Util\Util;

/**
 * One foreign-key column: which column of this table points at which column of
 * which other table.
 *
 * Naming note: the original API calls the *referenced* (parent) table the
 * "referencing" table. Those methods are kept because generated Base classes
 * call them; the correctly named getReferencedTable()/setReferencedTable()
 * and getReferencedField()/setReferencedField() aliases are provided for new code.
 */
class ForeignKey
{
    /** constraint name */
    private ?string $keyName;

    /** table that owns the FK column */
    private ?Table $table;

    /** the FK column */
    private ?Field $field;

    /** the referenced (parent) table name */
    private ?string $referencingTable;

    /** the referenced column; usually the parent's primary key */
    private ?string $referencingField;

    /** ON DELETE rule as reported by information_schema (CASCADE, SET NULL, RESTRICT, NO ACTION) */
    private ?string $onDelete = null;

    /** ON UPDATE rule */
    private ?string $onUpdate = null;

    /**
     * Per-request memo of positive validations: [table][column][value] => true.
     * Only positives are cached: a value that does not exist now may be inserted
     * later in the same request, so negatives are always re-checked.
     * @var array<string, array<string, array<string, bool>>>
     */
    private static array $validationCache = [];

    /** @var array<string, Table> referenced-table metadata per table name */
    private static array $metaCache = [];

    /**
     * @param string|null $keyName constraint name
     * @param Table|null $table owning table
     * @param Field|null $field FK column
     * @param string|null $referencingTable referenced (parent) table
     * @param string|null $referencingField referenced column
     */
    public function __construct(?string $keyName = null, ?Table $table = null, ?Field $field = null, ?string $referencingTable = null, ?string $referencingField = null)
    {
        $this->keyName = $keyName;
        $this->table = $table;
        $this->field = $field;
        $this->referencingTable = $referencingTable;
        $this->referencingField = $referencingField;
    }

    /* ───────────── legacy API (used by generated code) ───────────── */

    public function getKeyName(): ?string
    {
        return $this->keyName;
    }

    public function setKeyName(?string $keyName): static
    {
        $this->keyName = $keyName;
        return $this;
    }

    public function getTable(): ?Table
    {
        return $this->table;
    }

    public function setTable(Table $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function getField(): ?Field
    {
        return $this->field;
    }

    public function setField(Field $field): static
    {
        $this->field = $field;
        return $this;
    }

    /** The referenced (parent) table name */
    public function getReferencingTable(): ?string
    {
        return $this->referencingTable;
    }

    public function setReferencingTable(string $referencingTable): static
    {
        $this->referencingTable = $referencingTable;
        return $this;
    }

    /** The referenced column name */
    public function getReferencingField(): ?string
    {
        return $this->referencingField;
    }

    public function setReferencingField(?string $referencingField): static
    {
        $this->referencingField = $referencingField;
        return $this;
    }

    /* ───────────── correctly named aliases ───────────── */

    public function getReferencedTable(): ?string
    {
        return $this->referencingTable;
    }

    public function setReferencedTable(string $table): static
    {
        return $this->setReferencingTable($table);
    }

    public function getReferencedField(): ?string
    {
        return $this->referencingField;
    }

    public function setReferencedField(?string $field): static
    {
        return $this->setReferencingField($field);
    }

    /* ───────────── referential rules (optional metadata) ───────────── */

    public function getOnDelete(): ?string
    {
        return $this->onDelete;
    }

    public function setOnDelete(?string $rule): static
    {
        $this->onDelete = $rule === null ? null : strtoupper(trim($rule));
        return $this;
    }

    public function getOnUpdate(): ?string
    {
        return $this->onUpdate;
    }

    public function setOnUpdate(?string $rule): static
    {
        $this->onUpdate = $rule === null ? null : strtoupper(trim($rule));
        return $this;
    }

    public function cascadesOnDelete(): bool
    {
        return $this->onDelete === 'CASCADE';
    }

    /** True when the FK column may hold NULL (no parent). */
    public function isNullable(): bool
    {
        return $this->field === null || !$this->field->isRequired();
    }

    /* ───────────── referenced-class helpers (one implementation for Field, Select, Radio) ───────────── */

    /**
     * Fully qualified class of the referenced table's model, with a leading backslash.
     * Built from Settings 'project.package_name' and the table name.
     */
    public function getReferencedClassName(): string
    {
        return '\\' . Settings::get('project.package_name') . '\\' . Util::tableNameToClassName((string)$this->referencingTable);
    }

    public function referencedClassExists(): bool
    {
        try {
            return $this->referencingTable !== null && class_exists($this->getReferencedClassName());
        } catch (\Throwable $ex) {
            return false;
        }
    }

    /**
     * The referenced row as its model object.
     * @param mixed $id defaults to this FK column's current value
     * @return BaseObject|null null when the model class does not exist
     */
    public function getReferencedObject($id = null): ?BaseObject
    {
        if (!$this->referencedClassExists()) {
            return null;
        }
        if ($id === null && $this->field !== null) {
            $id = $this->field->getValue();
        }
        $class = $this->getReferencedClassName();
        return new $class($id);
    }

    /**
     * Metadata (fields, PK, display field) of the referenced table, without loading a row.
     */
    public function getReferencedTableMeta(): ?Table
    {
        if (!$this->referencedClassExists()) {
            return null;
        }
        $name = (string)$this->referencingTable;
        if (!isset(self::$metaCache[$name])) {
            $class = $this->getReferencedClassName();
            self::$metaCache[$name] = (new $class())->getTable();
        }
        return self::$metaCache[$name];
    }

    /**
     * Name of the referenced column: the declared one, else the referenced table's first PK column, else 'id'.
     */
    public function resolveReferencedFieldName(): string
    {
        if ($this->referencingField !== null && $this->referencingField !== '') {
            return $this->referencingField;
        }
        $meta = $this->getReferencedTableMeta();
        $pk = $meta ? $meta->getPrimaryKey() : null;
        if ($pk !== null && $pk->getFirstField() !== null) {
            return $pk->getFirstField()->getName();
        }
        return 'id';
    }

    /** Type of the referenced column, when the referenced model is available. */
    public function getReferencedFieldType(): ?FieldType
    {
        $meta = $this->getReferencedTableMeta();
        if ($meta === null) {
            return null;
        }
        $field = $meta->getField($this->resolveReferencedFieldName());
        return $field ? $field->getType() : null;
    }

    /* ───────────── validation ───────────── */

    /**
     * Does a row with this key exist in the referenced table?
     * Empty values are valid (NULL foreign key). Works on MySQL/MariaDB and
     * PostgreSQL, with integer, string and UUID keys; positive answers are
     * memoised for the rest of the request.
     * @param mixed $id
     */
    public function isValidValue($id): bool
    {
        if ($id === null) {
            return true;
        }
        if (is_string($id)) {
            $id = trim($id);
        }
        if ($id === '' || $id === false) {
            return true;
        }

        $table = Identifier::sanitize((string)$this->referencingTable);
        $column = Identifier::sanitize($this->resolveReferencedFieldName());
        if ($table === '' || $column === '') {
            return true;
        }

        $boundValue = $id;
        $pdoType = \PDO::PARAM_STR;
        $type = $this->getReferencedFieldType();
        if ($type !== null) {
            try {
                [$boundValue, $pdoType] = $type->toDb($type->normalize($id));
            } catch (InvalidValueException $ex) {
                return false; // cannot even be a value of the referenced column
            }
            if ($boundValue === null) {
                return true;
            }
        }

        $cacheKey = is_scalar($boundValue) ? (string)$boundValue : serialize($boundValue);
        if (isset(self::$validationCache[$table][$column][$cacheKey])) {
            return true;
        }

        $sql = 'SELECT 1 AS value_found FROM ' . Identifier::quote($table) . ' WHERE ' . Identifier::quote($column) . ' = :id';
        $stmt = Db::getInstance()->prepare($sql);
        $stmt->bindValue(':id', $boundValue, $pdoType);
        $stmt->execute();
        $found = $stmt->fetchColumn() !== false;
        if ($found) {
            self::$validationCache[$table][$column][$cacheKey] = true;
        }
        return $found;
    }

    /** Forget memoised validations (BaseObject::hardDelete() calls this). */
    public static function clearValidationCache(?string $table = null): void
    {
        if ($table === null) {
            self::$validationCache = [];
        } else {
            unset(self::$validationCache[Identifier::sanitize($table)]);
        }
    }

    public function __toString(): string
    {
        $owner = $this->table ? $this->table->getName() : '?';
        $column = $this->field ? $this->field->getName() : '?';
        return sprintf('%s.%s -> %s.%s', $owner, $column, $this->referencingTable ?? '?', $this->referencingField ?? '?');
    }
}
