<?php

namespace Laf\Database;

use Laf\Util\Util;
use Laf\Database\Field\Field;

/**
 * Metadata of one database table: its fields, primary key, foreign keys, unique fields and
 * the display field (the column that stands for a row in selects and links).
 *
 * A generated Base class builds one in its constructor; BaseObject::getTable() hands it out.
 * Cloning deep-copies the fields so two objects never share state.
 */
class Table
{
    private ?string $name;

    /** @var array<string, Field> keyed by field name */
    private array $fields;

    private ?PrimaryKey $primaryKey;

    /** @var array<string, ForeignKey> keyed by the FK column name */
    private array $foreignKey;

    /** @var Field[] */
    private array $uniqueFields = [];

    private ?Field $displayField;

    /**
     * Table constructor.
     * @param string|null $name
     * @param Field[]|null $fields
     * @param PrimaryKey|null $primaryKey
     * @param ForeignKey[]|null $foreignKey
     * @param Field|null $displayField
     */
    public function __construct(?string $name = null, ?array $fields = [], ?PrimaryKey $primaryKey = null, ?array $foreignKey = [], ?Field $displayField = null)
    {
        $this->name = $name;
        $this->fields = $fields ?? [];
        $this->primaryKey = $primaryKey;
        $this->foreignKey = $foreignKey ?? [];
        $this->displayField = $displayField;
    }

    /**
     * Set Table Name
     * @param string|null $name
     * @return static
     */
    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Table name
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * The name a form uses as its id and marker input for this table. Despite its name it is
     * the plain table name: forms, page scripts and the request contract depend on that, so it stays.
     */
    public function getNameRot13(): string
    {
        return (string)$this->getName();
    }

    /** `school_instructor` gives `SchoolInstructor` */
    public function getNameAsClassname(): string
    {
        return Util::tableNameToClassName((string)$this->getName());
    }

    /**
     * Add Field to the table
     * @param Field $field
     * @return static
     */
    public function addField(Field $field): static
    {
        $this->setField($field);
        return $this;
    }

    /**
     * Set a specific field in the table
     * @param Field $field
     * @return static
     */
    public function setField(Field $field): static
    {
        $field->setTable($this);
        $this->fields[$field->getName()] = $field;
        return $this;
    }

    /**
     * Get table field by name
     * @param string $fieldName
     * @return Field|null
     */
    public function getField(?string $fieldName): ?Field
    {
        if ($fieldName === null) {
            return null;
        }
        return $this->fields[$fieldName] ?? null;
    }

    /**
     * Check if field exists in the table
     * @param string $fieldName
     * @return bool
     */
    public function hasField(string $fieldName): bool
    {
        return isset($this->fields[$fieldName]);
    }

    /**
     * Get all table fields
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Returns number of fields per table
     * @return int
     */
    public function getFieldCount(): int
    {
        return count($this->fields);
    }

    /**
     * Set primary key
     * @param PrimaryKey $key
     * @return static
     */
    public function setPrimaryKey(PrimaryKey $key): static
    {
        $key->setTable($this);
        $this->primaryKey = $key;
        return $this;
    }

    /**
     * The primary key, null when none was set
     */
    public function getPrimaryKey(): ?PrimaryKey
    {
        return $this->primaryKey;
    }

    /**
     * Return number of primary keys per table
     * @return int
     */
    public function getPrimaryKeyCount(): int
    {
        $primaryKey = $this->getPrimaryKey();
        return $primaryKey === null ? 0 : $primaryKey->getFieldCount();
    }

    /**
     * Check by fieldname is a field is primary key
     * @param string $fieldName
     * @return bool
     */
    public function isPrimaryKey(string $fieldName): bool
    {
        $primaryKey = $this->getPrimaryKey();
        return $primaryKey !== null && $primaryKey->hasField($fieldName);
    }

    /**
     * Add Table Foreign Key
     * @param ForeignKey $key
     * @return static
     */
    public function addForeignKey(ForeignKey $key): static
    {
        $key->setTable($this);
        $this->foreignKey[(string)$key->getField()->getName()] = $key;
        return $this;
    }

    /**
     * Get table foreign key
     * @param string $name
     * @return ForeignKey|null
     */
    public function getForeignKey(string $name): ?ForeignKey
    {
        return $this->foreignKey[$name] ?? null;
    }

    /**
     * Check if a field is a foreign key
     * @param string $name
     * @return bool
     */
    public function isForeignKey(string $name): bool
    {
        return isset($this->foreignKey[$name]);
    }

    /**
     * Get all foreign keys
     * @return array<string, ForeignKey> keyed by the FK column name
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKey;
    }

    /**
     * Set display field name
     * @param Field $displayField
     * @return static
     */
    public function setDisplayField(?Field $displayField): static
    {
        $this->displayField = $displayField;
        return $this;
    }

    /**
     * Returns the instance of displayField
     * @return Field
     */
    public function getDisplayField(): ?Field
    {
        if (is_object($this->displayField))
            return $this->displayField;
        else {
            $keys = array_keys($this->fields);
            if ($this->hasField('name')) {
                return $this->getField('name');
            } else if ($this->hasField('label')) {
                return $this->getField('label');
            } else if ($this->hasField('description')) {
                return $this->getField('description');
            } else if (array_key_exists(1, $keys))
                return $this->fields[$keys[1]];
            else if (array_key_exists(0, $keys))
                return $this->fields[$keys[0]];
        }
        return null;
    }

    public function __toString(): string
    {
        return (string)$this->getName();
    }

    /**
     * Deep copy: fields, primary key, foreign keys, unique and display fields
     * all point at the new Field instances, so two objects never share state.
     */
    public function __clone(): void
    {
        $fields = [];
        foreach ($this->fields as $name => $field) {
            $copy = clone $field;
            $copy->setTable($this);
            $fields[$name] = $copy;
        }
        $this->fields = $fields;

        if ($this->primaryKey instanceof PrimaryKey) {
            $pk = new PrimaryKey();
            foreach ($this->primaryKey->getFields() as $pkField) {
                $pk->addField($this->fields[$pkField->getName()] ?? clone $pkField);
            }
            $pk->setTable($this);
            $this->primaryKey = $pk;
        }

        $foreignKeys = [];
        foreach ($this->foreignKey as $name => $fk) {
            $copy = clone $fk;
            $copy->setTable($this);
            if (isset($this->fields[$name])) {
                $copy->setField($this->fields[$name]);
            }
            $foreignKeys[$name] = $copy;
        }
        $this->foreignKey = $foreignKeys;

        $this->uniqueFields = array_map(fn(Field $f) => $this->fields[$f->getName()] ?? $f, $this->uniqueFields);
        if ($this->displayField instanceof Field) {
            $this->displayField = $this->fields[$this->displayField->getName()] ?? $this->displayField;
        }
    }

    /**
     * @return bool
     */
    public function hasUniqueFields(): bool
    {
        return count($this->getUniqueFields()) > 0;
    }

    /**
     * @return Field[]
     */
    public function getUniqueFields(): array
    {
        return $this->uniqueFields;
    }

    /**
     * @param Field[] $uniqueFields
     * @return static
     */
    public function setUniqueFields(array $uniqueFields): static
    {
        $this->uniqueFields = $uniqueFields;
        return $this;
    }

    /**
     * @param Field $field
     * @return static
     */
    public function addUniqueField(Field $field): static
    {
        $this->uniqueFields[] = $field;
        return $this;
    }


}