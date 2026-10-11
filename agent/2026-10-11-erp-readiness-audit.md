Audit of the LAF framework (master @ e884a24) for building a new ERP on it: verified strengths, bugs, and improvements ordered from hours to weeks.

# LAF ERP-readiness audit — 2026-10-11

Scope: `src/Laf/**` on `master`, the `improvements1` branch, and `sample_project/asm/` as the evidence of what a real ERP had to build by hand on top of LAF. Every "missing" claim below was grepped for first; items that exist are listed in the "what already works" section so they are not re-proposed.

## 1. Headline findings

1. **`improvements1` was never merged.** It is 21 commits ahead of master (+7,130 / -522 lines): 17 unit-test files (~5,400 lines), CI workflow, CSRF protection in `Form`, the PageGenerator rewrite (flash messages, record checks, lazy tabs, junction-table detection), the `QueryBuilder` deletion, Docker test harness. Master only has 4 README/composer commits that `improvements1` lacks. Merge-base is `8fdbe38`, so it merges cleanly in concept. **This is the single highest-value, lowest-effort improvement.** The project memory describes `improvements1` as if it were master, so it is stale until the merge happens.
2. **The ORM has an N+1 shape by design.** `find()`/`bOfind()` select IDs then instantiate one object per row, each doing its own `SELECT *`. `listAllObjects()` in every generated Base class does the same. ASM works around this with 1,095 raw `Db::`/`->query()` calls in app code. For an ERP (invoices with lines, stock per branch) this is the first thing that will hurt.
3. **Framework is coupled to two app classes it does not own**: `<package>\Person::getLoggedUserId()` is called on every insert/update that has `created_by`/`updated_by`, and `<package>\Document` is required for file uploads. A fresh project without a `Person` table gets a fatal error on first insert. `BaseObject::setActiveUserId()` already exists and should be the only source.
4. **No transactions, no lifecycle hooks, no validation-error UX, no auth/ACL, no relationships, no i18n beyond a label array, no migrations, no CLI.** ASM built all of these itself (`RoutingTable`, `Group`, `GroupRole`, `Person`, `Email`, `ExcelRaportet`, cron scripts in `public/update/`). A second ERP would rebuild them again unless they move into LAF.
5. **A handful of real bugs ship on master** (section 4), including a PostgreSQL detection that can never succeed and a form helper that references a class that does not exist.

## 2. What already works and should be kept (verified)

- Active Record with change tracking (`Field::hasChanged`), auto `created_on/by`, `updated_on/by`, soft delete via `record_status_id` or `deleted` (`BaseObject.php:326`).
- Automatic audit log of inserts, changed-fields-only updates, deletes (`AuditLog.php`), toggle per object.
- FK validation on set (`ForeignKey::isValidValue`, parameter-safe), FK navigation (`getXxxObj()`), FK display-field resolution.
- Schema-driven code generation for MySQL and PostgreSQL (`TableInspector`, `PostgresTableInspector`), two-tier Base/main class split that survives regeneration, column-comment JSON to drive field config.
- `PhpGrid`: server-side paging/sorting/filtering with whitelisted sort columns and bound filter params, totals row, Excel/CSV export, JS callbacks per column and action button, lazy init (on `improvements1`).
- Form auto-generation in VIEW/INSERT/UPDATE from the same field list, which already satisfies the "same layout in all three modes" rule.
- Prepared statements everywhere in `BaseObject`; `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`.
- Component tree (Div/Row/Column/Card/Tab/Modal) with fluent CSS/attribute API; Bootstrap 5 classes already used in `GenericPage`.
- Settings singleton with a small, discoverable key set (16 keys, listed in appendix A).

## 3. Tier 0 — reconcile branches (hours)

| Step | Why |
|---|---|
| Merge `improvements1` into `master` (user decides merge vs rebase). Resolve the only overlap: `composer.json` monolog line and README. | Brings tests, CI, CSRF, PageGenerator fixes, QueryBuilder removal onto the branch `asm`/`routerarena` consume. |
| Delete the stale `origin/qb`, `origin/grid_features`, `origin/generator` branches or document what they hold. | `qb` and `grid_features` are behind master by 1,000+ lines; they will confuse the next person. |
| Fix `CLAUDE.md`: it references `1-example/` and `app/util/generate_classes.php`, neither exists. Point to `sample_files/class_generator.php` and `sample_project/asm`. | Keeps the onboarding doc truthful. |
| Decide what `sample_project/` is: it is untracked and is a copy of the production ASM repo (its own composer.json, node_modules). Either `.gitignore` it or replace with a trimmed example. | Untracked 564-file tree in a framework repo is a hazard for accidental commits. |
| Refresh the project memory after the merge (tests, CI, CSRF are branch facts today, not master facts). | Memory currently misleads. |

