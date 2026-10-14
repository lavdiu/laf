-- Example schema used throughout the LAF documentation (MySQL / MariaDB).
--   company           a standalone table
--   employee          has a foreign key to company (one company per employee)
--   project           a standalone table
--   employee_project  linker table: employees <-> projects, many-to-many (marked with the table comment
--                     {"linker": true}, so the generator gives both forms a multi-value picker)
-- audit_log comes from migrations/create_audit_logs_table.sql (optional, enables the audit log).

CREATE TABLE `company` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `vat_number` varchar(20) DEFAULT NULL COMMENT '{"fields":{"setLabel":"VAT number","setHint":"Leave empty for private persons"}}',
  `email` varchar(150) DEFAULT NULL COMMENT '{"fields":{"setLabel":"E-mail"},"form":{"group":"Contact"}}',
  `phone` varchar(30) DEFAULT NULL COMMENT '{"form":{"group":"Contact"}}',
  `record_status_id` tinyint(4) NOT NULL DEFAULT 1 COMMENT '{"fields":{"setRequired":false}}',
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `employee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL COMMENT '{"fields":{"setLabel":"Company"}}',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL COMMENT '{"fields":{"setLabel":"E-mail","setDisplayField":true}}',
  `birth_date` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '{"fields":{"setLabel":"Active","setRequired":false}}',
  `notes` text DEFAULT NULL COMMENT '{"form":{"tab":"Notes"}}',
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_email_unique` (`email`),
  KEY `employee_company_fk` (`company_id`),
  CONSTRAINT `employee_company_fk` FOREIGN KEY (`company_id`) REFERENCES `company` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `project` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `starts_on` date DEFAULT NULL,
  `budget` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `employee_project` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL COMMENT '{"fields":{"setLabel":"Team members"}}',
  `project_id` int(11) NOT NULL COMMENT '{"fields":{"setLabel":"Projects"}}',
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_project_unique` (`employee_id`, `project_id`),
  KEY `employee_project_project_fk` (`project_id`),
  CONSTRAINT `employee_project_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_project_project_fk` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='{"linker": true}';
