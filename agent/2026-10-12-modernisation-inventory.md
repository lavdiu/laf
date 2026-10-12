Inventory (2026-10-12, branch improvements1) of the LAF source that the review-and-modernise pass has not covered yet, Grid excluded by the user's choice, with the defects found and the recommended order: Database core typing (Field, Table, ForeignKey, PrimaryKey, AuditLog), Util folder, Filesystem/Document, UI/Table, project scaffolding.

# Method

- `git log master..improvements1 -- <file>` per source file: which files no branch commit touched.
- `vendor/bin/phpstan analyse --level 5` on `src/Laf/Database src/Laf/Util src/Laf/Filesystem src/Laf/Exception src/Laf/UI/Table`: 28 errors (Document 6, UrlParser 9, Util 2, AuditLog 3, Field 3, PrimaryKey 3, AbstractFieldType 1, FieldTypeFactory 1).
- Typing census: untyped properties and methods without return types per file; tab-indented files; CRLF files (none left).
- App usage counts (asm + routerarena, Composer copies of LAF) for the compatibility contract.

# A. Never touched on the branch

| File | Lines | State / defects |
|---|---|---|
| `src/Laf/Filesystem/Document.php` | 219 | Untouched since 2021. `upload()` documented `int` but returns null on every failure; trusts the client MIME (`$_FILES[..]['type']`); runs every upload through SimpleImage so a PDF/doc upload throws inside the try and silently returns null; `bestFit('300','300')` passes strings; `getFullPathFullSize()` concatenates the `Field` object from `getFileNameFld()` (works only through `Field::__toString()`); `handleRequest()` echoes `file_get_contents()` and exits, fixed 2030 cache headers, unquoted `Content-Disposition`, redirects to Settings key `404`; `isImage()` hard-coded list; `$document_id` write-only; untyped. routerarena: `Document::upload()` 13 calls, `new Document()` 8 (asm none). The form `File` input and `Carousel` already have URL builders that route around it. |
| `src/Laf/Util/UrlParser.php` | 328 | Untyped singleton reading `$_GET` in the constructor; 9 PHPStan hits (null into `string`/`int` properties, `_getId()` returns int, uuid string or null against `int`); `getInsertLink()` duplicates `getNewLink()`; `redirect*()` exit; only the `uriRewrite` rewrite key. Heaviest LAF API in the apps (`getModule` 2,024, `getListLink` 804, `getId` 638, `redirectToViewPage` 286, ...) and used by the generated pages: every static method keeps name, arguments and return values. `UrlParserTest` (14 tests) drives it through `$_GET` plus a reflection reset. |
| `src/Laf/Util/Util.php` | 177 | `e()`/`attr()` modern; the rest untyped: `formatDateForDb()` compares `createFromFormat()` with null instead of false; `coalesce()` via `func_get_args`; `uuid()` via `openssl_random_pseudo_bytes`; `isJSON`, `tableNameToClassName`, `tableFieldNameToLabel/MethodName` untyped (apps use them). |
| `src/Laf/Util/Settings.php` | 146 | Half typed (`get`/`set`/`has`/`getBool` done); `getProperty($key)`, `propertyExists($key)` untyped; `saveToFile()` / `loadFromFile()` are TODO stubs returning false since 2021. Apps: `getProperty` 142 calls, `setProperty` 140. |
| `src/Laf/Exception/InvalidForeignKeyValue.php`, `InvalidValueException.php`, `MissingConfigParamException.php` | 7 each | Empty classes without docblocks; `InvalidForeignKeyValue` lacks the `Exception` suffix (thrown by `ForeignKey`). |
| `src/Laf/Database/SqlErrorLoggerInterface.php` | 34 | Functionally fine; no docblock. |
| `src/Laf/UI/Table/Td.php`, `Th.php` | 10 / 9 | Trivial subclasses, untyped `$tagName`. |

# B. Touched, but never reviewed as a folder

| File | Lines | State |
|---|---|---|
| `src/Laf/Database/Field/Field.php` | 1,040 | Centre of the ORM; got the FieldType rewrite (9d0c26c) but is tab-indented, 23 untyped properties, 29 public methods without return types, `getAttribute(): string` returns null, `$loadedOnTs` write-only, `@param mixed` against `FieldType`. |
| `src/Laf/Database/Table.php` | 371 | 6 untyped properties, 18 methods without return types. |
| `src/Laf/Database/ForeignKey.php` | 342 | 9 untyped properties, 3 untyped getters. |
| `src/Laf/Database/PrimaryKey.php` | 194 | 2 untyped properties, 3 "ternary always true" PHPStan hits. |
| `src/Laf/Database/AuditLog.php` | 750 | 6 untyped static properties, 3 PHPStan hits. |
| `AbstractFieldType.php`, `FieldTypeFactory.php` | | 1 PHPStan hit each (redundant `instanceof`, `??` on a complete map). |
| `src/Laf/UI/Table/Table.php`, `Tr.php`, `Cell.php` | 854 | Reworked in 9d0c26c (ComponentInterface, escaping, 44 tests) but 31 untyped properties, `Cell::getData()` untyped. |

`BaseObject.php`, `Db.php`, `Query.php` were reviewed twice; what is untyped there is untyped on purpose (generated overrides).

# C. Project scaffolding

- No `phpstan.neon` and no `rector.php`, although CLAUDE.md documents `vendor/bin/phpstan analyze` and `vendor/bin/rector process`.
- CI matrix PHP 8.1 to 8.4 (`.github/workflows/ci.yml`), `Dockerfile` `php:8.4-cli`; verification happens on 8.5.4.
- `README.md` is one line.
- `sample_files/test.php` shows an outdated inspector and grid usage; `examples/` has only the audit-logging example.
- Still open from earlier reviews: hand-kept `tests/Fixtures/LafShell/Base/*` differ from generator output; the apps' modals do not use `addHiddenInput()` for CSRF; `improvements1` is not merged into `master`.

# Recommended order (one review, one commit each)

1. Database core typing pass: `Field`, `Table`, `ForeignKey`, `PrimaryKey`, `AuditLog`, plus the two one-line PHPStan hits in `AbstractFieldType` / `FieldTypeFactory`. Typed properties and return types, spaces in `Field.php`, PHPStan 0 on `src/Laf/Database`, no behaviour change.
2. Util folder: `UrlParser` (typed, `_getId()` as int|string|null, `getInsertLink()` delegating, PHPStan 0, static API unchanged), `Util` (types, `formatDateForDb()` false check, `random_bytes()` uuid, variadic `coalesce()`), `Settings` (types; implement or deprecate the file stubs), docblocks on the empty exceptions and `SqlErrorLoggerInterface`.
3. `Filesystem/Document`: typed, `upload(): ?int`, server-side MIME via finfo, resize only images, value instead of Field object in the path, `readfile()` streaming with correct headers, configurable not-found URL, no silent catch; `Document` model in the LafShell fixture for tests.
4. UI/Table typing pass.
5. Scaffolding: `phpstan.neon`, `rector.php` (dry run), CI and Dockerfile to 8.5, README quick start, refreshed `sample_files/test.php`.

Grid (`PhpGrid`, `Column`, `ActionButton`, `Settings`, `SimpleTable`) stays as is.