## 4. Tier 1 — small fixes (minutes to hours each)

All on master unless marked.

| # | File:line | Bug | Effect | Fix |
|---|---|---|---|---|
| 1 | `src/Laf/UI/Grid/PhpGrid/PhpGrid.php:899` | `isPostgres()` calls `$db->getAttribute()`, which `Db` does not define. The `\Throwable` catch swallows the `Error`. | Always returns false, so ILIKE / LOWER() case-insensitive search and sort never apply on PostgreSQL. | Add `Db::getAttribute(int $attr)` delegating to PDO, or read `Settings::get('database.engine')` like `BaseObject::isMySqlDriver()` does. Unit test this. |
| 2 | `src/Laf/UI/Form/Form.php:702,713` | `FormElementFactory::create()` — class does not exist anywhere in `src/`. | `addFieldsFromObject()`, `groupFields()`, `setFieldVisibilityCondition()` are dead on arrival; calling them fatals. | Replace with `$field->getFormElement()` or delete the three methods. |
| 3 | `src/Laf/UI/Traits/FormElementTrait.php:345` | `setAutocomplete()` writes attribute named `attribute`. | Autocomplete can't be controlled per input. | `addAttribute('autocomplete', $value ? 'on' : 'off')`. |
| 4 | `src/Laf/Database/Field/Field.php:774` | `setTableDisplayField()` calls `Table::setDisplayFieldName()`, which doesn't exist. | Fatal when used. | Call `setDisplayField($this)`. |
| 5 | `src/Laf/Database/BaseObject.php:1056,1082` | `returnLeafClass()` and `getAllFieldsAsFormElements()` call `static::` on themselves. | Infinite recursion if a subclass forgets the override (every generated class overrides, so latent). | Make them `abstract`, or implement `returnLeafClass()` as `return $this;` once in `BaseObject` and drop it from the generator template (late static binding already gives the leaf). |
| 6 | `src/Laf/Database/Db.php:138` | `execute()` declared `: int` but `return false` when no connection. | `TypeError` instead of a clear message. | Throw `DbConnectException`. |
| 7 | `src/Laf/Database/Db.php:370` | `getRowObject()` declared `?\stdClass` but fetches into `$classNamme`. | TypeError for any custom class. | Return type `object`. |
| 8 | `src/Laf/Database/Db.php:29,653` | `$logger` typed `LoggerInterface`, never initialised; `getLogger()` throws if unset. | Any caller of `getLogger()` must guard. | Make nullable or default to `NullLogger`. |
| 9 | `src/Laf/Database/Db.php:678` | `SHOW TABLES LIKE` in `handleSqlErrorLogging`. | SQL error logging breaks on PostgreSQL. | Query `information_schema.tables`. |
| 10 | `src/Laf/Database/AuditLog.php:15-22` | Constructor builds a `Table` and discards it; never calls `setTable()`. | `new AuditLog()` then anything touching `getTable()` fails. Static `logChange()` path is fine. | Wire the table or remove the ctor. |
| 11 | `src/Laf/Database/BaseObject.php:326-349` | Soft delete via `record_status_id` does not write the audit DELETE entry; the `deleted` path does. | Inconsistent audit trail. | Log in both branches. Also `canSoftDelete()` (line 1117) only checks `deleted`, not `record_status_id`, so the generated delete page hard-deletes tables that use `record_status_id`. |
| 12 | `src/Laf/Database/Field/Field.php:261` | `str_contains($value, ',')` with null `$value`. | Deprecation on PHP 8.1+. | `str_contains((string)$value, ',')`. |
| 13 | `src/Laf/UI/Form/Form.php:534-535`, `TabContainer.php:80-81`, `PhpGrid.php:1326` | Inline jQuery (`$(...)`, `$(document).ready`). | Violates the project's no-jQuery rule; the only jQuery left in the framework. | Vanilla `querySelector` + `DOMContentLoaded`; `improvements1` already replaced the grid one with `Grid.autoInit`. |
| 14 | `src/Laf/Database/QueryBuilder.php` | Backticks only (MySQL), field names interpolated without validation, `with()` calls a no-op `loadRelation()`. | Half-finished, unsafe if field names come from input. | Delete (already done on `improvements1`) and design properly in Tier 3. |
| 15 | `src/Laf/Database/Table.php:84` | `getNameRot13()` returns the plain name while `Field::getNameRot13()` really rot13s. | Harmless today, confusing. Drop the rot13 "obscurity" layer entirely; it isn't security. | Rename to `getHtmlName()` or similar. |
| 16 | `phpstan.neon`, `rector.php` | Dev deps installed, no config. | Static analysis never runs. | Add level 5 phpstan.neon scoped to `src/`, run it in CI; fix what it reports (expect ~50 findings: missing return types, undefined methods above). |

