<?php

namespace Laf\Database;

use Laf\Database\Field\Field;
use Laf\Database\Field\FieldType;

class AuditLog extends BaseObject
{
    const ACTION_INSERT = 'INSERT';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_DELETE = 'DELETE';


    public function __construct($id = null)
    {
        parent::__construct($id);
        $table = new Table('audit_log');


        $this->auditLogDisable();
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
            $db = Db::getInstance();
            $sql = "
            INSERT INTO audit_log (user_id, table_name, record_id, action, changes, created_on) 
            VALUES (:user_id, :table_name, :record_id, :action, :changes, NOW());
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                ':user_id' => $userId,
                ':table_name' => $tableName,
                ':record_id' => $recordId,
                ':action' => $action,
                ':changes' => json_encode($changes)
            ]);

        } catch (\Throwable $e) {
            error_log("Failed to write to audit log: " . $e->getMessage());
        }
        return true;
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
            if (in_array($field->getName(), [
                'created_on',
                'created_by',
                'updated_on',
                'updated_by'
            ])) {
                continue;
            }
            if (!$field->isPrimaryKey() && !$field->isAutoIncrement()) {
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
            if (in_array($field->getName(), [
                'created_on',
                'created_by',
                'updated_on',
                'updated_by'
            ])) {
                continue;
            }

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

}
