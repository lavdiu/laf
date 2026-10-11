-- audit_log table for PostgreSQL (MySQL/MariaDB version: create_audit_logs_table.sql)
-- Written by Laf\Database\AuditLog for every INSERT / UPDATE / DELETE done through BaseObject.
CREATE TABLE IF NOT EXISTS audit_log (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER NULL,
    table_name  VARCHAR(100) NOT NULL,
    record_id   VARCHAR(50) NOT NULL,
    action      VARCHAR(10) NOT NULL CHECK (action IN ('INSERT', 'UPDATE', 'DELETE')),
    changes     JSONB NOT NULL,
    created_on  TIMESTAMP NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_table_record ON audit_log (table_name, record_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_log (user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_on ON audit_log (created_on);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_log (action);