## 5. Tier 2 — medium improvements (days each)

### 5.1 ORM performance: hydrate from rows, not from IDs
`bOfind()` (`BaseObject.php:170`) runs one query for IDs and then `new static($id)` per row, so each row costs a `SELECT *` plus a full `buildClass()`. Same in the generated `listAllObjects()`.

- Add `BaseObject::hydrateFromRow(array $row): static` (calls `loadValueFromDb` per field, sets `recordSelected`), and make `bOfind`/`findAsArray`/`listAllObjects` do a single `SELECT *` and hydrate.
- Add `find(array $criteria, array $orderBy = [], ?int $limit = null, int $offset = 0)` so pages can paginate objects without raw SQL.
- Cache the `Table` metadata per class in a static map: `buildClass()` currently re-creates ~50 `Field` objects per instance. For a grid of 100 FK lookups that is 5,000 object constructions per request.

**Why:** an ERP list of 50 invoices with customer, agent, branch FKs is 1 + 50 + 150 queries today. After this it is 1 + 3.

### 5.2 FK display and select inputs
- `Field::getReferencedValue()` (`Field.php:592`) instantiates the full referenced object to read one display column. Replace with one `SELECT display FROM ref WHERE pk = ?`, memoised per request in a static `[table][id] => label` map (this is the "cache lookup tables" rule from the coding conventions).
- `Select::getOptions()` (`Select.php:94`) loads every row of the referenced table and interpolates `dbSelectionCriteria` values into SQL (marked `@TODO`). For `artikujt` (tens of thousands of rows) a select is unusable. Add: bound parameters; a row cap with a configurable threshold above which the input renders as a searchable autocomplete backed by a JSON endpoint (`?module=x&submodule=y&action=options&q=...`), implemented in vanilla JS; `ORDER BY` and active-only (`record_status_id = 1`) default per convention rule 7.

### 5.3 Transactions
`Db` has no `beginTransaction/commit/rollBack`. ERP operations are multi-table (invoice header + lines + stock movement + ledger). Add `Db::transaction(callable $fn)` that nests via a depth counter, rethrows, and `BaseObject::storeWithRelated()`-style helpers later. ASM has zero transactions today, which means a failed line insert leaves a header behind.

### 5.4 Decouple from `Person` and `Document`
- `BaseObject::insert()/update()` (`:502`, `:640`): use `self::getActiveUserId()` only. Keep a one-line fallback to `Person::getLoggedUserId()` behind `class_exists()` for ASM compatibility, deprecated.
- `Filesystem\Document` (`:33`, `:46`): define `DocumentInterface` (or a `document.class` setting) and make non-image uploads work (today anything SimpleImage can't open returns `null` silently, so PDFs and XLSX can't be attached).

### 5.5 Validation that reaches the user
Today a required-field miss throws `MissingFieldValueException` from `processForm()`, which the generated page does not catch, so the user sees a fatal card. Add:
- `BaseObject::validate(): array` returning `[field => message]` (required, max length, min/max value, unique via the still-TODO `checkUniqueFieldsForDuplicateValues()` at `:1108`, FK existence, date-not-in-future hooks).
- `Form::processForm()` returns a result object or throws a `ValidationException` carrying the array; the form redraws with Bootstrap `is-invalid` + `invalid-feedback` per field and keeps submitted values.
- Keep HTML5 attributes (`required`, `maxlength`, `min`, `max`) as the client layer; they are already emitted.

### 5.6 Money and decimals
`decimal`/`numeric` columns map to `TypeFloat` (`FieldTypeFactory.php:36-40`), which casts to PHP float. For prices, VAT, customs values (ASM has `cmimiblerjes`, `tvsh`, `dogana`, `marzha`…) binary float drifts. Add `TypeDecimal` that keeps the value as a string, validates with a regex, binds as `PARAM_STR`, and exposes `bcadd`-friendly helpers; read scale from `NUMERIC_SCALE` in the inspector and emit `step` on the input.

### 5.7 Non-integer primary keys
`update()` binds the PK as `PARAM_INT` (`BaseObject.php:702`), `ForeignKey::isValidValue()` casts to `(int)`, `UrlParser::_getId()` returns null for non-numeric. UUID keys are half-supported (insert generates one). Either support them end to end or document "integer PKs only".

