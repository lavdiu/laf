<?php

/**
 * Audit logging with Laf\Database\BaseObject and Laf\Database\AuditLog.
 *
 * Every INSERT / UPDATE / DELETE done through a BaseObject model writes one row
 * to the `audit_log` table:
 *   - migrations/create_audit_logs_table.sql           (MySQL / MariaDB)
 *   - migrations/create_audit_logs_table_postgres.sql  (PostgreSQL)
 *
 * Run inside a bootstrapped application (Settings + autoloader configured).
 */

use Laf\Database\AuditLog;
use Laf\Database\BaseObject;

// 1. Who is making the changes. Set once per request (e.g. right after login is resolved).
//    Without it, AuditLog falls back to <package>\Person::getLoggedUserId() when that class exists.
BaseObject::setActiveUserId($_SESSION['user_id'] ?? null);

// 2. Normal model work is audited automatically (User is a generated model here).
// $user = new User();
// $user->setNameVal('John Doe');
// $user->setEmailVal('john@example.com');
// $user->store();                              // INSERT entry: every column that received a value
//
// $user->setEmailVal('john.doe@example.com');
// $user->store();                              // UPDATE entry: only email, with old and new value
// $user->store();                              // nothing: the object is clean after a successful write
//
// $user->softDelete();                         // one DELETE entry carrying the status column change
// $user->hardDelete();                         // DELETE entry with a snapshot of every column

// 3. Switching it off
// $import = new User();
// $import->auditLogDisable();                  // this object only
// AuditLog::disable();                         // the whole process (bulk imports, migrations)
// AuditLog::enable();

// 4. Failure policy: a failed audit write never breaks the business write.
//    It is reported through the Db logger / error_log and kept in AuditLog::getLastError().
//    For installations where a lost audit row must stop the request:
// AuditLog::setStrict(true);

// 5. Payload size: cut long text values in the payload (0 = unlimited; also Settings 'audit.max_value_length')
// AuditLog::setMaxValueLength(2000);

// 6. Reading the history
// foreach (AuditLog::findForObject($user) as $entry) {            // newest first; also findForRecord('user', 12)
//     printf("%s %s by user %s on %s\n",
//         $entry->getActionVal(), $entry->getRecordIdVal(), $entry->getUserIdVal() ?? '-', $entry->getCreatedOnVal());
//     foreach ($entry->getChanges() as $change) {
//         printf("  %s: %s -> %s\n",
//             $change['field'],
//             json_encode($change['old_value']),
//             json_encode($change['new_value']));
//     }
// }
// $emailChange = $entry->getChangeFor('email');                    // ['field' => .., 'old_value' => .., 'new_value' => ..] or null
// $count = AuditLog::countForRecord('user', 12);
// $byUser = AuditLog::findForUser(42, 50);
// $one = new AuditLog(1234);                                       // AuditLog is a regular model of the audit_log table

echo "Audit logging is wired into BaseObject::insert()/update()/hardDelete()/softDelete().\n";
echo "Create the audit_log table with the migration for your engine, then set BaseObject::setActiveUserId().\n";
