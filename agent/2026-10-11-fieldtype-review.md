Review of the FieldType layer (src/Laf/Database/Field on improvements1 @ eac3820): how values flow form → PHP → PDO → DB, the bugs found, and a proposed cleaner contract.

# FieldType review — 2026-10-11

Branch: `improvements1` (checked out for this review). The Field directory differs from master by two lines in `TypeTime.php`, so every finding applies to master too. The branch's `tests/FieldTypeTest.php` (44 cases) and `tests/FieldTypeFactoryTest.php` (12 cases) were read; `vendor/` is not installed in this checkout, so the suite was not executed.

## 1. How a value moves today

```
                      FieldType contract (FieldType.php)
                      ┌──────────────────────────────────────────────────────┐
browser string ──►    │ formatForDb(?string): mixed   "parse the form text"   │ ──► Field::setValue()
                      │ isValid(mixed): bool          "accept or throw"       │ ──► throws InvalidValueException
                      │ getValueDbSanitized(mixed)    "normalise for storage" │ ──► Field::$value
                      │ getPdoType(): int             "bind hint"             │ ──► BaseObject::update() only
                      │ getFormElement(Field)         "which <input>"         │ ──► Field::getFormElement()
                      └──────────────────────────────────────────────────────┘
DB row ──► Field::loadValueFromDb()   (no conversion at all: value stays whatever PDO returned, usually a string)
```

Call sites (verified by grep):
- `Form::processForm()` (Form.php:194, 206, 213): `$field->setValue($type->formatForDb(trim($_POST[...])))`.
- `Field::setValue()` (Field.php:255-282): float comma handling → `isValid()` → `sanitize()` = `getValueDbSanitized()` → change tracking.
- `BaseObject::insert()` (BaseObject.php:547): passes `$field->getValue()` straight to `PDOStatement::execute([...])` (every value bound as string; `getPdoType()` is ignored here).
- `BaseObject::update()` (BaseObject.php:694-699): `bindValue(name, value, getPdoType())`, overridden to `PARAM_NULL` when `mb_strlen($value ?? '') == 0`.
- `ClassGenerator::generateFields()` (ClassGenerator.php:512): `FieldTypeFactory::getClassLiteral($column['DATA_TYPE'])` decides the type at generation time. `getClass()` has no production caller.
- `Db::connect()` (Db.php:90): `PDO::ATTR_ORACLE_NULLS = NULL_EMPTY_STRING`, so an empty string read from the DB arrives as `null`.

So there are three overlapping "convert" steps and one bind step, and they disagree with each other in several places. That is the root of most findings below.

## 2. Findings, by severity

### A. Fatal or data-destroying