### 5.8 Output escaping policy
`getValueForHtml()` escapes values, but labels, hints, placeholders, attribute values (`Text.php:186,190`), grid column labels (`PhpGrid.php:1315`), and `Link`/`Alert` text are emitted raw. Labels come from developers, so risk is low, but ERP labels get edited by admins eventually. Add `Util::e()` and apply it in the component traits once, with an explicit `->setRawHtml()` opt-out.

### 5.9 Settings
`loadFromFile()`/`saveToFile()` are stubs (`Settings.php:77-92`). Add `Settings::loadFromArray()`, `loadFromEnv()` (`LAF_DATABASE_HOSTNAME` → `database.hostname`), and `get($key, $default)`. Document the 16 keys the framework reads (appendix A); today they are only discoverable by grep.

### 5.10 Lifecycle hooks
No `beforeInsert/afterUpdate/...`. ERP logic (recalculate totals, allocate a document number, update stock) lands in overridden `store()` today. Add protected no-op hooks in `BaseObject` called from `insert()/update()/hardDelete()/softDelete()`, plus `afterLoad()`. Cheap, and it makes Tier 3 features (numbering, events) possible without touching generated code.

### 5.11 Soft-delete-aware finders
`find()` returns deleted rows. Per convention rule 7, default to active rows when the table has `record_status_id`/`deleted`, with `withDeleted()` opt-in.

### 5.12 PostgreSQL parity checklist
Besides #1 and #9 above: `Select::getOptions` backtick branch is right, but `QueryBuilder` and `getListAllSimpleTableObject` are MySQL-only; `TableInspector` `COLUMN_KEY`/`EXTRA` are MySQL-only (PG inspector handles its own). Add a PG integration job to CI (already in `improvements1`'s `ci.yml`) and keep it green.

## 6. Tier 3 — large improvements (weeks; architectural)

Ordered by ERP value.

### 6.1 Authentication, sessions, and permissions module
ASM hand-built `Person` (login, activation, password reset, device approval, API key), `RoutingTable`, `Group`, `GroupRole`, `GroupRoutingTable`, and `noperm.php`. None of it is reusable. Propose `Laf\Auth`:
- `UserInterface` + `SessionAuth` (password_hash, remember-me, login throttling), `ApiKeyAuth`.
- `Permission` strings per module+action (`invoice.view`, `invoice.update`), role → permission table seeded with explicit IDs (convention rule 1), `Gate::allows()`.
- `PageGenerator` emits a permission check per `case` and hides action buttons the user lacks.
- Audit log already records `user_id`; wire `setActiveUserId()` from the auth module automatically.

### 6.2 Relationships and a real query builder
Replace the deleted `QueryBuilder` with one that: validates identifiers against `Table` metadata, quotes per engine, supports `where/orWhere/whereIn/between/null`, joins, `orderBy`, `limit/offset`, `count()`, `paginate()`, and returns hydrated objects via 5.1. Then generate relationship methods from FKs: `hasMany` (`Invoice::getLines()`), `belongsTo` (exists as `getXxxObj()`), `belongsToMany` through junction tables (detection already exists on `improvements1` in `PageGenerator::detectJunctionTable`), with batched eager loading (`->with('lines')` = one `WHERE invoice_id IN (...)`).

### 6.3 Front controller, modules, and routing inside LAF
Each app writes its own `App::bootstrap()` + `index.php` + header/footer templates (ASM: `App.php`, `Factory.php`, Twig). Provide `Laf\Http\Kernel` that: parses the URL (`UrlParser` exists), resolves `module/submodule` to a `.page` file in registered module directories, applies auth (6.1), wraps output in a configurable layout (`Html` exists), handles exceptions with a dev/prod switch, and exposes flash messages (exists on `improvements1`). Modules become folders (`modules/Sales/{pages,src}`), which is the ERP shape ASM already has informally (`app/fatura`, `app/artikujt`, …).

### 6.4 Internationalisation
Framework strings (`'Submit'`, `'Are you sure…'`, grid labels) are hard-coded or come from one `settings.label.translations` array. Models set labels by hand (ASM: 129 `setLabel()` calls). Add `Laf\I18n\Translator` with `t('invoice.field.total')`, per-locale PHP/JSON files, generator emitting label keys instead of English literals, and locale-aware date/number formatting using the existing `locale.*.format` settings. Albanian sentence-case rule can live in the seed files.

