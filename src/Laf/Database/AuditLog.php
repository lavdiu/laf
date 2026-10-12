<?php

namespace Laf\Database;

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeBlob;
use Laf\Database\Field\TypeDateTime;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeText;
use Laf\Database\Field\TypeVarchar;
use Laf\Util\Settings;

/**
 * Audit trail for every INSERT / UPDATE / DELETE performed through BaseObject.
 *
 * Writing: BaseObject calls logInsert()/logUpdate()/logDelete() (or logChange()
 * directly) after a successful statement. One row per action is written to the
 * `audit_log` table (see migrations/create_audit_logs_table.sql and
 * migrations/create_audit_logs_table_postgres.sql):
 *
 *   user_id     who did it (BaseObject::setActiveUserId(), else Person::getLoggedUserId())
 *   table_name  the table that changed
 *   record_id   the primary key as a string ("v1,v2" for composite keys)
 *   action      INSERT | UPDATE | DELETE
 *   changes     JSON list of {field, old_value, new_value}
 *   created_on  application time, same clock as created_on/updated_on on the rows
 *
 * Reading: AuditLog is a regular BaseObject model of that table, so
 * `new AuditLog($id)`, AuditLog::findForRecord(), findForObject() and
 * getChanges() give access to the history.
 *
 * Failures never break the business operation: a failed audit insert is logged
 * through the Db logger / error_log and remembered in getLastError(). Switch
 * setStrict(true) on when a lost audit row must be an exception instead.
 */
class AuditLog extends BaseObject
{
    const ACTION_INSERT = 'INSERT';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_DELETE = 'DELETE';

    const ACTIONS = [self::ACTION_INSERT, self::ACTION_UPDATE, self::ACTION_DELETE];

    /** Columns never written to the payload: the row itself carries them and they change on every write */
    const SKIPPED_FIELDS = ['created_on', 'created_by', 'updated_on', 'updated_by'];

    /** @var string table the audit rows are written to */
    private static string $tableName = 'audit_log';

    /** @var bool process-wide switch; per-object switch is BaseObject::auditLogDisable() */
    private static bool $enabled = true;

    /** @var bool when true a failed audit write throws instead of being swallowed */
    private static bool $strict = false;

    /** @var int|null longest value kept in the payload (0/null = unlimited); read from Settings 'audit.max_value_length' */
    private static ?int $maxValueLength = null;

    /** @var string|null message of the last failed write */
    private static ?string $lastError = null;

    /** @var array{key:string, statement:\PDOStatement}|null prepared INSERT, reused across writes */
    private static ?array $statement = null;

    public function __construct($id = null)
    {
        parent::__construct($id);
        $this->buildClass();
        $this->auditLogDisable();
        if ($id !== null && $id !== '') {
            $this->select($id);
        }
    }

    protected function returnLeafClass(): static
    {
        return $this;
    }

    private function buildClass(): void
    {
        $this->setTable(new Table(self::$tableName));
        $pk = new PrimaryKey();

        $field = (new Field())
            ->setName('id')->setLabel('Id')
            ->setRequired(true)->setAutoIncrement(true)
            ->setType(new TypeInteger());
        $pk->addField($field);
        $this->getTable()->addField($field);

        $this->getTable()->addField((new Field())
            ->setName('user_id')->setLabel('User')
            ->setRequired(false)
            ->setType(new TypeInteger()));

        $this->getTable()->addField((new Field())
            ->setName('table_name')->setLabel('Table Name')
            ->setRequired(true)->setMaxLength(100)
            ->setType(new TypeVarchar()));

        $this->getTable()->addField((new Field())
            ->setName('record_id')->setLabel('Record')
            ->setRequired(true)->setMaxLength(50)
            ->setType(new TypeVarchar()));

        $this->getTable()->addField((new Field())
            ->setName('action')->setLabel('Action')
            ->setRequired(true)->setMaxLength(10)
            ->setType(new TypeVarchar()));

        $this->getTable()->addField((new Field())
            ->setName('changes')->setLabel('Changes')
            ->setRequired(true)->setAllowHtml(true)
            ->setType(new TypeText()));

        $this->getTable()->addField((new Field())
            ->setName('created_on')->setLabel('Created On')
            ->setRequired(true)
            ->setType(new TypeDateTime()));

        $this->getTable()->setPrimaryKey($pk);
    }

