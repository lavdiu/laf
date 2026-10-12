<?php

namespace Laf\Database;

use Laf\Database\Field\Field;
use Laf\Exception\DatabaseException;
use Laf\Exception\InvalidForeignKeyValue;
use Laf\Exception\InvalidValueException;
use Laf\Exception\MissingFieldValueException;
use Laf\Exception\UniqueFieldDuplicateValueException;
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;
use Laf\UI\Form\Form;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\FormLayout;
use Laf\UI\Grid\SimpleTable;
use Laf\Util\Settings;
use Laf\Util\UrlParser;
use Laf\Util\Util;
use Psr\Log\LoggerInterface;

/**
 * Active Record base of every generated model.
 *
 * A generated Base class binds a Table (fields, keys) in its constructor and
 * loads the row when an id is given; this class holds the state (record id,
 * loaded flag) and the behaviour: load, insert, update, delete, find, audit.
 *
 * Lifecycle: new → (select) → loaded → store() = insert() or update() → loaded.
 * Hooks (beforeInsert/afterInsert/...) are the extension points for subclasses;
 * writes go through one executor so every statement is quoted for the engine,
 * bound through the field types and reported as DatabaseException.
 *
 * Signatures that app subclasses override untyped stay untyped on purpose:
 * insert(), update(), hardDelete(), returnLeafClass(), __clone().
 *
 * @phpstan-consistent-constructor every subclass takes (\$id = null), so new static() is safe
 */
class BaseObject implements \JsonSerializable
{
    public const FIELD_CREATED_ON = 'created_on';
    public const FIELD_CREATED_BY = 'created_by';
    public const FIELD_UPDATED_ON = 'updated_on';
    public const FIELD_UPDATED_BY = 'updated_by';
    public const FIELD_DELETED = 'deleted';
    public const FIELD_RECORD_STATUS_ID = 'record_status_id';

    public const DELETE_HARD = 'hard';
    public const DELETE_SOFT = 'soft';

    /** The user every write is attributed to (created_by, updated_by, audit log) */
    private static ?int $activeUserId = null;

    private ?Table $table = null;

    /** scalar key, or name => value for composite keys */
    private int|string|array|null $recordId = null;

    /** True once a row was loaded from (or written to) the database */
    private bool $recordSelected = false;

    private int $affectedRows = 0;

    private ?string $lastSql = null;

    private ?LoggerInterface $logger = null;

    private bool $auditLoggingEnabled = true;

    /**
     * @param int|string|array|null $id generated subclasses load the row when it is not empty
     */
    public function __construct($id = null)
    {
        $this->recordId = self::normalizeRecordId($id);
    }

    /* ------------------------------------------------------------------ */
    /* table and metadata                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * @throws \LogicException when no table has been bound yet
     */
    public function getTable(): Table
    {
        if ($this->table === null) {
            throw new \LogicException(static::class . ' has no table bound; the generated constructor calls setTable()');
        }
        return $this->table;
    }

    public function hasTable(): bool
    {
        return $this->table !== null;
    }

