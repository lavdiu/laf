# Settings, locale and utilities

This chapter covers the small pieces every other chapter leans on. `Laf\Util\Settings` is the process-wide
configuration map: you load it once from a PHP/JSON/INI file, an array, the environment or a `.env` file, and
LAF reads `database.*`, `upload.*`, `locale.*` and the other keys from it. `Laf\Util\Locale` turns those locale
settings into parsing and display rules for dates and numbers. `Laf\Util\Util` holds the output escaping functions
and name conversions. The chapter ends with an overview of LAF's exception classes.

On this page:

- [Settings](#settings)
  - [Reading values](#reading-values)
  - [Loading configuration](#loading-configuration)
  - [Environment variables and .env files](#environment-variables-and-env-files)
  - [Every key LAF reads](#every-key-laf-reads)
  - [Settings reference](#settings-reference)
- [Locale](#locale)
  - [Formats](#formats)
  - [Display helpers](#display-helpers)
  - [Parsing user input](#parsing-user-input)
  - [Locale reference](#locale-reference)
- [Util](#util)
- [Exceptions](#exceptions)

## Settings

`Settings` is a flat map of dotted keys (`database.hostname`) to values. It is a singleton: what you set in the
bootstrap is visible to every model, form and page in the request. Nested arrays are flattened when loaded, so
`['database' => ['hostname' => 'x']]` and `Settings::set('database.hostname', 'x')` are the same thing.

### Reading values

```php
use Laf\Exception\MissingConfigParamException;
use Laf\Util\Settings;

Settings::get('database.hostname');          // "127.0.0.1"

try {
    Settings::get('nope.key');
} catch (MissingConfigParamException $e) {
    echo $e->getMessage();                   // Error: Setting nope.key doesnt exist
}

Settings::has('nope.key');                   // false (a key set to null counts as present)
Settings::getBool('nope.key', true);         // true  (the default)
Settings::getString('database.hostname');    // "127.0.0.1"
Settings::getInt('database.port');           // 3306
```

`get()` is strict on purpose: a required key that is missing fails loudly. Use the typed readers for optional keys:

| Reader | Accepts | Falls back to `$default` when |
|---|---|---|
| `getBool($key, $default = false)` | `true`/`false`, `1`/`0`, `"1"`/`"0"`, `"yes"`/`"no"`, `"on"`/`"off"`, `"true"`/`"false"` | missing, null, array, or not a recognised boolean |
| `getString($key, $default = '')` | any scalar (`true` gives `"1"`) | missing or not a scalar |
| `getInt($key, $default = 0)` | ints, bools, whole-number strings (spaces allowed) | missing or not a whole number |

```php
Settings::set('flag.a', 'off');   Settings::getBool('flag.a', true);   // false
Settings::set('flag.b', 'maybe'); Settings::getBool('flag.b', true);   // true (default: not a boolean)
Settings::set('n', '42 ');        Settings::getInt('n');               // 42
Settings::set('f', '4.5');        Settings::getInt('f', -1);           // -1 (not a whole number)
```

### Loading configuration

```php
// an array (nested keys are flattened, lists stay one value)
Settings::load([
    'upload' => ['allowed_mime_types' => ['image/*', 'application/pdf'], 'max_image_size' => 1600],
]);
Settings::get('upload.allowed_mime_types');   // ['image/*', 'application/pdf']
Settings::get('upload.max_image_size');       // 1600

// with a prefix
Settings::load(['date' => ['format' => 'd.m.Y']], 'locale');
Settings::get('locale.date.format');          // "d.m.Y"

// a file: .php (returns an array), .json (an object) or .ini (sections become prefixes)
Settings::getInstance()->loadFromFile(__DIR__ . '/config/settings.php');   // true, or false when missing/invalid
```

An INI file with typed values:

```ini
[database]
hostname = ini-host
port = 3307
persistent = true
```

```php
Settings::getInstance()->loadFromFile('app.ini');   // true
Settings::get('database.port');                     // 3307 (int)
Settings::get('database.persistent');               // true
```

`loadFromFile()` returns `false` and loads nothing when the file is missing, unreadable or does not hold an array.
Later loads overwrite earlier keys, so the usual order is: defaults file, then environment-specific file, then the
environment.

`saveToFile('.php'|'.json')` writes the whole map back in a form `loadFromFile()` reads (a cache of a merged
configuration, an installer). It returns `false` for other extensions or when the file cannot be written.

> **Note:** `saveToFile()` writes *every* key, including `database.password`. Never write it under the web root or
> into version control.

Other instance methods: `setProperty()`, `getProperty()` (throws like `get()`), `propertyExists()`,
`removeProperty()`, `getProperties()` (the whole map), `loadFromArray($values, $prefix)`. Static shortcuts:
`set()`, `remove()`, `load()`.

### Environment variables and .env files

Containers and CI pass configuration through the environment. `loadFromEnvironment($prefix = 'LAF_')` maps every
variable that starts with the prefix to a key: the rest of the name is lower-cased and `__` (double underscore)
becomes a dot. Values stay strings, so read flags with `getBool()` / `getInt()`.

```php
// LAF_DATABASE__HOSTNAME=db1  LAF_SETTINGS__USE_PRETTY_URL=false
Settings::loadFromEnvironment();          // 2 (variables loaded); default source: getenv() + $_ENV
Settings::get('database.hostname');       // "db1"
Settings::getBool('settings.use_pretty_url', true);   // false

// or pass the variables explicitly (tests)
Settings::loadFromEnvironment('LAF_', ['LAF_DATABASE__HOSTNAME' => 'db1', 'PATH' => 'x']);   // 1
```

`loadEnvFile($file, $prefix = 'LAF_')` reads `KEY=value` lines the same way. It understands `#` comments (also
after an unquoted value), `export KEY=...`, and single or double quotes (`\n` and `\"` inside double quotes). A
variable that exists in the real environment wins over the file, so the file holds local defaults and the
deployment can override them:

```bash
# .env
LAF_DATABASE__HOSTNAME=db.internal
LAF_DATABASE__PASSWORD="s3cret # not a comment"
LAF_FORM__NATIVE_DATE_INPUTS=yes   # trailing comment
export LAF_LOCALE__LANGUAGE='sq'
OTHER_VAR=ignored
```

```php
Settings::loadEnvFile(__DIR__ . '/.env');      // 4 (OTHER_VAR has no LAF_ prefix)
Settings::get('database.password');            // "s3cret # not a comment"
Settings::get('form.native_date_inputs');      // "yes"
Settings::getBool('form.native_date_inputs');  // true
Settings::get('locale.language');              // "sq"
Settings::loadEnvFile(__DIR__ . '/missing.env');   // false
```

A bootstrap that combines them (`sample_files/bootstrap.php` does the same with a hand-written map of the
`LAF_DB_*` and `APP_ENV` variables, which predates `loadFromEnvironment()`):

```php
use Laf\Util\Settings;

require __DIR__ . '/../vendor/autoload.php';

Settings::getInstance()->loadFromFile(__DIR__ . '/settings.php');
Settings::loadEnvFile(__DIR__ . '/../.env');   // optional local overrides
Settings::loadFromEnvironment();               // container / CI overrides win
date_default_timezone_set(Settings::getString('app.timezone', 'UTC'));
```

### Every key LAF reads

This is `sample_files/settings.php`, the reference configuration (copy it into your application and adjust). The
`app.*` keys belong to the sample bootstrap, not to LAF.

```php
return [
    'app' => [
        'env' => 'development',           // anything but 'production' shows errors
        'timezone' => 'UTC',
    ],
    'project' => [
        'package_name' => 'MyApp',        // namespace of the generated classes: app/lib/MyApp/
    ],
    'database' => [
        'engine' => 'mysql',              // mysql | postgres
        'hostname' => 'localhost',
        'port' => 3306,                   // 5432 for PostgreSQL
        'database_name' => 'myapp',
        'username' => 'root',
        'password' => '',
        // optional: charset, socket, persistent, connect_timeout, options, dsn, dsn_extra, timezone,
        // schema (PostgreSQL search_path), init_commands, empty_string_as_null, reconnect_on_loss
    ],
    'settings' => [
        'use_pretty_url' => true,         // /module/submodule/action/id instead of ?module=...
        'label' => [
            'translations' => [],         // page labels, e.g. 'add-new' => 'Regjistro' (PageGenerator::LABEL_DEFAULTS)
        ],
    ],
    'form' => [
        'restrict_to_rendered_fields' => false,   // ignore posted fields the form did not draw
        'native_date_inputs' => false,            // <input type="date"> instead of the locale format + picker
        'select_autocomplete_threshold' => 0,     // foreign keys to tables with more rows get an Autocomplete (0 = off)
    ],
    'orm' => [
        'validate_on_store' => false,             // field rules + validateRecord() inside store()/insert()/update()
    ],
    'upload' => [
        'documents_directory' => __DIR__ . '/../public/document/',
        'allowed_mime_types' => ['image/*', 'application/pdf'],
        'max_image_size' => 1200,
        'thumbnail_size' => 300,
        'cache_max_age' => 31536000,
        'not_found_url' => '/errors/404',
    ],
    'locale' => [
        'language' => 'en',
        'date' => ['format' => 'Y-m-d'],
        'datetime' => ['format' => 'Y-m-d H:i'],
        'time' => ['format' => 'H:i'],
        'decimal_separator' => '.',
        'thousands_separator' => ',',
    ],
];
```

Keys the sample does not list, because they are rarely needed:

| Key | Default | Read by |
|---|---|---|
| `database.*` optional keys | see [13-database.md](13-database.md#connection-settings-reference) | `Db` |
| `database.<name>.*` | | named connections, `Db::getInstance('<name>')` |
| `audit.max_value_length` | `0` (unlimited) | `AuditLog` ([14-audit-log.md](14-audit-log.md)) |
| `upload.image_mime_types` | common web image types | `Document::isImage()` ([15-files-and-documents.md](15-files-and-documents.md)) |
| `404` | none | legacy fallback for `upload.not_found_url` |

> **Note:** the sample puts `documents_directory` under `public/`. For uploaded files prefer a directory outside
> the web root and serve them through `Document::handleRequest()`; see [15-files-and-documents.md](15-files-and-documents.md#security-rules).

### Settings reference

| Method | What it does | Notes |
|---|---|---|
| `Settings::get($key)` | Value | `MissingConfigParamException` when missing |
| `Settings::has($key)` | Present? | null counts as present |
| `Settings::getBool($key, $default = false)` | Boolean | see the table above |
| `Settings::getString($key, $default = '')` | String | |
| `Settings::getInt($key, $default = 0)` | Integer | |
| `Settings::set($key, $value)` / `remove($key)` | Write / delete one key | |
| `Settings::load($values, $prefix = '')` | Load a nested array | lists stay values |
| `Settings::loadFromEnvironment($prefix = 'LAF_', ?$env)` | From environment variables | returns the count |
| `Settings::loadEnvFile($file, $prefix = 'LAF_')` | From a `.env` file | count, or `false` |
| `Settings::getInstance()` | The singleton | |
| `->loadFromFile($file)` | `.php` / `.json` / `.ini` | `bool` |
| `->saveToFile($file)` | `.php` / `.json` | `bool` |
| `->loadFromArray($values, $prefix)` | Same as `load()` | |
| `->setProperty()` / `getProperty()` / `propertyExists()` / `removeProperty()` / `getProperties()` | Instance access | |

## Locale

`Laf\Util\Locale` is the single source of truth for date and number formats. Inputs use it to show and parse
values, `Util::formatDateForDb()` uses it, and you use its display helpers in lists and pages. Every value is read
from Settings on each call, so a multi-tenant application can switch formats at runtime.

### Formats

| Setting | Default | Used for |
|---|---|---|
| `locale.date.format` | `F d, Y` (October 14, 2026) | DATE columns |
| `locale.datetime.format` | `F d, Y H:i` | DATETIME columns |
| `locale.time.format` | `g:i A` (2:30 PM) | TIME columns |
| `locale.decimal_separator` | `.` | numbers |
| `locale.thousands_separator` | `,` | numbers |
| `locale.language` | `en` | `<html lang>` |

Formats are PHP `date()` format strings. Values in the database are always ISO (`Y-m-d`, `Y-m-d H:i:s`,
`H:i:s`); only what the user sees and types follows the locale. With `form.native_date_inputs` the browser's own
date inputs are used instead, and they always post ISO values ([07-form-inputs.md](07-form-inputs.md)).

### Display helpers

With the defaults:

```php
use Laf\Util\Locale;

Locale::formatDate('2026-10-14');                 // "October 14, 2026"
Locale::formatDateTime('2026-10-14 09:05:00');    // "October 14, 2026 09:05"
Locale::formatTime('14:30:00');                   // "2:30 PM"
Locale::formatDate(new DateTimeImmutable('2026-01-05'));   // "January 05, 2026"
Locale::formatDate('2026-02-30', 'invalid');      // "invalid" (the $empty argument)

Locale::formatNumber('1234567.5');                // "1,234,567.50"  (2 decimals by default)
Locale::formatNumber(1234567.5, 0);               // "1,234,568"
Locale::formatNumber('4500.10', null);            // "4,500.1"      (null = the digits the value has)
Locale::formatNumber(null, 2, '-');               // "-"
Locale::formatNumber('abc', 2, 'n/a');            // "n/a"
```

With a European configuration:

```php
Settings::load(['locale' => [
    'date' => ['format' => 'd.m.Y'],
    'datetime' => ['format' => 'd.m.Y H:i'],
    'decimal_separator' => ',',
    'thousands_separator' => '.',
]]);

Locale::formatDate('2026-10-14');                 // "14.10.2026"
Locale::formatDateTime('2026-10-14 09:05:00');    // "14.10.2026 09:05"
Locale::formatNumber('1234567.5');                // "1.234.567,50"
```

The helpers accept ISO strings, `DateTimeInterface` objects, or a string already in the display format; `null`,
`''` and invalid values give the `$empty` argument (default `''`), so a list never shows "January 1, 1970".

> **Note:** the display helpers return plain text. Escape it when you build HTML yourself (`Util::e()`); `Table`
> cells set with `addRow()` / `setText()` are escaped for you.

### Parsing user input

`Locale::parseNumber()` normalises what people type into a canonical numeric string (`.` decimal point, no
grouping), or `null` when it is not a number. The rules: whitespace is removed; when both `.` and `,` appear, the
right-most one is the decimal point; a single separator is the decimal point when it is the configured decimal
separator or when it is not followed by exactly three digits; otherwise it is grouping.

With the default separators (`.` decimal, `,` thousands):

```php
Locale::parseNumber('1 234,56');   // "1234.56"
Locale::parseNumber('1,234.56');   // "1234.56"
Locale::parseNumber('1,5');        // "1.5"   (not three digits after it: a decimal comma)
Locale::parseNumber('1,234');      // "1234"  (three digits: grouping)
Locale::parseNumber('1.2.3');      // null
Locale::parseNumber('1.5e3');      // "1500"
Locale::parseNumber('-0');         // "0"
```

With `locale.decimal_separator = ','` the ambiguous `'1,234'` becomes `"1.234"` and `'1.234,5'` becomes `"1234.5"`.

`Locale::parseDate($value, $formats)` tries each format strictly: unspecified fields are reset instead of taken from
the current time, and an overflowing date such as February 31 is refused instead of rolling over:

```php
Locale::parseDate('14.10.2026', ['d.m.Y'])->format('Y-m-d');   // "2026-10-14"
Locale::parseDate('31.02.2026', ['d.m.Y']);                    // null
```

### Locale reference

| Method | Returns |
|---|---|
| `dateFormat()`, `dateTimeFormat()`, `timeFormat()` | the configured formats (or defaults) |
| `language()` | `locale.language`, default `en` |
| `decimalSeparator()`, `thousandsSeparator()` | the separators |
| `formatWithSeconds($format)` | the format with `:s` after the minutes: `H:i` gives `H:i:s`, `F d, Y H:i` gives `F d, Y H:i:s` |
| `formatNumber($value, ?int $decimals = 2, $empty = '')` | display number |
| `formatDate($value, $empty = '')`, `formatDateTime()`, `formatTime()` | display date/time |
| `parseDate($value, array $formats)` | `?DateTimeImmutable` |
| `parseNumber($value)` | canonical numeric string or `null` |

Constants: `ISO_DATE`, `ISO_DATETIME`, `ISO_TIME`, `DEFAULT_DATE_FORMAT`, `DEFAULT_DATETIME_FORMAT`,
`DEFAULT_TIME_FORMAT`.

## Util

`Laf\Util\Util` is a box of static helpers. The two that matter most are the escaping functions: LAF stores values
verbatim and escapes at output, and these are the functions it uses ([17-security.md](17-security.md) explains the
rule).

```php
use Laf\Util\Util;

$s = "O'Brien & <Sons> \"Ltd\"";
Util::e($s);              // O&apos;Brien &amp; &lt;Sons&gt; &quot;Ltd&quot;   (text, single- or double-quoted attributes)
Util::attr($s);           // O'Brien &amp; &lt;Sons&gt; &quot;Ltd&quot;        (double-quoted attributes only)
Util::e('&amp; already'); // &amp; already    (existing entities are not encoded twice)
Util::e(null);            // ""
Util::e(true);            // "1"
```

```php
echo "<td title='" . Util::e($employee->getNotesVal()) . "'>" . Util::e($employee->getLastNameVal()) . "</td>";
echo '<a href="' . Util::attr($url) . '" onclick="return confirm(\'Sure?\')">Delete</a>';
```

| Method | What it does | Example |
|---|---|---|
| `e($value)` | Escape for HTML text and attributes (`ENT_QUOTES`, UTF-8, no double encoding) | see above |
| `attr($value)` | Escape for a double-quoted attribute; leaves `'` alone | see above |
| `isJSON($value)` | A JSON object or array (not a bare scalar) | `isJSON('{"a":1}')` true, `isJSON('1')` false |
| `tableNameToClassName($name)` | `employee_project` to `EmployeeProject` | |
| `tableFieldNameToLabel($name)` | `company_id` to `Company`, `user_identity` to `User Identity` | only a trailing `_id` is dropped |
| `tableFieldNameToMethodName($name)` | `birth_date` to `BirthDate` | the part in `getBirthDateVal()` |
| `formatDateForDb($date)` | a date in the locale format or ISO to `Y-m-d`, `null` otherwise | `'14.10.2026'` with `d.m.Y` gives `2026-10-14` |
| `uuid()` | random version 4 UUID | 36 characters |
| `coalesce(...$values)` | first non-empty argument; `0` and `"0"` count as values | `coalesce(null, '', 0, 'x')` gives `0` |
| `scrambleFieldOrTableName($name)` | rot13, the form field naming | `first_name` to `svefg_anzr` |
| `toFloat($number)` | either separator convention to float | `'1.234,56'` and `'1,234.56'` give `1234.56` |
| `timeAgo(DateTimeInterface)` | "3 hours ago" | seconds up to years |

> **Note:** `Util::e()` does not re-encode existing entities (`double_encode = false`), so text stored by older LAF
> versions as `&amp;` still displays as `&`. The flip side: a user who types the literal text `&amp;` sees `&`.
> This is a display quirk, not an injection risk.

> **Note:** form inputs carry rot13 field names (`svefg_anzr`). That is a naming convention, not a security
> measure; the CSRF token and the signed field list are what protect forms.

## Exceptions

All of LAF's own exceptions live in `Laf\Exception`. Output from the example database where it helps:

| Exception | Extends | Thrown when | Useful methods |
|---|---|---|---|
| `MissingConfigParamException` | `Exception` | `Settings::get()` of a missing key | |
| `DbConnectException` | `PDOException` | a connection cannot be opened | `getCode()` (driver code), `errorInfo` |
| `DatabaseException` | `Exception` | a model statement fails | `getSql()`, `getBindings()`, `getSqlState()`, `getDriverCode()`, `isUniqueViolation()`, `isForeignKeyViolation()`, `getPrevious()` = the `PDOException` |
| `MissingFieldValueException` | `Exception` | a required column has no value on write | `getFieldNames()`, `setFieldNames()` |
| `UniqueFieldDuplicateValueException` | `Exception` | a single-column unique value already exists | `getFieldNames()`, `setFieldNames()` |
| `InvalidForeignKeyValue` | `Exception` | a foreign key value has no referenced row | |
| `InvalidValueException` | `Exception` | a value is not valid for its field type; an autocomplete "create new" refusal (the message is shown to the user) | |
| `RecordNotFoundException` | `RuntimeException` | `Model::loadOrFail()` finds no row; code `404` | `getTableName()`, `getRecordId()` |
| `ValidationException` | `Exception` | validation on store refuses a write | `getErrors()`, `getMessages()`, `getFieldNames()`, `getTableName()`, `setTableName()`; constant `RECORD_KEY` |
| `FormValidationException` | `ValidationException` | `Form::processForm()` finds errors | as above |
| `CsrfTokenException` | `Exception` | a form submission carries no valid CSRF token | |

What they look like:

```text
Laf\Exception\UniqueFieldDuplicateValueException (code 0): Field email must be unique: value 'dup@ch16.test' already exists in employee
  fields: ["email"]
Laf\Exception\MissingFieldValueException (code 0): Field last_name is required. Value provided is ; Field email is required. Value provided is
  fields: ["last_name","email"]
Laf\Exception\InvalidForeignKeyValue (code 0): Foreign key not found company_id
Laf\Exception\RecordNotFoundException (code 404): No employee record with id 987654
Laf\Exception\MissingConfigParamException (code 0): Error: Setting upload.documents_directory doesnt exist
```

Two catch patterns cover most code. In forms, one `catch (ValidationException $e)` covers both validation on store and
form validation, because `FormValidationException` extends it; `processForm()` already turns missing-value and
duplicate-value refusals into form errors ([06-forms.md](06-forms.md)). In controllers, map
`RecordNotFoundException` to a 404 page with its code:

```php
use Laf\Exception\RecordNotFoundException;
use MyApp\Employee;

try {
    $employee = Employee::loadOrFail($id);
} catch (RecordNotFoundException $e) {
    http_response_code($e->getCode());   // 404
    exit('No such employee');
}
```

Validation exceptions are covered in [05-validation.md](05-validation.md), model write exceptions in
[03-models.md](03-models.md), connection and statement errors in [13-database.md](13-database.md#errors).

## See also

- [01-getting-started.md](01-getting-started.md): the bootstrap that loads the settings
- [13-database.md](13-database.md): every `database.*` key
- [07-form-inputs.md](07-form-inputs.md): how date and number inputs use `Locale`
- [09-lists-and-tables.md](09-lists-and-tables.md): `Locale` formatting in lists
- [17-security.md](17-security.md): escaping rules