| # | Where | What happens | Why it matters for an ERP |
|---|---|---|---|
| A1 | `TypeBlob.php:5`, `TypeChar.php:5`, `TypeJson.php:5` | `use Laf\UI\Form\Text` / `Laf\UI\Form\TextArea` — those classes do not exist (`Laf\UI\Form\Input\Text` does). `getFormElement()` fatals with "class not found". | Any `char(n)` column (country/currency codes), any `json` or `blob` column makes the generated form page crash on render. Nothing in the test suite calls `getFormElement()` on these types, so it is green. |
| A2 | `FieldTypeFactory::getClassLiteral()` lines 73-75 | `tinyint` → `TypeBool` regardless of width. | `tinyint(3)` quantities, status codes, percent columns become a checkbox and are stored as 0/1. (ASM dodged this by using `int(1)` for booleans: 30 columns; those get `TypeInteger` and a number input instead of a checkbox, which is the opposite problem.) MySQL exposes the width in `COLUMN_TYPE` (`tinyint(1)`), which the inspector already selects but the generator never reads. |
| A3 | `TypeVarchar/TypeText/TypeChar::getValueDbSanitized()` | `strip_tags()` then `htmlspecialchars(ENT_COMPAT)` **before storage**. | Data corruption, not just encoding: `"Bolt M8 <10mm"` is stored as `"Bolt M8 "` (strip_tags eats from `<` to end of string). `"Smith & Sons"` is stored as `"Smith &amp; Sons"`, then `Text::getValueForHtml()` (Text.php:140) runs `htmlentities()` again, so the screen shows `&amp;`. Exports, PDFs, API consumers and `LIKE` searches all see the encoded form. The grid does not need this protection: `grid.js` uses `innerText` for plain cells (grid.js:801, 806) and `innerHTML` only for developer callbacks. Escaping belongs at output, once. |
| A4 | `TypeBool::isValid()` + `BaseObject::update()` | (a) `filter_var("0", FILTER_VALIDATE_BOOL) !== false` is false, so `isValid("0")`, `isValid(0)`, `isValid("false")`, `isValid("no")` all **throw**. The branch test `bool_falsy_string_values` asserts this as "a known quirk" instead of fixing it. (b) In `update()`, `mb_strlen(false ?? '') == 0`, so a boolean set to `false` is bound as `PARAM_NULL`. | `$invoice->setPaidVal(0)` throws; `setPaidVal(false)` survives validation but is written as `NULL`, which fails on a `NOT NULL` column and silently reads back as "unset" on a nullable one. Unchecking a checkbox in the UI goes through `formatForDb('')` → `(bool)''` = `false` → same NULL write. |
| A5 | `FieldTypeFactory::getClassLiteral()` | No case for MySQL `timestamp`, PostgreSQL `boolean`, `jsonb`, `uuid`, `enum`, `set`, `year`, `bit`, `binary/varbinary`, `*blob` variants, `mediumtext/longtext` (last two fall to TypeText, which is fine). Unknown types fall to **TypeText**. | `created_on timestamp` becomes a textarea with no date validation. A PG `boolean` column is `'boolean'` in `information_schema`, not `'bool'`, so it is a textarea too and `(string)true` = `"1"` goes through. `jsonb` loses JSON validation. |

### B. Wrong results without crashing

