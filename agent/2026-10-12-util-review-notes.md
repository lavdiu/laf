Review of `src/Laf/Util` (UrlParser, Util, Settings) plus the three empty exception classes and `SqlErrorLoggerInterface` on `improvements1`, 2026-10-12: typed throughout, a date helper that crashed on bad input fixed, UUID ids accepted in routes and links, link parts URL-encoded, array request values tolerated, Settings gets typed readers and real file loading/saving; links render identical for every existing route shape, PHPStan level 5 clean, Unit 656 tests, MariaDB and PostgreSQL integration green.

# Defects fixed

| # | Where | Defect | Fix |
|---|---|---|---|
| U1 | Util | `formatDateForDb()` compared `DateTime::createFromFormat()` with null instead of false, so an invalid date was a fatal `->format()` on false (the old test pinned the crash) | parses the locale date format (`Locale::dateFormat()`, 'F d, Y' by default) and 'Y-m-d' strictly through `Locale::parseDate()`; null for anything else |
| P1 | UrlParser | `getViewLink/getUpdateLink/getDeleteLink($id)` accepted numbers only: a UUID key passed in was ignored and the link carried the route's own id (wrong record after `redirectToViewPage($uuid)`) | numbers, UUIDs and `all` are used, anything else falls back to the route id as before |
| P2 | UrlParser | `?id[]=1` (or an array in any part) ended up in string-typed properties / `is_numeric(array)` | array request values count as absent |
| P3 | UrlParser | module and submodule from the URL were put into the generated links verbatim (`/ad min/<x>/view/1`) | `rawurlencode()` on module, submodule, action and id; plain names are unchanged |
| P4 | UrlParser | `getInsertLink()` duplicated `getNewLink()`; nine PHPStan findings (null into `string`/`int` properties, `_getId()` returning int, string or null against `int`, unreachable UUID branch) | one `getLink()` builder, typed properties, `getId(): int|string|null` |
| S1 | Settings | `saveToFile()` / `loadFromFile()` were TODO stubs returning false since 2021 | implemented (`.php` array file, `.json`; `.ini` read-only); the stub behaviour for no file / missing file stays false, as `SettingsTest` pins |

# Modernisation

- `UrlParser`: typed properties, `?string` id segment normalised on read (`getId()`), `getRawId()`, `reset()` (tests and CLI
  scripts: the old tests reach into the private static with reflection, that keeps working), `isUuid()`, generic
  `getLink($action, $id, $withId)`, redirects through one helper (`Location:` header). The constructor still reads
  Settings `settings.use_pretty_url` with the old `(bool)` cast.
- `Util`: class docblock, typed signatures (`?string` on the three name helpers so a null column name still gives `''`),
  `isJSON(mixed): bool`, `uuid()` from `random_bytes()` (same v4 layout), `coalesce(mixed ...$values)` instead of
  `func_get_args()` (same rule: 0 and "0" are values, any array is a value, an empty one too), `timeAgo(DateTimeInterface)`.
- `Settings`: typed, `getProperty(string): mixed`, `removeProperty()`, `getProperties()`, `loadFromArray()` (nested arrays
  flattened to dotted keys, lists kept as values), static `getString()`, `getInt()`, `load()`, `remove()`.
- Docblocks on `InvalidForeignKeyValue` (the name predates the suffix convention and stays: generated code and the apps
  catch it), `InvalidValueException`, `MissingConfigParamException`, `SqlErrorLoggerInterface`.
- PHPStan level 5 on `src/Laf/Util` + `src/Laf/Exception`: 11 errors before (UrlParser 9, Util 2), 0 after.

# Compatibility

- Every static `UrlParser` method keeps its name and arguments (the apps call `getModule()` 2,024×, `getListLink()` 804×,
  `getId()` 638×, `redirectToViewPage($id)` 261×). Links for every existing route shape are byte-identical: pretty and
  query-string mode, with and without id, `?`-prefixed rewrite, `mod=` alias, no request at all (scratchpad
  `urlparser_render.php`, old class from `git show HEAD:`). A route without an id still yields `/m/s/view/` and
  `…&id=` as before.
- Only values that never produced a usable link change: a UUID argument is now used, special characters in module or
  submodule are encoded.
- `Util::formatDateForDb('invalid')` returns null instead of crashing; `UtilTest::format_date_for_db_invalid_crashes`
  became `…_invalid_is_null`.
- `Settings::getInstance()->saveToFile()` without a file and `loadFromFile('missing')` still return false.

# Verification

- `php -l`, PHPStan level 5 clean on Util, Exception, Database, Generator, Form, Page, Container, Component, Traits, Table.
- Unit suite: 656 tests, 2,567 assertions (11 new in `tests/UtilReviewTest.php`); MariaDB integration 151 / 685,
  PostgreSQL integration 151 / 686.

# Open items

- `Locale.php` was already modern and is untouched.
- `UrlParser` still reads `$_GET` itself; an injectable request would need a wider change and nothing asks for it.
- Next in the sequence: `Filesystem/Document`, `UI/Table`, scaffolding (phpstan.neon, rector.php, CI 8.5, README).
