-- Example schema used throughout the LAF documentation (PostgreSQL).
-- Same tables as schema.mysql.sql; column comments are set with COMMENT ON COLUMN.
-- audit_log comes from migrations/create_audit_logs_table_postgres.sql (optional).

CREATE TABLE company (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  vat_number VARCHAR(20),
  email VARCHAR(150),
  phone VARCHAR(30),
  record_status_id SMALLINT NOT NULL DEFAULT 1,
  created_on TIMESTAMP,
  created_by INTEGER,
  updated_on TIMESTAMP,
  updated_by INTEGER,
  CONSTRAINT company_name_unique UNIQUE (name)
);
COMMENT ON COLUMN company.vat_number IS '{"fields":{"setLabel":"VAT number","setHint":"Leave empty for private persons"}}';
COMMENT ON COLUMN company.email IS '{"fields":{"setLabel":"E-mail"},"form":{"group":"Contact"}}';
COMMENT ON COLUMN company.phone IS '{"form":{"group":"Contact"}}';
COMMENT ON COLUMN company.record_status_id IS '{"fields":{"setRequired":false}}';

CREATE TABLE employee (
  id SERIAL PRIMARY KEY,
  company_id INTEGER REFERENCES company (id),
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(150) NOT NULL,
  birth_date DATE,
  salary NUMERIC(10,2),
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  notes TEXT,
  created_on TIMESTAMP,
  created_by INTEGER,
  updated_on TIMESTAMP,
  updated_by INTEGER,
  CONSTRAINT employee_email_unique UNIQUE (email)
);
COMMENT ON COLUMN employee.company_id IS '{"fields":{"setLabel":"Company"}}';
COMMENT ON COLUMN employee.email IS '{"fields":{"setLabel":"E-mail","setDisplayField":true}}';
COMMENT ON COLUMN employee.is_active IS '{"fields":{"setLabel":"Active","setRequired":false}}';
COMMENT ON COLUMN employee.notes IS '{"form":{"tab":"Notes"}}';

CREATE TABLE project (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  starts_on DATE,
  budget NUMERIC(12,2),
  CONSTRAINT project_name_unique UNIQUE (name)
);

CREATE TABLE employee_project (
  id SERIAL PRIMARY KEY,
  employee_id INTEGER NOT NULL REFERENCES employee (id) ON DELETE CASCADE,
  project_id INTEGER NOT NULL REFERENCES project (id) ON DELETE CASCADE,
  CONSTRAINT employee_project_unique UNIQUE (employee_id, project_id)
);
COMMENT ON TABLE employee_project IS '{"linker": true}';
COMMENT ON COLUMN employee_project.employee_id IS '{"fields":{"setLabel":"Team members"}}';
COMMENT ON COLUMN employee_project.project_id IS '{"fields":{"setLabel":"Projects"}}';
