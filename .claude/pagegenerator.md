# PageGenerator Improvements (2026-03-06)

## Bugs Fixed
1. Missing opening quote in TabContainer constructor (line ~195)
2. Table object passed as string to `buildGrid()` (line ~225)
3. `buildGrid()` used parent table name instead of grid's own table for ID detection and action buttons
4. Grid titles now use `Util::tableFieldNameToLabel()` instead of raw table names
5. Removed dead `$panel` variable and unused `Div` import
6. Fixed inconsistent backslash escaping in `use` statement
7. Removed redundant self-alias in FROM clause
8. Fixed missing space before subquery alias `) l1`

## Features Added

### Extracted `createTableInspector()` factory method
- Replaces 3 duplicated if/else blocks for MySQL vs Postgres inspector creation

### Record existence checks
- View/update actions check `recordExists()` before rendering
- Redirect to list with flash message if record not found

### Flash messages
- Labels: `record-saved`, `record-deleted`, `record-not-found`
- Uses `Alert` component (Type_Success / Type_Danger)
- Displayed after form save, delete success/failure, record not found
- Stored in `$_SESSION` and cleared after display

### Lazy-loading related grids in tabs
- `PhpGrid::setDeferInitialize(true)` — skips the `$(document).ready` auto-init script
- First tab initializes immediately, others defer until `shown.bs.tab` event
- Backwards compatible: `deferInitialize` defaults to `false`
- Grid JS (`grid.js`) already uses AJAX via `fetchJson()` in `initialize()`

### Many-to-many relationship detection
- `detectJunctionTable(tableName, currentTable)` — static method
- Heuristic: exactly 2 FKs + at most 2 non-FK/non-PK/non-metadata columns = junction table
- Metadata columns excluded: id, created_on, created_by, updated_on, updated_by, is_deleted
- Returns: `junction_table`, `other_table`, `fk_to_current`, `fk_to_other`

### `buildManyToManyGrid()` method
- Builds grid showing "other" table's columns (not junction table)
- SQL joins through junction table: `SELECT l1.* FROM (otherSql) l1 INNER JOIN junction jt ON ...`
- View-only action button (no update/delete)
- Tab label shows "other" table name (e.g., "Tags" not "OrderTags")

## Deferred Investigation
- Whether `populateReferencingTables()` should return FK column name (for tables with multiple FKs to same parent)
- User explicitly tabled this — do NOT proceed without asking

## Key Technical Notes
- Form.php already has CSRF token protection (prevents duplicate submissions inherently)
- TabItem pane IDs: `{TitleNoSpaces}-content`, link IDs: `{TitleNoSpaces}-tab`
- `window.grid` global registry in grid.js
- 356 unit tests pass after all changes