    public function setTable(Table $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Returns the field object ref, null when the table has no such column
     */
    public function getField(string $fieldName): ?Field
    {
        if ($this->getTable()->hasField($fieldName)) {
            return $this->getTable()->getField($fieldName);
        }
        $this->log('error', "Field doesn't exist", [$fieldName]);
        return null;
    }

    /**
     * Returns the lowest level class in the inheritance tree. Generated leaf
     * classes override this; the default already is the leaf thanks to late
     * static binding, so nothing recurses when a subclass forgets it.
     * @return static
     */
    protected function returnLeafClass()
    {
        return $this;
    }

    /** The primary key; an empty key when the table declares none. */
    private function primaryKey(): PrimaryKey
    {
        /** @var PrimaryKey|null $pk */
        $pk = $this->getTable()->getPrimaryKey();
        return $pk ?? new PrimaryKey([], $this->getTable());
    }

    /* ------------------------------------------------------------------ */
    /* values                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Set a field value with validation: type normalisation and, for foreign
     * keys, existence of the referenced row.
     * @param mixed $value
     * @return bool false when the table has no such field
     * @throws InvalidForeignKeyValue
     * @throws InvalidValueException
     */
    public function setFieldValue(string $fieldName, $value = null): bool
    {
        if (!$this->getTable()->hasField($fieldName)) {
            return false;
        }
        $field = $this->getTable()->getField($fieldName);
        if ($field->isForeignKey()) {
            $foreignKey = $this->getTable()->getForeignKey($fieldName);
            if ($foreignKey !== null && !$foreignKey->isValidValue($value)) {
                throw new InvalidForeignKeyValue("Foreign key not found " . $fieldName);
            }
        }
        $field->setValue($value);
        return true;
    }

    /**
     * Set several fields at once (same rules as setFieldValue(); unknown columns are ignored)
     * @param array<string, mixed> $values column => value
     * @throws InvalidForeignKeyValue
     * @throws InvalidValueException
     */
    public function setFieldValues(array $values): static
    {
        foreach ($values as $fieldName => $value) {
            $this->setFieldValue((string)$fieldName, $value);
        }
        return $this;
    }

    /**
     * Set a value without type normalisation or foreign key validation
     * @param mixed $value
     * @return bool false when the table has no such field or the value was refused
     */
    public function setFieldValueRaw(string $fieldName, $value = null): bool
    {
        if (!$this->getTable()->hasField($fieldName)) {
            return false;
        }
        try {
            $this->getTable()->getField($fieldName)->setValueRaw($value);
        } catch (\Exception $ex) {
            $this->log('error', "Error while setting Raw value", [$fieldName, $value, $ex->getMessage()]);
            return false;
        }
        return true;
    }

    /**
     * @param mixed $value
     * @return bool false when the table has no such field or the value was refused
     */
    public function setFieldValueHTML(string $fieldName, $value = null): bool
    {
        if (!$this->getTable()->hasField($fieldName)) {
            return false;
        }
        try {
            $this->getTable()->getField($fieldName)->setValueHTML($value);
        } catch (\Exception $ex) {
            $this->log('error', "Error while setting HTML value", [$fieldName, $value, $ex->getMessage()]);
            return false;
        }
        return true;
    }

    /**
     * Returns the field value, null when the table has no such field
     * @return mixed
     */
    public function getFieldValue(string $fieldName)
    {
        if ($this->getTable()->hasField($fieldName)) {
            return $this->getTable()->getField($fieldName)->getValue();
        }
        $this->log('error', "Field doesn't exist", [$fieldName]);
        return null;
    }

    /**
     * Associative array of column => value for the given fields (all when none given)
     * @param string[] $fields
     * @return array<string, mixed>
     */
    public function getFieldValuesAsArray(array $fields = []): array
    {
        $data = [];
        if (count($fields) > 0) {
            foreach ($fields as $field) {
                if ($this->getTable()->hasField($field)) {
                    $data[$field] = $this->getFieldValue($field);
                }
            }
            return $data;
        }
        foreach ($this->getTable()->getFields() as $field) {
            $data[$field->getName()] = $field->getValue();
        }
        return $data;
    }

    /**
     * Every column => value
     * @return array<string, mixed>
     */
    public function getAllFieldsAsKVArrays(): array
    {
        return $this->getFieldValuesAsArray();
    }

    /**
     * Alias of getFieldValuesAsArray()
     * @param string[] $fields
     * @return array<string, mixed>
     */
    public function toArray(array $fields = []): array
    {
        return $this->getFieldValuesAsArray($fields);
    }

    /**
     * json_encode($object) gives the column => value map
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->getFieldValuesAsArray();
    }

    /** True when any field differs from the value loaded from (or written to) the database */
    public function isDirty(): bool
    {
        foreach ($this->getTable()->getFields() as $field) {
            if ($field->hasChanged()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fields that differ from their persisted value
     * @return array<string, Field> column => field
     */
    public function getChangedFields(): array
    {
        $changed = [];
        foreach ($this->getTable()->getFields() as $field) {
            if ($field->hasChanged()) {
                $changed[$field->getName()] = $field;
            }
        }
        return $changed;
    }

    /**
     * Old and new value of every changed field
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChangedValues(): array
    {
        $changes = [];
        foreach ($this->getChangedFields() as $name => $field) {
            $changes[$name] = ['old' => $field->getOldValue(), 'new' => $field->getValue()];
        }
        return $changes;
    }

    /* ------------------------------------------------------------------ */
    /* record id and state                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * The key the object was loaded with or given at insert: a scalar, or
     * name => value for composite keys.
     * @return int|string|array|null
     */
    public function getRecordId()
    {
        return $this->recordId;
    }

    /**
     * @param mixed $recordId scalar, or name => value / positional values for composite keys
     * @return bool false when empty
     */
    public function setRecordId($recordId): bool
    {
        $normalized = self::normalizeRecordId($recordId);
        if (is_array($normalized)) {
            $this->recordId = $normalized;
            return count($normalized) > 0;
        }
        if ($normalized === null || $normalized === '') {
            return false;
        }
        $this->recordId = $normalized;
        return true;
    }

    /**
     * The primary key value(s) currently held by the fields: a scalar, or
     * name => value for composite keys.
     * @return int|string|array|null
     */
    public function getPrimaryKeyValue()
    {
        $pk = $this->primaryKey();
        if ($pk->isComposite()) {
            return $pk->getValues();
        }
        $first = $pk->getFirstField();
        return $first === null ? null : $first->getValue();
    }

    /**
     * True when the primary key holds a value (not whether that row exists in the database)
     */
    public function recordExists(): bool
    {
        $pk = $this->primaryKey();
        if ($pk->getFirstField() === null) {
            return false;
        }
        if ($pk->isComposite()) {
            return $pk->hasValue();
        }
        $value = $pk->getFirstField()->getValue();
        if (is_numeric($value)) {
            return true;
        }
        return trim((string)($value ?? '')) !== '';
    }

    /** True once a row was loaded from (or written to) the database */
    public function isRecordSelected(): bool
    {
        return $this->recordSelected;
    }

    /** Alias of isRecordSelected() */
    public function isLoaded(): bool
    {
        return $this->recordSelected;
    }

    public function setRecordSelected(bool $recordSelected): static
    {
        $this->recordSelected = $recordSelected;
        return $this;
    }

    /**
     * What store() will do: insert when the key is empty, or when a
     * non-auto-increment key was assigned but never loaded; update otherwise.
     */
    public function isNew(): bool
    {
        $first = $this->primaryKey()->getFirstField();
        if ($first === null) {
            return !$this->recordSelected;
        }
        $value = $first->getValue();
        if ($value === null || (string)$value === '') {
            return true;
        }
        if (!$first->isAutoIncrement() && (string)($first->getOldValue() ?? '') === '') {
            return true;
        }
        return false;
    }

    /** Number of rows the last insert, update or delete touched */
    public function getAffectedRows(): int
    {
        return $this->affectedRows;
    }

    /** The last statement this object ran (for debugging) */
    public function getLastSql(): ?string
    {
        return $this->lastSql;
    }

    /**
     * @param mixed $id
     * @return int|string|array|null
     */
    private static function normalizeRecordId($id)
    {
        if ($id === null || is_int($id) || is_string($id) || is_array($id)) {
            return $id;
        }
        if (is_bool($id)) {
            return (int)$id;
        }
        if (is_float($id)) {
            return (string)$id;
        }
        if (is_object($id) && method_exists($id, '__toString')) {
            return (string)$id;
        }
        throw new \InvalidArgumentException('A record id must be a scalar or an array, ' . gettype($id) . ' given');
    }

    /* ------------------------------------------------------------------ */
    /* loading                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Load the row with the given key. On a miss a previously loaded row is
     * unloaded (values cleared, isRecordSelected() false) and false is returned.
     * @param int|string|array|null $id scalar key, or name => value / positional values for composite keys
     * @throws DatabaseException
     */
    public function select($id): bool
    {
        $this->recordId = self::normalizeRecordId($id);
        $pk = $this->primaryKey();
        if ($pk->getFirstField() === null) {
            $this->log('error', 'Cannot select a row: the table has no primary key', [$this->getTable()->getName()]);
            return false;
        }

        if (is_array($id)) {
            // composite (or explicitly keyed) primary key: address the row by every key column
            $pk->setValues($id);
            $where = $pk->getWhereClause('pk_');
            $bindings = $pk->getBindings('pk_', false);
            $this->recordId = $pk->isComposite() ? $pk->getValues() : $pk->getFirstField()->getValue();
        } else {
            if ($id === null || $id === '') {
                $this->unload();
                return false;
            }
            $where = Identifier::quote($pk->getFirstField()->getName()) . ' = :pk_id';
            $bindings = [':pk_id' => [$id, \PDO::PARAM_STR]];
        }

        $sql = 'SELECT * FROM ' . Identifier::quote($this->getTable()->getName()) . ' WHERE ' . $where . ' LIMIT 1';
        $row = $this->execute($sql, $bindings, 'SELECT')->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $this->log('debug', 'SELECT found no row', [$this->recordId]);
            $this->unload();
            return false;
        }
        $this->loadRow($row);
        return true;
    }

    /**
     * Reloads the data from the database into the object
     * @alias select()
     * @throws DatabaseException
     */
    public function reload(): bool
    {
        foreach ($this->getTable()->getFields() as $field) {
            $field->clearFormElementCache();
        }
        return $this->select($this->recordId);
    }

    /**
     * Take the values of an already fetched row (no query). The object is then
     * loaded exactly as after select(): record id from the key columns, nothing
     * dirty, afterLoad() called.
     * @param array<string, mixed> $row column => value as fetched
     */
    public function loadRow(array $row): static
    {
        $table = $this->getTable();
        foreach ($row as $column => $value) {
            if ($table->hasField((string)$column)) {
                $table->getField((string)$column)->loadValueFromDb($value);
            }
        }
        $pk = $this->primaryKey();
        if ($pk->isComposite()) {
            $this->recordId = $pk->getValues();
        } else if ($pk->getFirstField() !== null && $pk->getFirstField()->getValue() !== null) {
            $this->recordId = $pk->getFirstField()->getValue();
        }
        $this->recordSelected = true;
        $this->afterLoad();
        return $this;
    }

    /**
     * Build an object from a fetched row without a second SELECT
     * @param array<string, mixed> $row
     * @return static
     */
    public static function hydrate(array $row)
    {
        $object = new static();
        $object->loadRow($row);
        return $object;
    }

    /**
     * Clear every value and the loaded flag; the table metadata and the record id stay
     */
    public function reset(): static
    {
        $this->log('debug', 'Resetting object data');
        $this->clearFieldValues();
        $this->recordId = null;
        $this->recordSelected = false;
        $this->affectedRows = 0;
        $this->lastSql = null;
        return $this;
    }

    /** A loaded row is gone (missed select): forget its values so nothing stale can be stored. */
    private function unload(): void
    {
        if ($this->recordSelected) {
            $this->clearFieldValues();
        }
        $this->recordSelected = false;
    }

    private function clearFieldValues(): void
    {
        foreach ($this->getTable()->getFields() as $field) {
            $field->setValueRaw(null);
            $field->setOldValue(null);
            $field->clearFormElementCache();
        }
    }

    /* ------------------------------------------------------------------ */
    /* finders                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * A query on this model's table that returns instances of this class
     *
     *   Customer::query()->where('city', 'Boston')->orderBy('name')->limit(10)->get();
     */
    public static function query(): Query
    {
        $probe = new static();
        return new Query($probe->getTable(), static::class);
    }

    /**
     * @see query()
     */
    public static function getQueryBuilder(): Query
    {
        return static::query();
    }

    /**
     * Find one row by using the first result
     * @param array<string, mixed> $keyValuePairs column => value (null = IS NULL, array = IN)
     * @return static|null null when nothing matches or a column does not exist
     * @throws DatabaseException
     */
    public static function bOfindOne(array $keyValuePairs): ?BaseObject
    {
        $query = self::criteriaQuery($keyValuePairs);
        if ($query === null) {
            return null;
        }
        /** @var static|null $first */
        $first = $query->first();
        return $first;
    }

    /**
     * Every row matching all criteria (all rows when empty)
     * @param array<string, mixed> $keyValuePairs column => value (null = IS NULL, array = IN)
     * @param array<string, string>|string[] $orderBy column => ASC|DESC, or a list of columns
     * @return static[] empty when a column does not exist
     * @throws DatabaseException
     */
    public static function bOfind(array $keyValuePairs = [], array $orderBy = [], ?int $limit = null, int $offset = 0): array
    {
        $query = self::criteriaQuery($keyValuePairs);
        if ($query === null) {
            return [];
        }
        foreach ($orderBy as $column => $direction) {
            if (is_int($column)) {
                $query->orderBy((string)$direction);
            } else {
                $query->orderBy($column, (string)$direction);
            }
        }
        $query->limit($limit);
        $query->offset($offset);
        /** @var static[] $objects */
        $objects = $query->get();
        return $objects;
    }

    /**
     * Every row matching all criteria as associative arrays
     * @param array<string, mixed> $keyValuePairs column => value (null = IS NULL, array = IN)
     * @return array<int, array<string, mixed>> empty when a column does not exist
     * @throws DatabaseException
     */
    public static function findAsArray(array $keyValuePairs = []): array
    {
        $query = self::criteriaQuery($keyValuePairs);
        return $query === null ? [] : $query->arrays();
    }

    /**
     * Criteria to Query; null when a key is not a column of the table
     * (unknown columns are refused, never interpolated).
     * @param array<int|string, mixed> $criteria
     */
    private static function criteriaQuery(array $criteria): ?Query
    {
        $query = static::query();
        foreach ($criteria as $fieldName => $value) {
            if (!is_string($fieldName) || preg_match('/[^A-Za-z0-9_]/', $fieldName) || !$query->getTable()->hasField($fieldName)) {
                return null;
            }
            $query->where($fieldName, $value);
        }
        return $query;
    }

    /* ------------------------------------------------------------------ */
    /* writing                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Stores the data in the database: insert when isNew(), update otherwise
     * @throws DatabaseException
     * @throws MissingFieldValueException
     * @throws UniqueFieldDuplicateValueException
     */
    public function store(): bool
    {
        return $this->isNew() ? $this->insert() : $this->update();
    }

    /**
     * Creates a new row from the object's values.
     * Columns left null and not required are omitted so the database default
     * applies; call reload() to read defaults back.
     * @return bool
     * @throws DatabaseException
     * @throws MissingFieldValueException
     * @throws UniqueFieldDuplicateValueException
     */
    public function insert()
    {
        $this->log('debug', __METHOD__);
        $this->beforeInsert();

        $table = $this->getTable();
        $pk = $this->primaryKey();

        foreach ($pk->getFields() as $keyField) {
            if (!$keyField->isAutoIncrement() && (string)($keyField->getValue() ?? '') === '') {
                $generated = $keyField->hasType() ? $keyField->getType()->generatePrimaryKeyValue() : null;
                if ($generated === null) {
                    throw new MissingFieldValueException(sprintf('Missing primary key value for %s.%s: the key is not auto-increment and its type cannot generate a value', $table->getName(), $keyField->getName()));
                }
                $keyField->setValueRaw($generated);
            }
        }

        if ($table->hasField(static::FIELD_CREATED_ON) && (string)($table->getField(static::FIELD_CREATED_ON)->getValue() ?? '') === '') {
            $this->setFieldValueRaw(static::FIELD_CREATED_ON, date('Y-m-d H:i:s'));
        }
        if ($table->hasField(static::FIELD_CREATED_BY) && (string)($table->getField(static::FIELD_CREATED_BY)->getValue() ?? '') === '') {
            $userId = static::resolveActiveUserId();
            if ($userId !== null) {
                $this->setFieldValue(static::FIELD_CREATED_BY, $userId);
            }
        }

        $this->checkFieldsForMissingRequiredValues();
        $this->checkUniqueFieldsForDuplicateValues();

        $columns = $placeholders = $bindings = [];
        foreach ($table->getFields() as $field) {
            if ($field->isAutoIncrement()) {
                continue;
            }
            $value = $field->getValue();
            if ($value === null && !$field->isRequired() && !$pk->hasField($field->getName())) {
                continue; // the database default applies
            }
            $placeholder = ':' . Identifier::sanitize($field->getName());
            $columns[] = Identifier::quote($field->getName());
            $placeholders[] = $placeholder;
            $bindings[$placeholder] = $this->bindingFor($field, $value);
        }

        $sql = 'INSERT INTO ' . Identifier::quote($table->getName());
        if (count($columns) === 0) {
            $sql .= Identifier::isPostgres() ? ' DEFAULT VALUES' : ' () VALUES ()';
        } else {
            $sql .= " (\n\t  " . implode("\n\t, ", $columns) . "\n)\nVALUES (\n\t  " . implode("\n\t, ", $placeholders) . "\n)";
        }
        $returning = Identifier::isPostgres() && $pk->getFieldCount() > 0;
        if ($returning) {
            $sql .= ' RETURNING ' . implode(', ', array_map(fn(string $name) => Identifier::quote($name), $pk->getFieldNames()));
        }

        $stmt = $this->execute($sql, $bindings, 'INSERT');
        $this->affectedRows = $stmt->rowCount();

        if ($returning) {
            $keys = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (is_array($keys)) {
                foreach ($pk->getFields() as $keyField) {
                    if (array_key_exists($keyField->getName(), $keys)) {
                        $keyField->loadValueFromDb($keys[$keyField->getName()]);
                    }
                }
            }
        } else if ($pk->isAutoIncrement()) {
            $insertId = Db::getInstance()->getInsertId();
            if ($insertId !== '' && $insertId !== '0') {
                $pk->getFirstField()->setValue($insertId);
            }
        }

        $this->recordId = $pk->isComposite() ? $pk->getValues() : ($pk->getFirstField() !== null ? $pk->getFirstField()->getValue() : null);
        foreach ($pk->getFields() as $keyField) {
            $keyField->setOldValue($keyField->getValue());
        }
        $this->recordSelected = true;
        $this->log('debug', 'INSERT done', ['id' => $this->recordId, 'affected' => $this->affectedRows]);

        $this->logAuditEntry(AuditLog::ACTION_INSERT);
        // the row now holds the current values: a following store() only writes what changes from here on
        $this->markFieldsAsPersisted();
        $this->afterInsert();
        return true;
    }

    /**
     * Writes the changed fields of a loaded row. Nothing changed = nothing
     * written (true). Returns false when no row is loaded.
     * @return bool
     * @throws DatabaseException
     * @throws MissingFieldValueException
     * @throws UniqueFieldDuplicateValueException
     */
    public function update()
    {
        $this->log('debug', __METHOD__, [$this->recordId]);
        if (!$this->recordSelected) {
            $this->log('debug', 'No prior record selected to update. Returning false');
            return false;
        }
        $this->beforeUpdate();
        if (!$this->isDirty()) {
            $this->log('debug', 'No fields to update, returning');
            return true;
        }

        $table = $this->getTable();
        if ($table->hasField(static::FIELD_UPDATED_ON)) {
            $this->setFieldValueRaw(static::FIELD_UPDATED_ON, date('Y-m-d H:i:s'));
        }
        if ($table->hasField(static::FIELD_UPDATED_BY)) {
            $userId = static::resolveActiveUserId();
            if ($userId !== null) {
                $this->setFieldValue(static::FIELD_UPDATED_BY, $userId);
            }
        }

        $this->checkFieldsForMissingRequiredValues();
        $this->checkUniqueFieldsForDuplicateValues();

        $pk = $this->primaryKey();
        $assignments = $bindings = [];
        foreach ($table->getFields() as $field) {
            if ($field->isAutoIncrement() || !$field->hasChanged()) {
                continue;
            }
            $placeholder = ':' . Identifier::sanitize($field->getName());
            $assignments[] = Identifier::quote($field->getName()) . ' = ' . $placeholder;
            $bindings[$placeholder] = $this->bindingFor($field, $field->getValue());
        }
        if (count($assignments) === 0) {
            return true;
        }

        // every key column, bound to the values the row was loaded with
        $sql = 'UPDATE ' . Identifier::quote($table->getName()) . "\nSET\n\t  " . implode("\n\t, ", $assignments)
            . "\nWHERE\n\t" . $pk->getWhereClause('pk_');
        $bindings = array_merge($bindings, $pk->getBindings('pk_', true));

        $this->affectedRows = $this->execute($sql, $bindings, 'UPDATE')->rowCount();
        $this->log('debug', 'UPDATE done', ['affected' => $this->affectedRows]);

        // a changed (non auto-increment) key column: the object now addresses the new row
        $this->recordId = $pk->isComposite() ? $pk->getValues() : ($pk->getFirstField() !== null ? $pk->getFirstField()->getValue() : $this->recordId);

        $this->logAuditEntry(AuditLog::ACTION_UPDATE);
        // the row now holds the current values: a second store() must not re-send or re-log them
        $this->markFieldsAsPersisted();
        $this->afterUpdate();
        return true;
    }

    /**
     * Deletes the loaded row. This action cannot be undone.
     * Does not open a transaction, so it can run inside the caller's.
     * @return bool false when no row is loaded, the key is empty, or the row was already gone
     *              (deleted by someone else since it was loaded: nothing was deleted, afterDelete() is not called)
     * @throws DatabaseException
     */
    public function hardDelete()
    {
        $this->log('debug', __METHOD__, [$this->recordId]);
        if (!$this->recordSelected) {
            $this->log('debug', 'No prior record selected to delete. Returning false');
            return false;
        }

        $pk = $this->primaryKey();
        $bindings = $pk->getBindings('pk_', true);
        if (count($bindings) === 0) {
            $this->log('error', 'Delete method failed: the table has no primary key');
            return false;
        }
        foreach ($bindings as [$value]) {
            if ($value === null || $value === '') {
                $this->log('error', 'Delete method failed: Invalid record id provided', [$this->recordId]);
                return false;
            }
        }

        $this->beforeDelete(static::DELETE_HARD);
        $sql = 'DELETE FROM ' . Identifier::quote($this->getTable()->getName()) . "\nWHERE " . $pk->getWhereClause('pk_');
        $this->affectedRows = $this->execute($sql, $bindings, 'DELETE')->rowCount();
        ForeignKey::clearValidationCache($this->getTable()->getName());
        $this->log('debug', 'DELETE done', ['affected' => $this->affectedRows]);

        if ($this->affectedRows < 1) {
            $this->log('warning', 'Delete found no row: it was removed since the object was loaded', [$this->recordId]);
            $this->recordSelected = false;
            return false;
        }
        // Log the delete once the row is really gone; the object still holds every value for the snapshot
        $this->logAuditEntry(AuditLog::ACTION_DELETE);
        $this->recordSelected = false;
        $this->afterDelete(static::DELETE_HARD);
        return true;
    }

    /**
     * Soft delete: record_status_id = 0, or deleted = 1. Unsaved changes are
     * stored with the flag; a clean object is reloaded first.
     * @return bool false when the table has no status column or the row does not exist
     * @throws DatabaseException
     * @throws InvalidForeignKeyValue
     */
    public function softDelete(): bool
    {
        $this->log('debug', __METHOD__, [$this->recordId]);
        $table = $this->getTable();
        if (!$table->hasField(static::FIELD_RECORD_STATUS_ID) && !$table->hasField(static::FIELD_DELETED)) {
            $this->log('error', "Soft delete method failed: No 'deleted' or record_status_id property found");
            return false;
        }
        if (!$this->recordSelected || !$this->isDirty()) {
            if (!$this->reload()) {
                $this->log('error', 'Soft delete method failed: the row does not exist', [$this->recordId]);
                return false;
            }
        }

        $this->beforeDelete(static::DELETE_SOFT);
        if ($table->hasField(static::FIELD_RECORD_STATUS_ID)) {
            $this->setFieldValue(static::FIELD_RECORD_STATUS_ID, 0);
        } else {
            $this->setFieldValue(static::FIELD_DELETED, 1);
        }

        // one DELETE audit entry carrying the status change, instead of an UPDATE entry plus a DELETE snapshot
        $changes = AuditLog::collectChanges($this);
        $auditWasEnabled = $this->auditLoggingEnabled;
        $this->auditLoggingEnabled = false;
        try {
            $result = $this->store();
        } finally {
            $this->auditLoggingEnabled = $auditWasEnabled;
        }
        if ($result && count($changes) > 0) {
            $this->logAuditEntry(AuditLog::ACTION_DELETE, $changes);
        }
        $this->afterDelete(static::DELETE_SOFT);
        return $result;
    }

    /**
     * Whether the table supports softDelete() (deleted or record_status_id column)
     */
    public function canSoftDelete(): bool
    {
        return $this->getTable()->hasField(static::FIELD_DELETED) || $this->getTable()->hasField(static::FIELD_RECORD_STATUS_ID);
    }

    /**
     * Set updated_on to now and store
     * @throws DatabaseException
     */
    public function touch(): void
    {
        if ($this->getTable()->hasField(static::FIELD_UPDATED_ON)) {
            $this->setFieldValueRaw(static::FIELD_UPDATED_ON, date('Y-m-d H:i:s'));
            $this->store();
        }
    }

    /**
     * Run $fn inside a database transaction (committed on return, rolled back
     * and rethrown on exception; nested calls join the outer transaction).
     * @param callable(Db): mixed $fn
     * @return mixed what $fn returned
     * @throws \Throwable
     */
    public static function transaction(callable $fn)
    {
        return Db::getInstance()->transaction($fn);
    }

    /**
     * Align every field's old value with its current value after a successful write,
     * so hasChanged() is false until the object is modified again.
     */
    protected function markFieldsAsPersisted(): void
    {
        foreach ($this->getTable()->getFields() as $field) {
            $field->setOldValue($field->getValue());
        }
    }

    /**
     * @param mixed $value
     * @return array{0: mixed, 1: int}
     */
    private function bindingFor(Field $field, $value): array
    {
        if ($field->hasType()) {
            return $field->getType()->toDb($value);
        }
        return [$value, $value === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR];
    }

    /**
     * Prepare, bind and execute one statement; failures become DatabaseException.
     * @param array<string, array{0: mixed, 1: int}> $bindings placeholder => [value, PDO type]
     * @throws DatabaseException
     */
    private function execute(string $sql, array $bindings, string $operation): \PDOStatement
    {
        $this->lastSql = $sql;
        $this->log('debug', $operation . ' SQL', [$sql, self::bindingValues($bindings)]);
        try {
            return Db::getInstance()->statement($sql, $bindings);
        } catch (\PDOException $ex) {
            $this->log('error', $operation . ' SQL failed', [$sql, self::bindingValues($bindings), $ex->getMessage()]);
            throw DatabaseException::fromPdo($ex, $sql, $bindings);
        }
    }

    /**
     * @param array<string, array{0: mixed, 1: int}> $bindings
     * @return array<string, mixed>
     */
    private static function bindingValues(array $bindings): array
    {
        return array_map(fn(array $binding) => $binding[0], $bindings);
    }

    /* ------------------------------------------------------------------ */
    /* validation                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Constraint check of every field without touching the database row:
     * required, type rules (length, range) and single-column uniqueness.
     * @return array<string, string[]> column => messages, empty when valid
     */
    public function validate(): array
    {
        $errors = [];
        foreach ($this->getTable()->getFields() as $field) {
            $messages = $field->validate();
            if (count($messages) > 0) {
                $errors[$field->getName()] = $messages;
            }
        }
        foreach ($this->uniqueFields() as $name => $field) {
            if ($this->isDuplicateValue($field)) {
                $errors[$name][] = sprintf('%s must be unique: the value already exists', $field->getLabel() ?: $name);
            }
        }
        return $errors;
    }

    /**
     * Check if any field is required and has no value
     * @throws MissingFieldValueException
     */
    private function checkFieldsForMissingRequiredValues(): void
    {
        $msg = [];
        $names = [];
        foreach ($this->getTable()->getFields() as $field) {
            if ($field->isRequired() && ($field->getValue() === null || $field->getValue() === '') && !$field->isPrimaryKey()) {
                $msg[] = sprintf("Field %s is required. Value provided is %s", $field->getName(), (string)$field->getValue());
                $names[] = $field->getName();
            }
        }
        if (count($msg) > 0) {
            throw (new MissingFieldValueException(join('; ', $msg)))->setFieldNames($names);
        }
    }

    /**
     * Check every single-column unique field whose value is new or changed against the table
     * @throws UniqueFieldDuplicateValueException
     */
    private function checkUniqueFieldsForDuplicateValues(): void
    {
        foreach ($this->uniqueFields() as $name => $field) {
            if ($this->recordSelected && !$field->hasChanged()) {
                continue;
            }
            if ($this->isDuplicateValue($field)) {
                throw (new UniqueFieldDuplicateValueException(sprintf(
                    "Field %s must be unique: value '%s' already exists in %s",
                    $name,
                    is_scalar($field->getValue()) ? (string)$field->getValue() : gettype($field->getValue()),
                    $this->getTable()->getName()
                )))->setFieldNames([$name]);
            }
        }
    }

    /**
     * Single-column unique fields (Table::getUniqueFields() and Field::isUnique()), primary key excluded
     * @return array<string, Field>
     */
    private function uniqueFields(): array
    {
        $pk = $this->primaryKey();
        $unique = [];
        foreach ($this->getTable()->getUniqueFields() as $field) {
            $unique[$field->getName()] = $field;
        }
        foreach ($this->getTable()->getFields() as $field) {
            if ($field->isUnique()) {
                $unique[$field->getName()] = $field;
            }
        }
        foreach (array_keys($unique) as $name) {
            if ($pk->hasField($name)) {
                unset($unique[$name]);
            }
        }
        return $unique;
    }

    /** True when another row already holds this field's value */
    private function isDuplicateValue(Field $field): bool
    {
        $value = $field->getValue();
        if ($value === null || $value === '') {
            return false;
        }
        $query = (new Query($this->getTable()))->where($field->getName(), $value);
        if ($this->recordSelected) {
            foreach ($this->primaryKey()->getFields() as $keyField) {
                $old = $keyField->getOldValue();
                $own = ($old === null || $old === '') ? $keyField->getValue() : $old;
                if ($own !== null && $own !== '') {
                    $query->where($keyField->getName(), '<>', $own);
                }
            }
        }
        return $query->exists();
    }

    /* ------------------------------------------------------------------ */
    /* lifecycle hooks (no-ops; override in a model class, throw to abort)  */
    /* ------------------------------------------------------------------ */

    /** Before the INSERT is built: defaults, derived values. */
    protected function beforeInsert(): void
    {
    }

    /** After the row exists and the key is known. */
    protected function afterInsert(): void
    {
    }

    /** Before the change set is computed (runs even when nothing changed). */
    protected function beforeUpdate(): void
    {
    }

    /** After the UPDATE statement ran (not called when nothing changed). */
    protected function afterUpdate(): void
    {
    }

    /** @param string $mode self::DELETE_HARD or self::DELETE_SOFT */
    protected function beforeDelete(string $mode): void
    {
    }

    /** @param string $mode self::DELETE_HARD or self::DELETE_SOFT */
    protected function afterDelete(string $mode): void
    {
    }

    /** After a row was loaded by select(), reload(), hydrate() or loadRow(). */
    protected function afterLoad(): void
    {
    }

    /* ------------------------------------------------------------------ */
    /* active user and audit                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Set the active user ID for all BaseObject instances
     */
    public static function setActiveUserId(?int $userId): void
    {
        self::$activeUserId = $userId;
    }

    public static function getActiveUserId(): ?int
    {
        return self::$activeUserId;
    }

    /**
     * The user to attribute writes to: setActiveUserId(), else the
     * application's <package>\Person::getLoggedUserId() when such a class exists.
     */
    public static function resolveActiveUserId(): ?int
    {
        if (self::$activeUserId !== null) {
            return self::$activeUserId;
        }
        $package = null;
        try {
            $settings = Settings::getInstance();
            $package = $settings->propertyExists('project.package_name') ? $settings->getProperty('project.package_name') : null;
        } catch (\Throwable $ex) {
            return null;
        }
        if (!is_string($package) || $package === '') {
            return null;
        }
        $personClass = '\\' . $package . '\\Person';
        if (!class_exists($personClass) || !method_exists($personClass, 'getLoggedUserId')) {
            return null;
        }
        try {
            $loggedUserId = $personClass::getLoggedUserId();
        } catch (\Throwable $ex) {
            return null;
        }
        return is_numeric($loggedUserId) ? (int)$loggedUserId : null;
    }

    public function auditLogDisable(): static
    {
        $this->auditLoggingEnabled = false;
        return $this;
    }

    public function auditLogEnable(): static
    {
        $this->auditLoggingEnabled = true;
        return $this;
    }

    /**
     * Whether writes on this object are audited (per-object switch AND the process-wide AuditLog switch)
     */
    public function isAuditLoggingEnabled(): bool
    {
        return $this->auditLoggingEnabled && AuditLog::isEnabled();
    }

    /**
     * Log audit entry if audit logging is enabled.
     * Overridable: a subclass can enrich or redirect the entry.
     * @param string $action one of AuditLog::ACTION_*
     * @param array|null $changes explicit change list (soft delete); null = derive from the fields
     */
    protected function logAuditEntry(string $action, ?array $changes = null): void
    {
        if (!$this->isAuditLoggingEnabled()) {
            return;
        }

        try {
            $userId = AuditLog::resolveUserId();

            if ($action === AuditLog::ACTION_INSERT) {
                AuditLog::logInsert($this, $userId);
            } elseif ($action === AuditLog::ACTION_UPDATE) {
                AuditLog::logUpdate($this, $userId);
            } elseif ($action === AuditLog::ACTION_DELETE) {
                AuditLog::logDelete($this, $userId, $changes);
            }
        } catch (\Throwable $e) {
            $this->log('error', 'Audit logging failed', [$e->getMessage()]);
            if (AuditLog::isStrict()) {
                throw $e;
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* logging (PSR-3)                                                     */
    /* ------------------------------------------------------------------ */

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(?LoggerInterface $logger): static
    {
        $this->logger = $logger;
        if ($logger !== null) {
            $logger->debug('Loading Logger: ' . get_class($logger));
        }
        return $this;
    }

    /** @param mixed $context */
    public function addLoggerDebug(string $message, $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @param mixed $context */
    public function addLoggerInfo(string $message, $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param mixed $context */
    public function addLoggerNotice(string $message, $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /** @param mixed $context */
    public function addLoggerWarning(string $message, $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param mixed $context */
    public function addLoggerError(string $message, $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param mixed $context */
    public function addLoggerCritical(string $message, $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /** @param mixed $context */
    public function addLoggerAlert(string $message, $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /** @param mixed $context */
    public function addLoggerEmergency(string $message, $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /** @param mixed $context */
    private function log(string $level, string $message, $context = []): void
    {
        if ($this->logger === null) {
            return;
        }
        $this->logger->log($level, $message, is_array($context) ? $context : [$context]);
    }

    /* ------------------------------------------------------------------ */
    /* cloning                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * A clone is a new, unsaved row with the same values and an empty key
     */
    public function __clone()
    {
        // deep-copy the metadata: a shallow clone would share Field objects with the
        // original, so setting the clone's id would also change the original's
        if ($this->table !== null) {
            $this->table = clone $this->table;
            /** @var PrimaryKey|null $pk */
            $pk = $this->table->getPrimaryKey();
            if ($pk !== null && $pk->getFieldCount() > 0) {
                foreach ($pk->getFields() as $pkField) {
                    $pkField->setValueRaw(null);
                    $pkField->setOldValue(null);
                }
            } else if ($this->table->hasField('id')) {
                $this->table->getField('id')->setValueRaw(null);
                $this->table->getField('id')->setOldValue(null);
            }
        }
        $this->recordId = null;
        $this->recordSelected = false;
        $this->affectedRows = 0;
        $this->lastSql = null;
    }

    /* ------------------------------------------------------------------ */
    /* UI helpers                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * A form bound to this object. Without a draw mode every field is a flat element
     * (lay it out yourself); with one, the form is laid out for that mode by $layout,
     * else getFormLayout(), else FormLayout::auto(). configureForm() runs last.
     */
    public function getForm(?string $drawMode = null, ?FormLayout $layout = null): Form
    {
        $form = new Form($this);
        $form->setComponents($this->getAllFieldsAsFormElements());
        foreach ($this->getTable()->getFields() as $field) {
            if ($field->isDocumentField()) {
                $form->setHasFiles(true);
            }
        }
        if ($drawMode !== null) {
            $form->applyLayout($drawMode, $layout);
        }
        $this->configureForm($form);
        return $form;
    }

    /**
     * The layout of this object's forms (columns, sections, tabs, excluded / read-only /
     * hidden fields per draw mode); null = FormLayout::auto() from the table and the fields'
     * form options. Override in the model class:
     *
     *   return FormLayout::auto($this->getTable())->readOnly(['code'], [DrawMode::UPDATE]);
     */
    public function getFormLayout(): ?FormLayout
    {
        return null;
    }

    /**
     * Hook run at the end of getForm(): the place for element options, buttons or
     * visibility conditions that every form of this model needs
     */
    protected function configureForm(Form $form): void
    {
    }

    /**
     * Return all fields as form elements
     * @return FormElementInterface[]
     */
    public function getAllFieldsAsFormElements(): array
    {
        $elements = [];
        foreach ($this->getTable()->getFields() as $field) {
            $elements[] = $field->getFormElement();
        }
        return $elements;
    }

    /**
     * A paginated list of every row with view / update / delete actions
     */
    public function getListAllSimpleTableObject(): SimpleTable
    {
        $table = new SimpleTable();
        $parser = UrlParser::getInstance();
        $primaryKeyField = $this->primaryKey()->getFirstField() !== null ? $this->primaryKey()->getFirstField()->getName() : 'id';
        $placeholder = '{' . $primaryKeyField . '}';

        $translations = [];
        try {
            $translations = Settings::getInstance()->getProperty('settings.label.translations');
        } catch (\Exception $ex) {
        }
        if (!is_array($translations)) {
            $translations = [];
        }

        $table->setSql(sprintf("SELECT * FROM %s", Identifier::quote($this->getTable()->getName())))
            ->setRowsPerPage(10);

        $module = $parser->_getModule();
        $submodule = $parser->_getSubmodule();
        $pretty = $parser->isUsePrettyUrl();

        $viewLink = new Link();
        $viewLink->setValue('')
            ->setHref($pretty
                ? sprintf("/%s/%s/view/%s", $module, $submodule, $placeholder)
                : sprintf("?module=%s&submodule=%s&action=view&id=%s", $module, $submodule, $placeholder))
            ->setIcon('fa fa-eye')
            ->addCssClass('btn')
            ->addCssClass('btn-outline-secondary')
            ->addCssClass('btn-sm')
            ->addAttribute('title', $translations['view'] ?? 'view');

        $updateLink = new Link();
        $updateLink->setValue($translations['update'] ?? 'Update')
            ->setHref($pretty
                ? sprintf("/%s/%s/update/%s", $module, $submodule, $placeholder)
                : sprintf("?module=%s&submodule=%s&action=update&id=%s", $module, $submodule, $placeholder))
            ->setIcon('fa fa-edit');

        $deleteLink = new Link();
        $deleteLink->setValue($translations['delete'] ?? 'Delete')
            ->setHref($pretty
                ? sprintf("/%s/%s/delete/%s", $module, $submodule, $placeholder)
                : sprintf("?module=%s&submodule=%s&action=delete&id=%s", $module, $submodule, $placeholder))
            ->setIcon('fa fa-trash')
            ->setConfirmationMessage($translations['delete-confirmation'] ?? 'Are you sure you want to delete this item?\\nThis action cannot be undone!');

        $options = new Dropdown();
        $options->rightAlign();
        $options->setIcon('fa fa-cogs')
            ->addLink($updateLink)
            ->addLink($deleteLink)
            ->addCssClass('btn-outline-secondary')
            ->addCssClass('btn-sm');

        $table->addActionButton($viewLink);
        $table->addActionButton($options);
        return $table;
    }

    /**
     * Icon class of the model (menus, lists); generated classes override it
     */
    public static function getIcon(): ?string
    {
        return '';
    }

    /* ------------------------------------------------------------------ */
    /* schema utility                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Populate the object_list table with all tables from the database.
     * Creates the table if it doesn't exist.
     * Inserts any tables not already present, using Util::tableNameToClassName for the object_name.
     * @return int Number of new rows inserted
     * @throws \Exception
     */
    public static function populateTableObjectList(): int
    {
        $db = Db::getInstance();
        $postgres = Identifier::isPostgres();

        if ($postgres) {
            $db->execute("CREATE TABLE IF NOT EXISTS object_list (
                id SERIAL PRIMARY KEY,
                table_name VARCHAR(255) DEFAULT NULL,
                object_name VARCHAR(255) DEFAULT NULL
            )");
        } else {
            $db->execute("CREATE TABLE IF NOT EXISTS object_list (
                id INT(11) NOT NULL AUTO_INCREMENT,
                table_name VARCHAR(255) DEFAULT NULL,
                object_name VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        $existingTables = [];
        foreach ($db->query("SELECT table_name FROM object_list")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $existingTables[] = (string)$row['table_name'];
        }

        if ($postgres) {
            $stmt = $db->prepare("SELECT table_name FROM information_schema.tables
                    WHERE table_type = 'BASE TABLE'
                    AND table_schema = current_schema()
                    ORDER BY table_name ASC");
        } else {
            $stmt = $db->prepare("SELECT table_name FROM information_schema.tables
                    WHERE table_type = 'BASE TABLE'
                    AND table_schema = :db_name
                    ORDER BY table_name ASC");
            $stmt->bindValue(':db_name', $db->getDatabase());
        }
        $stmt->execute();
        $allTables = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $insertStmt = $db->prepare("INSERT INTO object_list (table_name, object_name) VALUES (:table_name, :object_name)");
        $inserted = 0;
        foreach ($allTables as $row) {
            $tableName = (string)($row['table_name'] ?? $row['TABLE_NAME'] ?? '');
            if ($tableName === '' || in_array($tableName, $existingTables, true)) {
                continue;
            }
            $insertStmt->bindValue(':table_name', $tableName);
            $insertStmt->bindValue(':object_name', Util::tableNameToClassName($tableName));
            $insertStmt->execute();
            $inserted++;
        }

        return $inserted;
    }
}