    /* ------------------------------------------------------------------ */
    /* configuration                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Turn audit logging on or off for the whole process (imports, migrations, tests).
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * When strict, a failed audit write throws \RuntimeException instead of returning false.
     */
    public static function setStrict(bool $strict): void
    {
        self::$strict = $strict;
    }

    public static function isStrict(): bool
    {
        return self::$strict;
    }

    /**
     * Longest string kept per value in the payload; longer values are cut and marked.
     * 0 means unlimited. Defaults to Settings 'audit.max_value_length' when present.
     */
    public static function setMaxValueLength(?int $length): void
    {
        self::$maxValueLength = $length === null ? null : max(0, $length);
    }

    public static function getMaxValueLength(): int
    {
        if (self::$maxValueLength === null) {
            self::$maxValueLength = (int)self::setting('audit.max_value_length', 0);
        }
        return self::$maxValueLength;
    }

    /**
     * Table the audit rows go to (default 'audit_log'). Affects writes and every AuditLog built afterwards.
     */
    public static function setTableName(string $tableName): void
    {
        self::$tableName = Identifier::sanitize($tableName);
        self::$statement = null;
    }

    public static function getTableName(): string
    {
        return self::$tableName;
    }

    /**
     * Message of the most recent failed write, null when the last write succeeded.
     */
    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    /* ------------------------------------------------------------------ */
    /* writing                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Write one audit row.
     * @param string $action one of the ACTION_* constants
     * @param string $tableName
     * @param int|string|array|null $recordId primary key value(s); arrays are stored as "v1,v2"
     * @param array $changes list of ['field' => .., 'old_value' => .., 'new_value' => ..]
     * @param int|null $userId defaults to the active user (BaseObject::setActiveUserId())
     * @return bool true when the row was written
     * @throws \RuntimeException in strict mode when the write fails
     */
    public static function logChange(string $action, string $tableName, $recordId, array $changes = [], ?int $userId = null): bool
    {
        if (!self::$enabled) {
            return false;
        }
        $action = strtoupper($action);
        if (!in_array($action, self::ACTIONS, true)) {
            return self::fail(sprintf('Unknown audit action "%s" for table %s', $action, $tableName));
        }

        $recordId = self::recordIdToString($recordId);
        $userId = $userId ?? self::resolveUserId();
        $payload = self::encodeChanges($changes);

        try {
            $db = Db::getInstance();
            $stmt = self::statement($db);
            $stmt->bindValue(':user_id', $userId, $userId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue(':table_name', $tableName, \PDO::PARAM_STR);
            $stmt->bindValue(':record_id', $recordId, \PDO::PARAM_STR);
            $stmt->bindValue(':action', $action, \PDO::PARAM_STR);
            $stmt->bindValue(':changes', $payload, \PDO::PARAM_STR);
            $stmt->bindValue(':created_on', date('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $stmt->execute();
        } catch (\Throwable $e) {
            self::$statement = null;
            return self::fail(sprintf('Failed to write audit log for %s#%s (%s): %s', $tableName, $recordId, $action, $e->getMessage()), $e);
        }
        self::$lastError = null;
        return true;
    }

    /**
     * Log an insert: every non-key column that received a value.
     */
    public static function logInsert(BaseObject $object, ?int $userId = null): bool
    {
        return self::logChange(
            self::ACTION_INSERT,
            $object->getTable()->getName(),
            $object->getRecordId(),
            self::collectInsertedValues($object),
            $userId
        );
    }

    /**
     * Log an update: only the columns whose value differs from the loaded one.
     * Nothing is written when nothing changed.
     */
    public static function logUpdate(BaseObject $object, ?int $userId = null): bool
    {
        $changes = self::collectChanges($object);
        if (count($changes) === 0) {
            return true;
        }
        return self::logChange(
            self::ACTION_UPDATE,
            $object->getTable()->getName(),
            $object->getRecordId(),
            $changes,
            $userId
        );
    }

    /**
     * Log a hard delete: a snapshot of every column (old value → null).
     * For a soft delete pass the changed columns as $changes instead of a snapshot.
     */
    public static function logDelete(BaseObject $object, ?int $userId = null, ?array $changes = null): bool
    {
        return self::logChange(
            self::ACTION_DELETE,
            $object->getTable()->getName(),
            $object->getRecordId(),
            $changes ?? self::collectSnapshot($object),
            $userId
        );
    }

    /* ------------------------------------------------------------------ */
    /* payload builders                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Columns whose current value differs from the loaded (old) value.
     * @return array<int, array{field:string, old_value:mixed, new_value:mixed}>
     */
    public static function collectChanges(BaseObject $object): array
    {
        $changes = [];
        foreach ($object->getTable()->getFields() as $field) {
            if (in_array($field->getName(), self::SKIPPED_FIELDS, true)) {
                continue;
            }
            if ($field->hasChanged()) {
                $changes[] = self::entry($field, $field->getOldValue(), $field->getValue());
            }
        }
        return $changes;
    }

    /**
     * Values written by an insert: non-key columns that are not null.
     * @return array<int, array{field:string, old_value:mixed, new_value:mixed}>
     */
    public static function collectInsertedValues(BaseObject $object): array
    {
        $changes = [];
        foreach ($object->getTable()->getFields() as $field) {
            if (in_array($field->getName(), self::SKIPPED_FIELDS, true)) {
                continue;
            }
            if ($field->isPrimaryKey() || $field->isAutoIncrement()) {
                continue;
            }
            $value = $field->getValue();
            if ($value === null || $value === '') {
                continue;
            }
            $changes[] = self::entry($field, null, $value);
        }
        return $changes;
    }

    /**
     * Snapshot of every column (old value → null), used for hard deletes.
     * @return array<int, array{field:string, old_value:mixed, new_value:mixed}>
     */
    public static function collectSnapshot(BaseObject $object): array
    {
        $changes = [];
        foreach ($object->getTable()->getFields() as $field) {
            $changes[] = self::entry($field, $field->getValue(), null);
        }
        return $changes;
    }

    /**
     * JSON for the `changes` column. Never fails: invalid UTF-8 is substituted and
     * unencodable values are replaced, so an odd value cannot lose the whole row.
     */
    public static function encodeChanges(array $changes): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
        $json = json_encode(array_values($changes), $flags);
        return $json === false ? '[]' : $json;
    }

    /**
     * Value as stored in the payload: scalars and nulls pass through, booleans stay
     * booleans, binary columns become a size marker, dates become strings, long
     * strings are cut to getMaxValueLength().
     * @param mixed $value
     * @param Field|null $field
     * @return mixed
     */
    public static function normalizeValue(mixed $value, ?Field $field = null): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if ($field !== null && $field->hasType() && $field->getType() instanceof TypeBlob) {
            if (is_resource($value)) {
                return '[binary stream]';
            }
            return sprintf('[binary, %d bytes]', strlen((string)$value));
        }
        if (is_resource($value)) {
            $contents = @stream_get_contents($value);
            return $contents === false ? '[stream]' : self::truncate($contents);
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_array($value)) {
            return array_map(function ($item) {
                return self::normalizeValue($item);
            }, $value);
        }
        if (is_object($value)) {
            if ($value instanceof \JsonSerializable) {
                return $value;
            }
            if (method_exists($value, '__toString')) {
                return self::truncate((string)$value);
            }
            return '[object ' . get_class($value) . ']';
        }
        return self::truncate((string)$value);
    }

    /**
     * Primary key value(s) as the string stored in record_id.
     * @param int|string|bool|array|null $recordId
     */
    public static function recordIdToString(mixed $recordId): string
    {
        if (is_array($recordId)) {
            return implode(',', array_map(function ($v) {
                return is_bool($v) ? ($v ? '1' : '0') : (string)$v;
            }, $recordId));
        }
        if (is_bool($recordId)) {
            return $recordId ? '1' : '0';
        }
        return (string)$recordId;
    }

    /**
     * The user to attribute a change to: the explicitly set active user, else the
     * application's Person::getLoggedUserId() when such a class exists.
     */
    public static function resolveUserId(): ?int
    {
        $userId = BaseObject::getActiveUserId();
        if ($userId !== null) {
            return $userId;
        }
        $package = self::setting('project.package_name', null);
        if ($package === null) {
            return null;
        }
        $personClass = '\\' . $package . '\\Person';
        if (!class_exists($personClass) || !method_exists($personClass, 'getLoggedUserId')) {
            return null;
        }
        try {
            $loggedUserId = $personClass::getLoggedUserId();
        } catch (\Throwable $e) {
            return null;
        }
        return is_numeric($loggedUserId) ? (int)$loggedUserId : null;
    }

    /* ------------------------------------------------------------------ */
    /* reading                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * History of one record, newest first.
     * @param string $tableName
     * @param int|string|array $recordId
     * @param int $limit 0 = no limit
     * @param int $offset
     * @return AuditLog[]
     */
    public static function findForRecord(string $tableName, $recordId, int $limit = 100, int $offset = 0): array
    {
        return self::findBy(['table_name' => $tableName, 'record_id' => self::recordIdToString($recordId)], $limit, $offset);
    }

    /**
     * History of a loaded object, newest first.
     * @return AuditLog[]
     */
    public static function findForObject(BaseObject $object, int $limit = 100, int $offset = 0): array
    {
        return self::findForRecord($object->getTable()->getName(), $object->getRecordId(), $limit, $offset);
    }

    /**
     * Everything a user did, newest first.
     * @return AuditLog[]
     */
    public static function findForUser(int $userId, int $limit = 100, int $offset = 0): array
    {
        return self::findBy(['user_id' => $userId], $limit, $offset);
    }

    /**
     * Number of audit rows for one record.
     * @param int|string|array $recordId
     */
    public static function countForRecord(string $tableName, $recordId): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . Identifier::quote(self::$tableName)
            . ' WHERE table_name = :table_name AND record_id = :record_id';
        return (int)Db::getOne($sql, [':table_name' => $tableName, ':record_id' => self::recordIdToString($recordId)]);
    }

