# AGENTS.md — LAF Framework Directives & Memory

## Project Overview
**LAF (Lulzim Application Framework)** is a PHP 8+ rapid application development (RAD) library using a **Database-First Active Record Pattern with Code Generation**. It reverse-engineers database schemas (MySQL/MariaDB and PostgreSQL) to generate two-tier Active Record models, CRUD pages (`.page`), and model-driven forms.

## Two-Tier Class Hierarchy (Critical Invariant)
```
BaseObject (Abstract ORM Core in src/Laf/Database/)
    ↓
Base/Base[TableName].php (Auto-generated from DB schema — NEVER EDIT MANUALLY)
    ↓
[TableName].php (Developer customizations and domain logic — edit here)
```
> [!IMPORTANT]
> Never edit files in `Base/` subdirectories. They are regenerated automatically when database schemas change. All custom methods, overrides, and business logic belong in the leaf class files.

---

## Essential Commands

### 1. Testing
```bash
# Unit suite (no database required; must run under E_ALL warning-free on PHP 8.5)
php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit

# Integration suite against MariaDB / MySQL
LAF_DB_ENGINE=mysql LAF_DB_HOST=127.0.0.1 LAF_DB_PORT=3306 LAF_DB_NAME=laf_test LAF_DB_USER=root LAF_DB_PASS= \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration

# Integration suite against PostgreSQL
LAF_DB_ENGINE=postgres LAF_DB_PORT=5432 LAF_DB_USER=postgres LAF_DB_PASS=postgres \
    php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Integration

# Single test file
vendor/bin/phpunit tests/BaseObjectTest.php
```

### 2. Static Analysis & Code Quality
```bash
# PHPStan Level 5 on src/ (must pass with 0 errors)
vendor/bin/phpstan analyse

# Rector (advisory only, dry-run)
vendor/bin/rector process --dry-run
```

### 3. Code Generation CLI
```bash
# Generate base classes and pages
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --lib=app/lib --install-pages

# Dry run (list files that would be generated without writing)
vendor/bin/laf-generate --bootstrap=app/bootstrap.php --list
```

---

## Architecture & Coding Guidelines

1. **Dual Database Engine Parity (MySQL/MariaDB & PostgreSQL)**:
   - Always quote identifiers using `Laf\Database\Identifier::quote($name)` (backticks for MySQL, double quotes for PostgreSQL).
   - In PostgreSQL, queries are case-sensitive. Use `LOWER()` or `ILIKE` for case-insensitive searches, and cast non-textual columns (`CAST(col AS TEXT)`) before calling text functions.
   - Primary key retrieval on INSERT uses `RETURNING` for PostgreSQL and `LAST_INSERT_ID()` for MySQL.

2. **No Hard Exits in Library Code**:
   - Avoid `exit;` or `die;` inside `src/Laf/` classes. Return output strings or use non-terminating parameters (`$terminate = false`) to maintain testability and worker-runtime compatibility.

3. **Active Record & Query Rules**:
   - Use `store()` for smart write dispatch (`insert()` when `isNew()`, `update()` when dirty).
   - Unset nullable columns are omitted from `INSERT` to allow database defaults to apply.
   - Use `Customer::query()` for fluent single-table queries (`where()`, `whereIn()`, `orderBy()`, `limit()`, `get()`, `first()`, `count()`, `exists()`).
   - Group writes using `BaseObject::transaction(fn() => ...)`.

4. **Form & Security Standards**:
   - Form operations must validate CSRF tokens via `Form::withCsrfToken()` and `Form::isValidCsrfToken()`.
   - Values rendered in HTML must be escaped using `Laf\Util\Util::e()` unless explicitly configured as raw HTML (`allowHtml`).
   - Audit logging is automatic for inserts, updates, and deletes via `AuditLog` when `BaseObject::setActiveUserId()` is populated.

5. **Backwards Compatibility**:
   - Maintain compatibility for client applications (`asm`, `routerarena`).
   - Preserve existing public API methods (`bOfind`, `bOfindOne`, `get[Field]Val()`, `set[Field]Val()`).
   - Do not add `declare(strict_types=1)` to legacy files accepting loose types from client applications.
