
-- Create audit_logs table for application-level logging
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `table_name` VARCHAR(100) NOT NULL,
    `record_id` VARCHAR(50) NOT NULL,
    `action` ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    `changes` TEXT NOT NULL,
    `created_on` DATETIME NOT NULL,
    INDEX `idx_audit_logs_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_logs_user_id` (`user_id`),
    INDEX `idx_audit_logs_created_on` (`created_on`),
    INDEX `idx_audit_logs_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
