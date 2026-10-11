Change notes for the FieldType layer rewrite on `improvements1` (uncommitted working tree): what changed, what stays compatible, what behaves differently, and how it was verified.

# FieldType rewrite — notes and compatibility

Branch: `improvements1`, working tree only (nothing committed). 34 files changed, 6 new. Companion documents: `2026-10-11-fieldtype-review.md` (the findings this fixes) and `2026-10-11-architecture-map.md` §2.1 (where the types sit).

## 1. What changed

### New contract (`src/Laf/Database/Field/FieldType.php`)
One method per boundary, all implemented once in `AbstractFieldType`:

| Method | Direction | Notes |
|---|---|---|
| `normalize(mixed): mixed` | any PHP value → canonical | throws `InvalidValueException`; empty (`null`, `''`) → `null`; `TypeBool` maps `''` → `false` |
| `fromInput(?string): mixed` | form text → canonical | accepts locale display formats; throws |
| `fromDb(mixed): mixed` | PDO raw → canonical | never throws, unparseable values pass through |
| `toDb(mixed): [value, PDO::PARAM_*]` | canonical → bound parameter | `null` → `PARAM_NULL`; used by **both** `insert()` and `update()` |
| `equals(a, b): bool` | change tracking | `"2.10"` equals `"2.1"`, `"1"` equals `true`, `null` ≠ `false` |
| `validate(value, ?Field): string[]` | constraints | length, integer range/sign, decimal precision, field min/max |
| `getInputAttributes(?Field): array` | HTML | `step`, `min`, `max`, `inputmode`, `pattern` |
| `generatePrimaryKeyValue(): ?string` | insert | `TypeUuid`/string types return a v4 UUID |
| `getTypeName(): string` | — | `'integer'`, `'decimal'`, `'bool'`, … |

Subclasses implement only `convert()` (one non-empty value), `getPdoType()`, `createFormElement()`.

The five legacy methods (`isValid`, `getValueDbSanitized`, `formatForDb`, `getPdoType`, `getFormElement`) are kept and delegate to the typed ones. `isValid` returns bool, `getValueDbSanitized`/`formatForDb` return `null` on invalid input exactly as before.

### Types
| Type | Canonical PHP value | New / changed |
|---|---|---|
| `TypeVarchar`, `TypeChar`, `TypeText` | trimmed string | **no more `strip_tags` + `htmlspecialchars` at storage** (see §3); `validate()` checks `maxLength`; `Char`/`Text` now extend `Varchar` |
| `TypeInteger(size, unsigned)` | `int` (digit string beyond PHP_INT range) | rejects `"1.7"` instead of truncating; range per `tinyint`/`smallint`/`mediumint`/`int`/`bigint`/`year`, signed/unsigned; `bigint unsigned` round-trips as string |
| `TypeDecimal(precision, scale)` **new** | numeric string `"1234.50"` | half-up rounding to scale (bcmath when available), locale parsing, exact equality, `step="0.01"` |
| `TypeFloat` | `float` | locale parsing; bound as a `%.15G` string so `precision` ini never truncates |
| `TypeBool` | `bool` | accepts `0/1`, `"0"/"1"`, `true/false`, `yes/no`, `on/off`, `y/n`, `t/f`; **bound as `PARAM_INT` 0/1**, so `false` is no longer written as NULL |
| `TypeDate` | `'Y-m-d'` | accepts ISO, datetime (time dropped), `DateTimeInterface`, locale display format; `'!'` parsing so missing parts are zero, not "now" |
| `TypeDateTime(withTimezone)` | `'Y-m-d H:i:s[.u][P]'` | accepts `T` separator, no seconds, fractional seconds, offsets, bare date (midnight), locale format |
| `TypeTime` | `'H:i:s[.u]'` | accepts `H:i`, 12-hour with AM/PM, `''` |
| `TypeJson` | the JSON text | `''` → `null`; arrays/objects encoded; `decode()` helper; order-insensitive equality |
| `TypeBlob` | bytes | reads PostgreSQL `bytea` stream resources; never trimmed |
| `TypeEnum(options, multiple)` **new** | declared label, or comma list for SET | case-insensitive match corrected to declared spelling; renders a `Select` with static options |
| `TypeUuid` **new** | lower-case hyphenated | accepts braces, upper case, 32-hex compact form; generates PK values |

### Factory (`FieldTypeFactory`)
One lookup table drives both `getClass()` and `getClassLiteral()`; both accept the full `information_schema` row as a second argument. Covered now: every MySQL/MariaDB and PostgreSQL type name listed in the interface constants plus `timestamp`, `mediumint`, `year`, `bit`, `enum`/`set` (options parsed from `COLUMN_TYPE`), `tinytext`/`mediumtext`/`longtext`, `tinyblob`/`mediumblob`/`longblob`/`binary`/`varbinary`/`bytea`, `boolean`, `jsonb`, `uuid`, `double precision`, `character`, `numeric(p,s)`, `money`, `timestamp with time zone`, `interval`/`inet`/`cidr`/`macaddr` (as varchar), `ARRAY`/`xml` (as text), `USER-DEFINED` (PostgreSQL enum when the inspector supplies `ENUM_OPTIONS`, otherwise text).