    /**
     * Rows matching every criterion, newest first (ORDER BY id DESC).
     * @param array<string, scalar|null> $criteria column => value
     * @return AuditLog[]
     */
    public static function findBy(array $criteria, int $limit = 100, int $offset = 0): array
    {
        $probe = new static();
        $where = [];
        $params = [];
        foreach ($criteria as $column => $value) {
            if (!$probe->getTable()->hasField($column)) {
                throw new \InvalidArgumentException(sprintf('audit_log has no column "%s"', $column));
            }
            $column = Identifier::sanitize($column);
            if ($value === null) {
                $where[] = Identifier::quote($column) . ' IS NULL';
            } else {
                $where[] = Identifier::quote($column) . ' = :' . $column;
                $params[':' . $column] = $value;
            }
        }
        $sql = 'SELECT * FROM ' . Identifier::quote(self::$tableName);
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int)$limit;
            if ($offset > 0) {
                $sql .= ' OFFSET ' . (int)$offset;
            }
        }

        $db = Db::getInstance();
        $stmt = $db->prepare($sql);
        foreach ($params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[] = static::fromRow($row);
        }
        return $rows;
    }

    /**
     * Build an entry from a fetched row without a second SELECT.
     * @param array<string, mixed> $row
     * @return static
     */
    public static function fromRow(array $row): static
    {
        $entry = new static();
        foreach ($row as $column => $value) {
            if ($entry->getTable()->hasField($column)) {
                $entry->getTable()->getField($column)->loadValueFromDb($value);
            }
        }
        $entry->setRecordId($row['id'] ?? null);
        $entry->setRecordSelected(true);
        return $entry;
    }

    /**
     * The decoded change list of this entry.
     * @return array<int, array{field:string|null, old_value:mixed, new_value:mixed}>
     */
    public function getChanges(): array
    {
        $raw = $this->getFieldValue('changes');
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        // JSONB (PostgreSQL) stores object keys in its own order; hand them back in the documented order
        $changes = [];
        foreach ($decoded as $change) {
            if (!is_array($change)) {
                continue;
            }
            $changes[] = [
                'field' => $change['field'] ?? null,
                'old_value' => $change['old_value'] ?? null,
                'new_value' => $change['new_value'] ?? null,
            ] + $change;
        }
        return $changes;
    }

    /**
     * The change recorded for one column of this entry, null when the column was not touched.
     * @return array{field:string|null, old_value:mixed, new_value:mixed}|null
     */
    public function getChangeFor(string $fieldName): ?array
    {
        foreach ($this->getChanges() as $change) {
            if (($change['field'] ?? null) === $fieldName) {
                return $change;
            }
        }
        return null;
    }

    /**
     * Names of the columns this entry touched.
     * @return string[]
     */
    public function getChangedFieldNames(): array
    {
        return array_values(array_filter(array_map(function ($change) {
            return $change['field'] ?? null;
        }, $this->getChanges())));
    }

    public function getIdVal(): mixed
    {
        return $this->getFieldValue('id');
    }

    public function getUserIdVal(): mixed
    {
        return $this->getFieldValue('user_id');
    }

    public function getTableNameVal(): mixed
    {
        return $this->getFieldValue('table_name');
    }

    public function getRecordIdVal(): mixed
    {
        return $this->getFieldValue('record_id');
    }

    public function getActionVal(): mixed
    {
        return $this->getFieldValue('action');
    }

    public function getChangesVal(): mixed
    {
        return $this->getFieldValue('changes');
    }

    public function getCreatedOnVal(): mixed
    {
        return $this->getFieldValue('created_on');
    }

    public function isInsert(): bool
    {
        return $this->getActionVal() === self::ACTION_INSERT;
    }

    public function isUpdate(): bool
    {
        return $this->getActionVal() === self::ACTION_UPDATE;
    }

    public function isDelete(): bool
    {
        return $this->getActionVal() === self::ACTION_DELETE;
    }

    /* ------------------------------------------------------------------ */
    /* internals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param mixed $old
     * @param mixed $new
     * @return array{field:string, old_value:mixed, new_value:mixed}
     */
    private static function entry(Field $field, $old, $new): array
    {
        return [
            'field' => $field->getName(),
            'old_value' => self::normalizeValue($old, $field),
            'new_value' => self::normalizeValue($new, $field),
        ];
    }

    private static function truncate(string $value): string
    {
        $max = self::getMaxValueLength();
        if ($max > 0 && mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max) . sprintf('…[+%d chars]', mb_strlen($value) - $max);
        }
        return $value;
    }

    /**
     * The prepared INSERT, kept for the lifetime of the connection.
     */
    private static function statement(Db $db): \PDOStatement
    {
        $connection = $db->getConnection();
        $key = spl_object_id($connection) . ':' . self::$tableName;
        if (self::$statement === null || self::$statement['key'] !== $key) {
            $sql = 'INSERT INTO ' . Identifier::quote(self::$tableName)
                . ' (user_id, table_name, record_id, action, changes, created_on)'
                . ' VALUES (:user_id, :table_name, :record_id, :action, :changes, :created_on)';
            $stmt = $connection->prepare($sql);
            self::$statement = ['key' => $key, 'statement' => $stmt];
        }
        return self::$statement['statement'];
    }

    /**
     * Remember and report a failed write; throws in strict mode.
     * @throws \RuntimeException
     */
    private static function fail(string $message, ?\Throwable $previous = null): bool
    {
        self::$lastError = $message;
        try {
            $logger = Db::getInstance()->getLogger();
            if ($logger === null) {
                error_log($message);
            } else {
                $logger->error($message);
            }
        } catch (\Throwable $ignored) {
            error_log($message);
        }
        if (self::$strict) {
            throw new \RuntimeException($message, 0, $previous);
        }
        return false;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function setting(string $key, mixed $default): mixed
    {
        try {
            $settings = Settings::getInstance();
            return $settings->propertyExists($key) ? $settings->getProperty($key) : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