| # | Where | What happens |
|---|---|---|
| B1 | `TypeFloat` (all methods) + `FieldTypeFactory` maps `decimal`/`numeric` to it | `(float)` / `floatval()` on `decimal(12,2)` money (ASM: 65 decimal columns). `0.1 + 0.2` class errors, and the string PDO sees is PHP's shortest round-trip repr, which for large totals can carry 15+ digits the column then rounds. Needs a `TypeDecimal` that keeps a validated string and binds `PARAM_STR`. |
| B2 | `Field::setValue()` Field.php:261 | Comma handling runs only when the value contains `,`, so `"1,234"` (US thousands) becomes `1.234` and `"1,234.56"` works only because `Util::toFloat` guesses from separator order. Locale should decide, not separator heuristics. Also `str_contains(null, ',')` is a PHP 8.1 deprecation. |
| B3 | `TypeInteger::formatForDb()` | `is_numeric("1.7")` is true → `(int)` → `1`. A typo in a quantity field silently truncates. `isValid()` would have rejected `"1.7"`, but `formatForDb` runs first in the form path. Also `FILTER_VALIDATE_INT` rejects values above `PHP_INT_MAX`, so `bigint unsigned` IDs from external systems fail validation. |
| B4 | `TypeDate`/`TypeDateTime`/`TypeTime` default formats disagree with the inputs | `Input\Date` renders with `'F d, Y'` (Date.php:25) and `Input\DateTime` with `'F d, Y H:i'`, but `TypeDate::formatForDb()` defaults to `'Y-m-d'` and `TypeDateTime::formatForDb()` to `'Y-m-d H:i:s'`. With no `locale.*.format` setting (sample config does not set any), submitting an untouched date field parses `"January 05, 2024"` as `Y-m-d` → `false` → `null` → **date cleared on every save**. `Input\Time` default `'H:i A'` mixes 24-hour `H` with AM/PM. |
| B5 | `TypeDateTime::formatForDb()` / `TypeTime::formatForDb()` | `DateTime::createFromFormat($fmt, $v)` without a leading `!` fills unspecified fields from *now*. A locale format without seconds (`'d/m/Y H:i'`) stores the current wall-clock seconds; a time-only format stores today's date internally (harmless after `->format('H:i:s')`, but the datetime case is a real drift). |
| B6 | `TypeDateTime::isValid()` | Only `'Y-m-d H:i:s'` is accepted. Values that come back from the DB with fractional seconds (`datetime(6)`) or a PG offset (`+01`) load fine via `loadValueFromDb` but throw as soon as they are copied with `setValue()` (clone-and-save flows, `touch()`-style helpers). |
| B7 | `TypeTime::isValid('')` | `createFromFormat('H:i:s', '')` is `false` → invalid, while `TypeDate`/`TypeDateTime` accept `''`. The form path masks it (`formatForDb('')` → `null`), direct setters do not. |
| B8 | `TypeJson::formatForDb('')` | Returns `''` (only `null` is mapped), then `isValid('')` → `json_decode('')` → syntax error → **submitting a form with an empty JSON textarea throws**. Also MariaDB reports `JSON` columns as `longtext`, so `TypeJson` is never generated there (the integration test works around it with `setFieldValueRaw`). |
| B9 | `TypeVarchar::formatForDb('')` → `null` but `TypeChar::formatForDb('')` → `''`; `TypeText` trims, `TypeVarchar` does not. | Same user action, different stored value depending on column type. With `NULL_EMPTY_STRING` on the connection, `''` can never be read back anyway, so `null` should be the single rule. |
| B10 | `TypeVarchar/TypeChar/TypeText::isValid()` always `true` | `maxLength` from the schema is emitted into the HTML attribute only (`Text.php:243`). Nothing server-side rejects a 300-char value for `varchar(255)`: MySQL strict mode throws a PDO exception the page does not catch; non-strict truncates silently. |
| B11 | `TypeBool::getValueDbSanitized()` returns PHP `bool`, DB loads return `"0"`/`"1"` strings | Consumers get mixed types; `Checkbox::drawViewMode()` compares with `== 1` / `== 0` and `null == 0` is false in PHP 8 but `false == 0` is true, so an unsaved `false` renders "No" while a DB `NULL` renders blank. `hasChanged()` uses `!=`, and `null != false` is **false**, so flipping a `NULL` bool to `false` is not detected and never written. |
| B12 | `FieldTypeFactory::getClass()` | Maps `datetime` → `TypeTime` (drops the date), lacks every `*2`/`*3` alias and `bool`. The test suite pins this behaviour (`factory_returns_type_time_for_time_and_datetime`), so a fix must update the test. Dead code in production; either align it with `getClassLiteral()` or delete it. `sanitize()` at line 104 is a stub returning `''`. |
| B13 | `TypeInteger::getFormElement()` | FK → `Select`, document FK → `File`, auto-increment PK → `Hidden` logic lives **inside the integer type**. A `varchar`/`uuid` FK gets a plain `Text` box; a `char(3)` currency FK never gets a dropdown. This decision belongs in `Field::getFormElement()` (Field.php:796), which already knows `isForeignKey()`, `isDocumentField()`, `isPrimaryKey()`. |
| B14 | `BaseObject::insert()` ignores `getPdoType()` | Insert binds everything as string via `execute([...])`. Works on MySQL by coercion; on PostgreSQL a `bool` column receiving `""` for `false` raises "invalid input syntax for type boolean". Update binds typed, insert does not: two code paths, two behaviours. |

### C. Design debt (not bugs, but why the above keep happening)

