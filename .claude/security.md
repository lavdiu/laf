# LAF Security Notes

## Fixed Issues
- **ForeignKey::isValidValue()** — was using string concatenation for SQL, now uses prepared statements with sanitized identifiers
- **Db::handleSqlErrorLogging()** — table name now sanitized via regex whitelist before SQL interpolation

## Remaining Issues

### XSS
- **SimpleTable.php line ~324** — cell values rendered without `htmlentities()`, DB results go straight to HTML
- **SimpleTable.php button substitution** — row data substituted into button URLs without URL encoding
- ~~**Form.php** — inline jQuery in onclick attributes~~ fixed 2026-10-11: vanilla JS, attributes escaped

### Missing Protections
- ~~**No CSRF tokens**~~ fixed: `Form::processForm()` validates `_csrf_token_{form}` against a per-session token (`CsrfTokenException`); `setCsrfProtection(false)` opts out
- **ROT13 "security"** — used for field name encoding (`Util::scrambleFieldOrTableName()`) and form table name obfuscation — not real security

### Query (replaced QueryBuilder, 2026-10-11)
- Column names are checked against `Table::hasField()` and quoted by `Identifier`; operators and sort directions are whitelisted
- Values are bound through the column's `FieldType::toDb()` (never interpolated); `whereRaw()` is the only trusted-SQL entry point
- `BaseObject::bOfind/bOfindOne/findAsArray` refuse unknown column names (return empty) instead of interpolating them

### SimpleTable
- `getSqlOrderBySection()` — column name from `getSortColumns()` not validated
- `setSql()` — raw SQL passthrough, executed via `$db->query()` without prepared statement

### Good Practices Already Present
- PDO prepared statements used correctly in most BaseObject methods
- Form inputs escape labels, hints, values, attributes and the form action with `Util::e()` / `Util::attr()`
- `bOfind()`/`bOfindOne()` validate field names with regex + `hasField()` check
- PDO configured with `ERRMODE_EXCEPTION` and `EMULATE_PREPARES=false`