`tinyint(1)` and `bit(1)` → `TypeBool`; any other width → `TypeInteger`. A bare `tinyint` with no width information still maps to `TypeBool` (the generator's historical choice).

### Generator / inspectors
- `ClassGenerator` passes the column row to `getClassLiteral()`, so generated Base classes now emit `new Database\Field\TypeDecimal(12, 2)`, `new Database\Field\TypeInteger('tinyint', true)`, `new Database\Field\TypeEnum(['a', 'b'])`, `new Database\Field\TypeDateTime(true)`.
- `PostgresTableInspector` queries `pg_enum` for `USER-DEFINED` columns and adds `ENUM_OPTIONS`.

### ORM / form / UI
- `BaseObject::insert()` binds every value through `toDb()` (it used to pass raw values to `execute()`), generates string/UUID primary keys through the type (the old check compared a PDO int constant against type-name strings and could never match), and `update()` binds the primary key with the key's own type instead of hard-coded `PARAM_INT`.
- `Field::setValue()` → `normalize()`; `Field::loadValueFromDb()` → `fromDb()`; `Field::hasChanged()` → `equals()`; new `Field::validate()`, `Field::hasTable()`; `setTableDisplayField()` now calls the method that exists; `__toString()` handles bool/int/null.
- `Form::processForm()` uses `fromInput()`.
- `Select::setOptions()` for static lists; FK selection criteria are now bound parameters; no longer instantiates the referenced record just to read metadata.
- `Checkbox` renders from the field's bool; `Date`/`DateTime`/`Time` inputs read their display format from `Util\Locale` (same defaults as before) and leave unparseable values untouched instead of substituting "now".
- `FormElementTrait::setMin/setMax/setStep` accept `int|float|string|null` (so `step="0.01"` works).
- `UrlParser::getId()` accepts UUIDs.
- Pre-existing fatal fixed on the way: `addStyle()` → `addCssStyleItem()` in `Select`, `File`, `Button`, `Image`.

### `Util\Locale` (new)
Reads `locale.date.format`, `locale.datetime.format`, `locale.time.format`, `locale.decimal_separator`, `locale.thousands_separator` from `Settings` with the historical defaults (`'F d, Y'`, `'F d, Y H:i'`, `'H:i A'`, `'.'`, `','`). `parseDate()` is strict (`'!'` prefix, warnings count as failures); `parseNumber()` handles `1,234.56`, `1.234,56`, `1 234 567,89`, `1,5`, scientific notation, and rejects `1.2.3.4`.

### Output escaping (added after the automated security review)
Because storage no longer encodes, every sink that writes a value into markup now escapes it:
- `Util::e()` (text and single-quoted contexts, `ENT_QUOTES`, no double-encoding so legacy `&amp;` rows still display as `&`): `TextArea` content, `<option>` values/labels in `Select` and `Radio`, `Field::getValueNl2Br()`, `Text::getValueForHtml()` / `FormElementTrait::getValueForHtml()` (replacing `htmlentities`, which double-encoded legacy rows).
- `Util::attr()` (double-quoted attributes, `ENT_COMPAT`, single quotes untouched so `onclick="return confirm('…')"` is byte-identical to before): the `key="value"` loops in `Text`, `TextArea`, `Hidden`, `File`, `Select`, `Radio`, `Button`, `Link`, `Dropdown`, `Image`, and `Html` head components.
- `Field::setAllowHtml(true)` remains the explicit opt-out for fields that intentionally hold HTML.
- `grid.js` already used `innerText` for plain cells; `SimpleTable` is deprecated and untouched.
Covered by `stored_markup_is_escaped_at_every_output_sink`, `legacy_entity_encoded_rows_are_not_double_encoded`, `allow_html_fields_render_raw_by_explicit_opt_in`.

## 2. Compatibility kept on purpose

- All `FieldType` constants and the five legacy methods remain; `getPdoType()` on `TypeBool` still returns `PARAM_BOOL`.
- Generated classes do not need regeneration to keep working: old `new TypeFloat()` literals for decimal columns still run (now with locale parsing and safer binding). Regenerate to get `TypeDecimal`/`TypeEnum`/sizes.
- `getFormElement()` on a type still returns `Select`/`File`/`Hidden` for FK, document and auto-increment PK fields (moved into `AbstractFieldType`, so `Field` and direct callers agree).
- Date/time inputs keep their display defaults, so screens look the same.
- `Util::toFloat()` kept; no PHP 8.1+ syntax added (`never` was avoided; `composer.json` still says `>=8.0`).
- ASM (`sample_project/asm`) uses none of the legacy type methods directly (grep), uses `int(1)` for booleans (so no checkbox/bool change) and has 65 `decimal` columns that switch to `TypeDecimal` only after regeneration.

## 3. Behaviour changes to be aware of

1. **Getters return typed values after load.** `getIdVal()` is `int`, `tinyint(1)`/`boolean` columns return `bool`, `decimal` columns return a numeric string once regenerated. Loose comparisons (`==`) behave as before; strict comparisons against `'1'` would not (none found in ASM).
2. **Storage-time HTML escaping is gone.** `"Smith & Sons"` is stored as typed. Output escaping already happens in `Text::getValueForHtml()` (unless `Field::setAllowHtml(true)`), `grid.js` uses `innerText`. Rows written by older versions contain `&amp;`-style text; a one-off `html_entity_decode()` pass per text column (or leaving them, since they display as before) is an app decision.
3. **Invalid form input throws** `InvalidValueException` from `processForm()` instead of silently storing NULL. A page should catch it and redisplay; the old silent data loss is gone.
4. **`false` is written as 0**, never NULL. A nullable bool can no longer be set to NULL through a checkbox (it can through code).
5. **Integer fields reject fractions** (`"1.7"` was truncated to `1`).
6. **`timestamp` columns become `TypeDateTime`** on regeneration (they were textareas).
7. **Date-only text is accepted by `TypeDateTime`** (midnight); previously invalid.

## 4. Verification

| Check | Result |
|---|---|
| `phpunit --testsuite Unit` | 402 tests, 1,201 assertions, green (was 356 with 8 PHP 8.5 errors) |
| `phpunit --testsuite Integration` on MariaDB 12.2 (`laf_test`, root) | 77 tests, 197 assertions, green; includes bool false→0, decimal exact round-trip, text with `<`/`&`, locale datetime input, typed change tracking |
| `phpunit --testsuite Integration` on PostgreSQL 18 (`laf_test`, postgres) | 77 tests, 197 assertions, green |
| Live schema round-trip, both engines | §5 |
| PHPStan level 5 on `src/Laf/Database/Field`, `Util/Locale` | only pre-existing `Field.php` docblock mismatches remain |
| Lint | all changed files |

Tests whose expectations were flipped because they pinned a bug: `bool_falsy_string_values`, `varchar_sanitize_*`, `text/char_sanitize_strips_tags`, `datetime_invalid_values` (date-only), `factory_returns_type_time_for_time_and_datetime`, `factory_getclass_maps_tinyint_to_integer_not_bool`, `factory_returns_type_float_for_numeric_variants`, `literal_returns_correct_strings` (numeric → decimal), `FieldTest::set_value_sanitizes_input`.

## 5. Live schema round-trip (both engines)

A scratch script (`scratchpad/type_roundtrip_check.php`, not part of the repo) created a table with every interesting type, ran the engine's inspector, built fields through `FieldTypeFactory::getClass()`, inserted, reloaded, updated, and checked validation:

| Column | MariaDB 12.2 | PostgreSQL 18 |
|---|---|---|
| `tinyint(1)` / `boolean` | `TypeBool`, `false` stored as `0`, reloads as `false` | same; `false` stored as `f`, reloads as `false` |
| `decimal(12,2)` / `numeric(12,2)` | `TypeDecimal(12, 2)`, `'1234.5'` → `'1234.50'` exact | same |
| `json` (MariaDB: longtext + `json_valid` check) / `jsonb` | `TypeJson` via the new check-constraint detection, array encoded and reloaded | `TypeJson` |
| `blob` / `bytea` | bytes `\x00\x01\xFF` intact | stream resource read back to the same bytes |
| `char(36)` / `uuid` | `TypeChar` (opt in with `{"fields":{"setType":"uuid"}}`) | `TypeUuid`, normalised to lower case |
| `enum('sad','ok','happy')` / `CREATE TYPE … AS ENUM` | options parsed from `COLUMN_TYPE`, `'HAPPY'` → `'happy'` | options discovered from `pg_enum`, same |
| `set('a','b','c')` | `'c,a'` → `'a,c'` | — |
| `datetime` / `timestamp without time zone` | `'January 15, 2024 14:30'` → `'2024-01-15 14:30:00'` | same |
| `timestamp with time zone` | — | `'2024-01-15 14:30:00+01:00'` round-trips as the same instant |
| `bigint unsigned` / `bigint` | `'18446744073709551615'` as string | `9223372036854775807` as int |
| `tinyint(3) unsigned` | `TypeInteger('tinyint', true)`, `200` | — |
| `double` / `double precision` | `'1,5'` → `1.5` | same |
| `text` | `'Bolt M8 <10mm & nut'` verbatim | same |
| `validate()` on `char(3) = 'TOOLONG'` | `["code must be at most 3 characters"]` | same |
| enum `'angry'`, decimal `'12.3.4'` | rejected with `InvalidValueException` | same |

Two more fixes came out of this run:
- `Db::getOne()` used `fetchColumn() === false` as "no row", which swallowed a PostgreSQL boolean `false`; it now fetches the row and returns `'0'`/`'1'` for booleans.
- `TableInspector` (MySQL) re-labels MariaDB `JSON` columns (reported as `longtext`) as `json` by reading the `json_valid()` check constraint, so they get `TypeJson` on generation.

Test suites after these fixes: Unit 402 green; Integration 77 green on MariaDB and 77 green on PostgreSQL.
