<?php

namespace Laf\Database;

use Laf\Database\Field\Field;
use Laf\Database\Field\FieldType;

class AuditLog extends BaseObject
{
    const ACTION_INSERT = 'INSERT';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_DELETE = 'DELETE';

    /**
     * @var Table
     */
    protected static $table;

    public function __construct($id = null)
    {
        parent::__construct($id);
        $this->setTable(self::getTableStatic());
    }

    /**
     * Log an audit entry
     * @param string $action
     * @param string $tableName
     * @param int|string $recordId
     * @param array $changes
     * @param int|null $userId
     * @return bool
     */
    public static function logChange(string $action, string $tableName, $recordId, array $changes = [], ?int $userId = null): bool
    {
        try {
            $auditLog = new self();
            
            $auditLog->setFieldValue('user_id', $userId);
            $auditLog->setFieldValue('table_name', $tableName);
            $auditLog->setFieldValue('record_id', (string)$recordId);
            $auditLog->setFieldValue('action', $action);
            $auditLog->setFieldValue('changes', json_encode($changes));
            $auditLog->setFieldValue('created_at', date('Y-m-d H:i:s'));
            
            return $auditLog->insert();
        } catch (\Exception $e) {
            // Log error but don't break the main operation
            error_log("AuditLog failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log an insert operation
     * @param BaseObject $object
     * @param int|null $userId
     * @return bool
     */
    public static function logInsert(BaseObject $object, ?int $userId = null): bool
    {
        $changes = [];
        foreach ($object->getTable()->getFields() as $field) {
            if (!$field->isPrimaryKey() || !$field->isAutoIncrement()) {
                $changes[] = [
                    'field' => $field->getName(),
                    'old_value' => null,
                    'new_value' => $field->getValue()
                ];
            }
        }

        return self::logChange(
            self::ACTION_INSERT,
            $object->getTable()->getName(),
            $object->getRecordId(),
            $changes,
            $userId
        );
    }

    /**
     * Log an update operation - only for changed fields
     * @param BaseObject $object
     * @param int|null $userId
     * @return bool
     */
    public static function logUpdate(BaseObject $object, ?int $userId = null): bool
    {
        $changes = [];
        foreach ($object->getTable()->getFields() as $field) {
            if ($field->hasChanged()) {
                $changes[] = [
                    'field' => $field->getName(),
                    'old_value' => $field->getOldValue(),
                    'new_value' => $field->getValue()
                ];
            }
        }

        if (empty($changes)) {
            return true; // No changes to log
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
     * Log a delete operation
     * @param BaseObject $object
     * @param int|null $userId
     * @return bool
     */
    public static function logDelete(BaseObject $object, ?int $userId = null): bool
    {
        $changes = [];
        foreach ($object->getTable()->getFields() as $field) {
            $changes[] = [
                'field' => $field->getName(),
                'old_value' => $field->getValue(),
                'new_value' => null
            ];
        }

        return self::logChange(
            self::ACTION_DELETE,
            $object->getTable()->getName(),
            $object->getRecordId(),
            $changes,
            $userId
        );
    }

    /**
     * Get the table definition for audit_logs
     * @return Table
     */
    public static function getTableStatic(): Table
    {
        if (self::$table === null) {
            self::$table = new Table('audit_logs');
            
            // Primary key
            $field = new Field();
            $field->setName('id')
                ->setType(new FieldType(FieldType::TYPE_INTEGER))
                ->setIsPrimaryKey(true)
                ->setIsAutoIncrement(true)
                ->setIsRequired(true);
            self::$table->addField($field);

            // User ID
            $field = new Field();
            $field->setName('user_id')
                ->setType(new FieldType(FieldType::TYPE_INTEGER))
                ->setIsRequired(false); // Allow null for system operations
            self::$table->addField($field);

            // Table name
            $field = new Field();
            $field->setName('table_name')
                ->setType(new FieldType(FieldType::TYPE_VARCHAR))
                ->setMaxLength(100)
                ->setIsRequired(true);
            self::$table->addField($field);

            // Record ID
            $field = new Field();
            $field->setName('record_id')
                ->setType(new FieldType(FieldType::TYPE_VARCHAR))
                ->setMaxLength(50)
                ->setIsRequired(true);
            self::$table->addField($field);

            // Action type
            $field = new Field();
            $field->setName('action')
                ->setType(new FieldType(FieldType::TYPE_VARCHAR))
                ->setMaxLength(10)
                ->setIsRequired(true);
            self::$table->addField($field);

            // Changes JSON
            $field = new Field();
            $field->setName('changes')
                ->setType(new FieldType(FieldType::TYPE_TEXT))
                ->setIsRequired(true);
            self::$table->addField($field);

            // Created at timestamp
            $field = new Field();
            $field->setName('created_at')
                ->setType(new FieldType(FieldType::TYPE_DATETIME))
                ->setIsRequired(true);
            self::$table->addField($field);
        }

        return self::$table;
    }

    /**
     * Get table instance
     * @return Table
     */
    public function getTable(): Table
    {
        return self::getTableStatic();
    }
}
