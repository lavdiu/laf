# LAF Project Memory

## Project
- **LAF** (Lulzim Application Framework) - PHP 8+ rapid app dev framework
- Database-First Active Record Pattern with Code Generation
- Supports MySQL/MariaDB and PostgreSQL
- Located at `D:\www\laf`, MIT license, author: Lulzim (l@orav.net)
- **17,633 lines** across 102 source files
- CLAUDE.md has comprehensive architecture docs

## Key Rules
- Never modify `Base/` directories - they are auto-generated
- Two-tier inheritance: BaseObject → Base[Table] → [Table]
- **Never commit unless explicitly asked**

## Structure → see [architecture.md](architecture.md)
## Security → see [security.md](security.md)
## Grid Callback System → see [grid-callbacks.md](grid-callbacks.md)
## Code Quality → see [code-quality.md](code-quality.md)

## Dependencies
- `claviska/simpleimage` ^4.2, `monolog/monolog` ^1.24 (outdated, v3 current)
- `openspout/openspout` ^4.13, `phpoffice/phpspreadsheet` ^1.10
- Dev: phpstan ^2.1, phpunit ^9.5, rector ^2.1

## Testing Status
- 1 test file: `tests/BaseObjectTest.php` (~200 lines, 20 methods)
- Covers basic CRUD, field types, soft/hard delete, unique constraints
- NOT tested: UI components, generators, forms, audit logging, routing
- phpunit.xml bootstrap points to `app/config/config.php` (doesn't exist in repo)
- PHPStan and Rector installed but NOT configured (no config files)

## Sample Project
- `sample_project/asm/` — full ERP system (564 files, Albanian language)
- Multi-environment (Dev, Live, Test, Docker), uses `object_list` table pattern
- Proves framework works at production scale

## Changes Made (by Claude)
- Fixed SQL injection in `ForeignKey::isValidValue()` — now uses prepared statements
- Fixed SQL injection in `Db::handleSqlErrorLogging()` — sanitizes table name
- Added `BaseObject::populateTableObjectList()` — creates/populates `object_list` table
- Added `sample_files/populate_table_object_list.php` — usage example