- **No typed contract.** `FieldType` declares `isValid($value)`, `formatForDb(?string $value)` etc. without return types; implementations return `bool|int|float|string|null` ad hoc. `formatForDb(?string)` is typed as string input, but `Field::getValueForDbInsert()` passes `getValue()`, which may already be an `int`/`bool` after sanitisation (works only because strict_types is off).
- **Direction is unclear.** "formatForDb" actually means "parse the form text", "getValueDbSanitized" actually means "coerce to PHP type and (wrongly) HTML-escape". There is no "from DB" step at all, so `getValue()` returns whatever PDO gave (`"2.10"`, `"1"`, `"2024-01-01 10:00:00"`), and the same field returns a different PHP type before and after `reload()`.
- **Type-name constants mix engines** (`'int'`, `'character varying'`, `'timestamp with time zone'`) and the two factory methods drifted apart because each case list is hand-maintained twice.
- **No precision/scale/unsigned/enum metadata** reaches the type. The inspector selects `information_schema.columns.*` (so `NUMERIC_SCALE`, `COLUMN_TYPE`, `COLUMN_DEFAULT`, `CHARACTER_SET_NAME` are available) but `generateFields()` only reads `DATA_TYPE`, `IS_NULLABLE`, `CHARACTER_MAXIMUM_LENGTH`, `EXTRA`, `COLUMN_KEY`, `COLUMN_COMMENT`.
- **Tests encode bugs as behaviour**: `bool_falsy_string_values`, `factory_returns_type_time_for_time_and_datetime`, `factory_getclass_maps_tinyint_to_integer_not_bool`, `varchar_sanitize_encodes_special_chars`. They should be flipped when the fixes land, not kept.

## 3. Per-class scorecard

| Class | isValid | getValueDbSanitized | formatForDb | PDO type | Form element | Notes |
|---|---|---|---|---|---|---|
| TypeVarchar | always true (B10) | strip_tags+htmlspecialchars (A3) | '' → null | STR | Text | |
| TypeChar | always true | same as varchar (A3) | '' → '' (B9) | STR | **broken import (A1)** | |
| TypeText | always true | same (A3) | trims, '' → null | STR | TextArea | |
| TypeInteger | FILTER_VALIDATE_INT | (int) | is_numeric → (int) truncates (B3) | INT | FK/PK logic here (B13) | |
| TypeFloat | FILTER_VALIDATE_FLOAT | (float) (B1) | floatval (B1) | STR | Text | no `step`, no scale |
| TypeBool | rejects "0"/0/"false" (A4) | (bool) | (bool) | BOOL | Checkbox | false → NULL on update (A4) |
| TypeDate | Y-m-d strict | Y-m-d | locale or Y-m-d (B4) | STR | Date | |
| TypeDateTime | Y-m-d H:i:s strict (B6) | same | locale or default (B4, B5) | STR | DateTime | |
| TypeTime | H:i:s, '' invalid (B7) | H:i:s | locale or default (B4, B5) | STR | Time | |
| TypeJson | json_decode, '' invalid (B8) | passthrough | null only | STR | **broken import (A1)** | |
| TypeBlob | always true | passthrough | passthrough | LOB | **broken import (A1)** | LOB bind never used (insert binds as string) |

## 4. Proposed shape (keeps the generated-code API, replaces the internals)

```php
interface FieldType
{
    /** DB → PHP, called by Field::loadValueFromDb(). "2.10" → "2.10" (decimal), "1" → true (bool), "2024-01-01" → "2024-01-01" */
    public function fromDb(mixed $raw): mixed;

    /** form/user input → PHP, called by Form::processForm() and setValue(). Returns a Result, never throws. */
    public function fromInput(?string $input, Locale $locale): ConversionResult;   // ok(value) | error(message)

    /** PHP → bound value + PDO type, called by insert() AND update(). [null, PARAM_NULL] | [value, PARAM_*] */
    public function toDb(mixed $value): array;

    /** constraints the type knows about: max length, precision/scale, min/max, enum values */
    public function validate(mixed $value, Field $field): array;   // [] or list of messages

    /** HTML5 attributes for the input: type, step, maxlength, pattern, min, max */
    public function inputAttributes(Field $field): array;

    public function defaultInput(): string;   // class name only; Field decides FK/PK/document overrides
}
```

Concrete steps, smallest first:

1. **Fix the three imports** (A1) and add one test per type that calls `getFormElement(new Field())` so it cannot regress.
2. **Fix bool** (A4, B11): `isValid()` via `filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) !== null`; in `update()` treat `false`/`0` as a value, only `null` as `PARAM_NULL`; normalise `fromDb("0")` to `false`. Flip `bool_falsy_string_values`.
3. **Stop escaping at storage** (A3): `getValueDbSanitized()` for the three string types becomes `trim()` + optional NFC normalisation; escape in `getValueForHtml()` (already there) and in labels/attributes (separate finding in the ERP audit, 5.8). Migration note for ASM: existing rows contain `&amp;`-style data; a one-off `html_entity_decode` pass per text column, or a tolerant read path for a release, is needed.
4. **Generator mapping** (A2, A5): read `COLUMN_TYPE` on MySQL (`tinyint(1)` → Bool, else Integer; `enum(...)` → new `TypeEnum` with the option list; `decimal(p,s)` → `TypeDecimal` with scale), and the inspector-normalised `data_type` on PG (`boolean`, `timestamp without time zone`, `jsonb`, `uuid`, `numeric`). Collapse the two switch statements into one table `[dbType => class]` consulted by both `getClass()` and `getClassLiteral()`, so they cannot drift.
5. **`TypeDecimal`** (B1): string in, string out, regex `^-?\d+(\.\d+)?$` after locale normalisation, scale check from schema, `bcadd`/`bccomp` helpers, `step="0.01"` on the input. Map `decimal`/`numeric` to it; keep `TypeFloat` for `float`/`double`/`real`.
6. **One empty-value rule** (B7, B8, B9): empty input → `null` for every type, before validation.
7. **Dates**: single source of locale formats (`Settings 'locale.*.format'` with the *same* defaults in the types and in the inputs), `'!' . $format` in every `createFromFormat`, accept fractional seconds and offsets in `isValid()` by parsing with `new DateTime($value)` and re-formatting. Consider emitting HTML5 `type=date`/`datetime-local` with ISO values, which removes locale parsing from the submit path entirely.
8. **Integer** (B3): reject non-integer input instead of truncating; validate against the column's range (`tinyint`/`smallint`/`int`/`bigint`, signed/unsigned from `COLUMN_TYPE`).
9. **Length check** (B10): `validate()` compares `mb_strlen` with `CHARACTER_MAXIMUM_LENGTH`; surfaces through the validation-result work in the ERP audit (5.5).
10. **Move FK/PK/document element choice** into `Field::getFormElement()` (B13); types return only their natural input.
11. **Make insert bind like update** (B14): build `[$value, $pdoType]` from `toDb()` for both statements.
12. Delete `FieldTypeFactory::sanitize()`, delete or align `getClass()`, update the four tests that pin current bugs.

## 5. Tests to add (names only)

`char_form_element_renders`, `json_form_element_renders`, `blob_form_element_renders`, `bool_accepts_zero_and_false_strings`, `bool_false_is_bound_as_false_not_null` (integration), `varchar_keeps_ampersand_and_lt`, `decimal_round_trips_two_places`, `integer_rejects_fraction`, `integer_accepts_bigint_unsigned_as_string`, `date_submit_without_locale_setting_keeps_value`, `datetime_accepts_fractional_seconds_from_db`, `time_accepts_empty`, `json_empty_input_becomes_null`, `factory_tinyint1_is_bool_tinyint3_is_integer`, `factory_timestamp_is_datetime`, `factory_pg_boolean_is_bool`, `factory_getclass_and_literal_agree` (iterate every constant and compare).

## Appendix — ASM column types (99 tables, from `migrations/database_structure.sql`, UTF-16)

| count | type | current mapping | note |
|---|---|---|---|
| 193 | int(11) | TypeInteger | |
| 90 | varchar(255) | TypeVarchar | A3 applies |
| 81 | datetime | TypeDateTime | B4/B6 apply |
| 71 | int(8) | TypeInteger | |
| 61 | text | TypeText | A3 applies |
| 52 | decimal(12,2) | TypeFloat | B1: money |
| 51 | int(14) | TypeInteger | |
| 30 | int(1) | TypeInteger | used as boolean; renders as number input, not checkbox |
| 13 | decimal(8,2) | TypeFloat | B1 |
| 8 | date | TypeDate | B4 applies |
| 0 | tinyint / timestamp / json / char | — | A1/A2/A5 are latent for ASM, live for new projects |
