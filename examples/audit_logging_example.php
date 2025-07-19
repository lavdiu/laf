<?php

/**
 * Example demonstrating the audit logging functionality in BaseObject
 * 
 * This example shows how to:
 * 1. Set up audit logging with static user ID
 * 2. Create, update, and delete records with automatic audit trail
 * 3. Query audit logs to see change history
 * 4. Use hasChanged() property for tracking only modified fields
 */

require_once '../src/Laf/Database/BaseObject.php';
require_once '../src/Laf/Database/AuditLog.php';

// Example usage of audit logging

// 1. Set the active user ID for all BaseObject instances (static property)
BaseObject::setActiveUserId(123); // User ID 123 for all operations

// 2. Create a sample object (assuming you have a User class extending BaseObject)
// $user = new User();
// $user->setFieldValue('name', 'John Doe');
// $user->setFieldValue('email', 'john@example.com');
// $user->insert(); // This will automatically log the INSERT operation

// 3. Update the object - only changed fields will be logged
// $user->setFieldValue('email', 'john.doe@example.com'); // This field will be marked as changed
// $user->update(); // Only the email field change will be logged in audit_logs

// 4. Soft delete (uses existing softDelete method)
// $user->softDelete(); // This will log as DELETE operation

// 5. Hard delete (uses existing hardDelete method)
// $user->hardDelete(); // This will log as DELETE operation with all field values

// 6. Disable audit logging for specific operations
// $user = new User();
// $user->setAuditLoggingEnabled(false);
// $user->setFieldValue('name', 'Silent User');
// $user->insert(); // This will NOT be logged

// 7. Change active user ID for subsequent operations
// BaseObject::setActiveUserId(456); // Now all operations will use user ID 456

// 8. Query audit logs
// $auditLogs = AuditLog::bOfind(['table_name' => 'users', 'record_id' => '1']);
// foreach ($auditLogs as $log) {
//     echo "Action: " . $log->getFieldValue('action') . "\n";
//     echo "User ID: " . $log->getFieldValue('user_id') . "\n";
//     echo "Changes: " . $log->getFieldValue('changes') . "\n";
//     echo "Date: " . $log->getFieldValue('created_at') . "\n\n";
//     
//     // Parse changes JSON to see individual field changes
//     $changes = json_decode($log->getFieldValue('changes'), true);
//     foreach ($changes as $change) {
//         echo "  Field: " . $change['field'] . "\n";
//         echo "  Old Value: " . ($change['old_value'] ?? 'NULL') . "\n";
//         echo "  New Value: " . ($change['new_value'] ?? 'NULL') . "\n";
//     }
// }

echo "Audit logging system is ready to use!\n";
echo "Features implemented:\n";
echo "- Static property BaseObject::setActiveUserId() for all instances\n";
echo "- Uses existing hardDelete() and softDelete() methods\n";
echo "- Uses hasChanged() property to track only modified fields\n";
echo "- Automatic logging for INSERT, UPDATE, DELETE operations\n";
echo "- JSON storage of field-level changes with old/new values\n";
echo "\nMake sure to run the SQL migration to create the audit_logs table.\n";
echo "See: migrations/create_audit_logs_table.sql\n";