### 6.5 Migrations and a console
No migration runner; ASM keeps `migrations/sql/*.sql` and applies by hand. Add `bin/laf` (symfony/console or a 200-line hand-rolled dispatcher): `migrate`, `migrate:make`, `generate:classes`, `generate:pages`, `object-list:populate`, `cache:clear`. Migration table + ordered SQL/PHP files, with the "seed lookup tables with explicit IDs then reset sequence" helper from the coding conventions.

### 6.6 Reporting and export layer
ASM's `ExcelRaportet` is raw SQL + `php_xlsxwriter` per report. `PhpGrid` already exports XLSX/CSV; generalise into `Laf\Report\Report` (SQL or query builder source, parameters with types/validation, column formats, totals, XLSX/CSV/PDF output via one writer) so a report is a class, not a page.

### 6.7 JSON API surface
`Person::restApiGetAll()` is a hand-rolled API. Add `Laf\Api\Resource` that exposes `list/get/create/update/delete` for a `BaseObject` with the same validation (5.5), permission (6.1), pagination (5.1), and field whitelist. Needed for mobile scanners, e-shop sync, and Postman testing.

### 6.8 PHP 8.1+ modernisation
`composer.json` says `>=8.0`; CI tests 8.1–8.4. Raise to 8.1, then: `DrawMode`, `ContainerType`, `FieldType` constants → enums; typed properties everywhere (most are untyped `private $x`); `readonly` on `Table`/`Field` metadata; `match` in `FieldTypeFactory`; Rector can do 70% of this mechanically once `rector.php` exists.

### 6.9 Documentation and a starter kit
README is three lines. Add: install guide, `create-project` skeleton (`public/index.php`, `config/`, `modules/`, `bin/laf`), the config-key reference, a "build a Customer module in 10 minutes" walkthrough, and an upgrade note for `asm`/`routerarena`.

## 7. Suggested order

1. Tier 0 (merge, cleanup, memory refresh) — one evening.
2. Tier 1 bug list with a unit test per fix — a weekend; run the `improvements1` suite plus PHPStan.
3. 5.1 hydration + metadata cache, 5.3 transactions, 5.4 decoupling, 5.10 hooks — these unblock everything else and are backwards compatible.
4. 5.5 validation UX, 5.2 FK selects, 5.6 decimals — the parts an ERP user feels first.
5. 6.1 auth and 6.3 kernel together (they share session/permission plumbing), then 6.2 relationships.
6. 6.4 i18n, 6.5 console/migrations, 6.6 reports, 6.7 API, 6.8 modernisation, 6.9 docs.

Downstream check: `asm` and `routerarena.com` pin `lavdiu/laf ^1.1`. Tiers 0–2 can ship as 1.x (additive, bugfixes). Tier 3 items that change generated-code shape (6.1, 6.2, 6.3) should be a 2.0 with a regeneration note.

## 8. Not recommended

- Rewriting LAF on Laravel/Symfony: the generator-driven, DB-first flow is the framework's reason to exist and ASM proves it scales. Borrow ideas (validation results, hooks, console), not the stack.
- Keeping `SimpleTable`: it is deprecated with known XSS; `PhpGrid` covers it. Remove in 2.0.
- The rot13 field-name scrambling: delete rather than "fix"; it adds friction (`getNameRot13`, `scrambleFieldOrTableName`) for no security.

## Appendix A — settings keys the framework reads (by grep)

`project.package_name` (9 uses), `database.engine`, `database.hostname`, `database.database_name`, `database.username`, `database.password`, `database.port`, `404`, `locale.date.format`, `locale.datetime.format`, `locale.time.format`, `upload.documents_directory`, `upload.allowed_mime_types`, `settings.use_pretty_url`, `settings.label.translations`.

## Appendix B — ASM evidence (what a LAF ERP builds by hand today)

- 119 model classes, 102 generated Base classes, 141 `.page` files.
- 129 `setLabel()` calls for Albanian labels; 1 form-element override; 2 `dbSelectionCriteria` uses.
- 1,095 raw `Db::`/`query`/`prepare` calls in app code; 0 transactions.
- 15 hand-written `PhpGrid` builders in models; 15 pages with inline `setSqlQuery`.
- Own auth stack (`Person`, `RoutingTable`, `Group*`, device approval), own email (`PHPMailer`), own reports (`ExcelRaportet`), own cron scripts (`public/update/*.php`), Twig for layout.
- `PERFORMANCE_ANALYSIS_artikujtGjendjaKrejt.md` in the sample documents a cartesian-product stock recalculation; a framework-level batch/aggregate helper would not have prevented it, but hooks (5.10) plus a stock-movement table pattern would make such recalculations incremental.
