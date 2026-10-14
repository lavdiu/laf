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

    /** @var array<string, array<string, mixed>> linker table => many-to-many definition (addManyToMany()) */
    private array $manyToMany = [];

    /** @var ManyToManyBuilder[] belongsToMany() declarations not resolved yet (resolved on the first read) */
    private array $pendingManyToMany = [];

    /** @var array<string, string[]> index name => columns of a unique key over several columns (addUniqueKey()) */
    private array $uniqueKeys = [];

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
     * A linker (junction) table that connects this table to another one, so a row of this
     * table can be linked to several rows of the other (person ⇄ group through person_group).
     * The generator adds one per linker table it finds; forms show it as a multi-value picker.
     *
     *   $table->addManyToMany('person_group', ['owner' => 'person_id', 'other' => 'group_id',
     *       'table' => 'group', 'label' => 'Groups', 'form' => ['element' => 'Autocomplete']]);
     *
     * @param string $junction the linker table, also the relation's name on forms and layouts
     * @param array<string, mixed> $definition owner (linker column pointing here), other (linker column
     *        pointing at the other table), table (the other table), label, hint, required, form (layout
     *        hints like a column comment's "form": order, group, tab, exclude, readonly, hidden, element, create)
     */
    public function addManyToMany(string $junction, array $definition): static
    {
        foreach (['owner', 'other'] as $key) {
            if (!isset($definition[$key]) || !is_string($definition[$key]) || $definition[$key] === '') {
                throw new \InvalidArgumentException(sprintf('Table %s: the many-to-many definition of %s needs "%s"', $this->name, $junction, $key));
            }
        }
        $definition['form'] = isset($definition['form']) && is_array($definition['form']) ? $definition['form'] : [];
        // declarations made before this call come first, so a definition added later (a model class after the
        // generated Base class) replaces theirs
        $this->resolvePendingManyToMany();
        $this->manyToMany[$junction] = $definition;
        return $this;
    }

    /**
     * A belongsToMany() declaration, resolved (linker table, columns, defaults) when the relations are
     * first read, so a model may declare it in any order and refine a generated one
     */
    public function addManyToManyBuilder(ManyToManyBuilder $builder): static
    {
        $this->pendingManyToMany[] = $builder->setTable($this);
        return $this;
    }

    public function removeManyToMany(string $junction): static
    {
        $this->resolvePendingManyToMany();
        unset($this->manyToMany[$junction]);
        return $this;
    }

    public function hasManyToMany(string $junction): bool
    {
        $this->resolvePendingManyToMany();
        return isset($this->manyToMany[$junction]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getManyToManyDefinition(string $junction): ?array
    {
        $this->resolvePendingManyToMany();
        return $this->manyToMany[$junction] ?? null;
    }

    /**
     * @param bool $resolvePending false = only the definitions resolved so far (used while resolving)
     * @return array<string, array<string, mixed>> linker table => definition
     */
    public function getManyToManyDefinitions(bool $resolvePending = true): array
    {
        if ($resolvePending) {
            $this->resolvePendingManyToMany();
        }
        return $this->manyToMany;
    }

    /**
     * Turns the belongsToMany() declarations into definitions, in declaration order
     * @throws \LogicException when a declaration names no usable linker table
     */
    private function resolvePendingManyToMany(): void
    {
        while ($this->pendingManyToMany !== []) {
            $builder = array_shift($this->pendingManyToMany);
            [$junction, $definition] = $builder->resolve($this);
            $this->manyToMany[$junction] = $definition;
        }
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

    /**
     * A unique index over several columns (UNIQUE (first_name, last_name)): the combination of
     * values must not repeat. The generator adds one per such index; single-column unique
     * indexes stay on the field (Field::setUnique(), addUniqueField()). Checked before every
     * write like a unique field, and forms show a duplicate as a message on the first column.
     * @param string $name the index (constraint) name, used to recognise the database's own error
     * @param string[] $fieldNames the columns in index order
     */
    public function addUniqueKey(string $name, array $fieldNames): static
    {
        $fieldNames = array_values(array_unique(array_map('strval', $fieldNames)));
        if ($name === '' || $fieldNames === []) {
            throw new \InvalidArgumentException(sprintf('Table %s: a unique key needs a name and at least one column', $this->name));
        }
        $this->uniqueKeys[$name] = $fieldNames;
        return $this;
    }

    public function removeUniqueKey(string $name): static
    {
        unset($this->uniqueKeys[$name]);
        return $this;
    }

    public function hasUniqueKey(string $name): bool
    {
        return isset($this->uniqueKeys[$name]);
    }

    /**
     * @return array<string, string[]> index name => columns (only the keys added with addUniqueKey())
     */
    public function getUniqueKeys(): array
    {
        return $this->uniqueKeys;
    }


}