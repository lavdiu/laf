Independent analysis of the whole LAF framework (improvements1, 2026-10-12) set against Gemini's audit: six verified defects, then design, usability, modernisation and packaging improvements ranked by value, with what asm and routerarena re-implement by hand as the evidence of what is missing.

# Method, and how it differs from Gemini's

Gemini read the framework source and graded it. This pass did that too (ORM, UI, application layer, each
claim checked against file and line), and added the angle Gemini lacked: what the two production
applications built on LAF (asm: 125 model classes, 44 pages plus legacy code; routerarena: 103 model classes,
69 pages) implement by hand, repeatedly, or copy between each other. That is the most reliable signal of a
missing framework feature. Every item below was confirmed by reading code; counts come from grep over the
apps without vendor/, node_modules/, Base/ and generator_cache/.

Constraints kept throughout: composer floor php >=8.0 (nothing below needs a higher floor unless marked),
backwards compatibility with both apps, MySQL/MariaDB and PostgreSQL parity, no jQuery, Bootstrap 5.

# 1. Defects found (verified, none in Gemini's report)

Status after the quick-win pass on the same day: D1 left as is on the user's decision; D2 fixed (related tabs and
junction grids link to the other table's own page through `PageGenerator::moduleForTable()`, config
`module_for_table`, no Delete there); D3 fixed (`Db` creates a fresh logger object per entry); D4 fixed with
`Query::whereGroup()` / `orWhereGroup()` / `when()` (the class stays final, scopes are still open); D5 fixed for
`hardDelete()` (false on 0 rows; `update()` is left alone because MySQL's row count excludes unchanged rows); D6 open.
Of the smaller items the pretty-URL boolean, the Locale docblock, the `object_list` base-table filter, the leaf
`find()` template and `json_validate()` are done; the unique key on `object_list` is open.

Second quick-win pass the same day (D1 stays: the user confirmed that `record_status` id 0 is the deleted status
by convention, so the applications must carry that row): M5 `Query::whereContains()` / `whereStartsWith()` /
`whereEndsWith()` with escaping and `ILIKE` on PostgreSQL; the scalar half of F2 (`sum()`, `min()`, `max()`,
`avg()`, `pairs()`; `paginate()`, `cursor()`, `groupBy()` still open); X4 `load()` / `loadOrFail()` with
`RecordNotFoundException` (display value and `firstOrCreate()` open); U3 `Locale::formatNumber()` / `formatDate()`
/ `formatDateTime()` / `formatTime()` (view-mode use behind a flag still open); the redirect terminate flag from the
fact-check; packaging: composer `scripts`, `branch-alias`, `suggest`, `.gitattributes` export-ignore (changelog,
tag and the Monolog move still open). U1 (errors in inactive tabs) was looked at and needs draw-time traversal of
the component tree, so it is not a quick win.

Third pass: the rest of F2 (`paginate()` with `Laf\Database\Pagination`, `cursor()`, `chunk()`; `groupBy()` still
open), the FK getter memoisation from the fact-check (`BaseObject::relatedObject()`, generated `getXxxObj(bool
$fresh)`), U4 flash messages (`Laf\UI\Flash`, generated pages use it, legacy session keys still read), X8 the
environment half (`Settings::loadFromEnvironment()` / `loadEnvFile()`; the database provider is open), X5
`Form::buildRequest()` (the test base class is open). The exception marker interface (X10) was dropped as lowest
value.

Fourth pass (2026-10-13, user request "validate the store method, foreign-key autocomplete"): D6 / F6 done.
`Field::setMinLength()` is checked now, `setPattern()`, `addValidator()`, decimal bounds (`setMinValue('0.01')`),
the model hook `validateRecord()` (in `validate()` and in forms), opt-in validation inside `store()`
(`setValidateOnStore()` / Settings `orm.validate_on_store`, every field on insert, changed fields on update) with
`Laf\Exception\ValidationException` as the parent of `FormValidationException`. Kept opt-in because asm and
routerarena rows may already hold values that break rules nobody enforced before. F9 done: `Input\Autocomplete`
(hidden key + search box, vanilla script printed once), `Form::handleLookupRequest()` called by generated pages,
Settings `form.select_autocomplete_threshold`, demo `sample_files/autocomplete_demo.php`. Not done: replacing
asm's 26 jQuery UI autocompletes (app work; they keep working), a "create new" entry in the list, multi-value
autocomplete.

| # | Defect | Evidence | Effect |
|---|---|---|---|
| D1 | `softDelete()` writes `record_status_id = 0` unconditionally | `BaseObject.php:923`; asm `RecordStatus::DELETED = 5`, its `record_status` table holds ids 1-7 (migration 2020-01-19), asm calls `softDelete()` in 128 files and has a foreign key on the column in 5 tables; routerarena uses `DELETED = 0` | On asm's FK-constrained tables `setFieldValue()` throws `InvalidForeignKeyValue` (`BaseObject.php:153-157`); on the others a status asm never treats as deleted is written. Same code in asm's vendor copy. |
| D2 | Generated related-records tabs link to the wrong table | `PageGenerator.php:339` builds the child grid with `buildGrid($refTable, ...)`, whose buttons are `sprintf('?module=%s&action=view|update|delete&id={child_pk}', UrlParser::getModule())` (`:537-541`) with the parent form's CSRF token | View/Update open the parent record whose id equals the child id; Delete removes it. Affects pages produced by the rewritten generator, not the apps' hand-written pages. The query-string form also ignores pretty URLs. |
| D3 | SQL error logger overwrites the first error of a request | `Db::writeErrorLog()` reuses the single registered instance (`Db.php:1641-1666`); both apps' `storeLogEntry()` is `$this->store()` | After the first insert `store()` becomes an UPDATE, so the second failure replaces the first row. Fix on the LAF side (fresh instance per entry) repairs both apps. |
| D4 | `Query` joins conditions flat | `Query::whereSql()` (`Query.php:412-422`): `where(a)->where(b)->orWhere(c)` is `a AND b OR c` | Any `orWhere` silently drops the preceding filters; no grouping closure exists and the class is `final`, so a model cannot add scopes. |
| D5 | `update()`/`hardDelete()` never check the row count | `BaseObject.php:844, 891-896` | A row deleted by another user is "updated" with 0 rows and reported as success. |
| D6 | Validation is not applied on `store()` | `insert()`/`update()` check required and unique only (`BaseObject.php:728, 822`); `validate()` runs only from forms; `setMinLength()` is never checked server-side; `setMinValue()/setMaxValue()` take `int` only | Imports, APIs and CLI writes bypass the type rules; a decimal minimum of 0.01 cannot be expressed. |

Smaller, verified: `UrlParser.php:45` casts the pretty-URL setting with `(bool)` so `"false"` from an ini
file means true; `Locale.php:15` documents the default time format as `H:i A` while the constant is
`g:i A`; `object_list` population has no `BASE TABLE` filter on MySQL (`BaseObject.php:1551`) unlike the
PostgreSQL branch; the generated leaf `find()` drops the `orderBy`/`limit` arguments of `bOfind()`
(`ClassGenerator.php:422-425`); `populateTableObjectList()` creates no unique key on `table_name`.

# 2. Comparison with Gemini's audit

- **Both found:** N+1 on `getXxxObj()`, metadata rebuilt per instance, hard exits, `Query` without
  joins (by design), ROT13 names, delete via GET, the Grid defects (fixed since).
- **Gemini missed:** every defect above, the fact that no dispatcher, page factory, access hook, flash
  API, translator or formatter exists (the apps carry about 15k lines of copy-pasted admin shell for
  these), soft-delete scoping, has-many navigation, aggregates and pagination on `Query`, bulk writes,
  foreign-key selects that load whole tables, validation errors invisible in inactive tabs, packaging
  (no tag since v1.2.1 of 2026-02-13, malformed `v.1.2.0`, no changelog, no `export-ignore`, Monolog
  required but unused in `src/`).
- **Gemini overstated:** the `Db::getAttribute()` claim (the method exists), `Util::isJson()` (does not
  exist), `database.schema` (exists), SQL injection through grid configuration, the flyweight's memory
  figures (measured: 13 KB and 41 microseconds per 19-field instance).
- **Gemini's modern-PHP section** (enums, readonly, property hooks, typed constants) is the only part
  that needs a floor above 8.0. asm declares `php >=8` (platform 8.4), routerarena `>=8.2`. Raising the
  floor is the user's decision; nothing below depends on it.

# 3. Design features

Ranked by value for an ERP built on LAF. Effort S/M/L, BC = backwards-compatibility risk.

| # | Feature | What exists / what the apps do | Sketch | Value / effort / BC |
|---|---|---|---|---|
| F1 | **Soft-delete contract**: configurable deleted/active values, `restore()`, `deleted_on/by` stamps when the columns exist, opt-in scope | D1; routerarena writes `record_status_id = 1` by hand 57 times; generated list grids show deleted rows | `Settings orm.soft_delete_value` (default 0), `Invoice::query()->withoutDeleted()`, `->onlyDeleted()`, `PhpGrid::setSoftDeleteFilter()` | high / S / low |
| F2 | **Query grouping, scopes, aggregates, pagination, streaming** | D4; terminals are get/first/arrays/column/count/exists/delete; asm has 16 raw `SUM(` queries; `listAllObjects()` loads whole tables (114 asm files); no chunk/cursor anywhere | `whereGroup(fn(Query $q) => ...)`, `when($cond, fn)`, `scope('active')` via `scopeActive(Query)` on the model, `sum/min/max/avg`, `pairs('id','name')`, `groupBy()`, `paginate($page, 25)`, `cursor()` generator, keyset `chunk()` | high / S-M / none |
| F3 | **Has-many navigation** generated per incoming foreign key | Only belongs-to `getXxxObj()` is generated (`ClassGenerator.php:302-318`); apps hand-write `Article::find(['manufacturer_id' => ...])` | `getInvoiceLineQuery(): Query`, `getInvoiceLineList(): array`; generator warns on a clash with a leaf method | high / M / low |
| F4 | **Dispatcher, page factory, access policy** | Both apps hand-write the same `App::bootstrap()` (routing_table lookup, login, device approval, permission array, include); generated pages call `{ns}\Factory::GeneralPage()` which LAF neither ships nor generates, so a new app fatals on its first page; no page checks permissions per action | `Dispatcher($pagesDir)->setPageResolver()->before(Access::check)->onError()->run()`; `DefaultFactory::GeneralPage()`; `AccessPolicy::can($module, $action, $id)` with an allow-all default; generated pages call `Access::requireAction()` | high / M / none |
| F5 | **CRUD page object** (`ResourcePage`) owning find-or-404, validation redraw, CSRF delete, flash, related tabs | 50 of 69 routerarena pages repeat the same switch (14.7k lines); 0 pages catch `FormValidationException`; 0 check `recordExists()`; deletes are plain GET links | `ResourcePage::for(Tag::class)->layout(fn)->listQuery($sql)->extraAction('publish', fn)->handle()`; generated pages shrink to the overrides | high / M / none (new class) |
| F6 | **Validation rules on fields and models, applied on `store()` opt-in** | D6 | `Field::addValidator(fn($v, $f) => ...)`, `setPattern()`, `rules(): array` on the model, `Settings orm.validate_on_store` | high / M / low |
| F7 | **Concurrency**: `lockForUpdate()`, optional version column, deadlock detection and retry, stale-row exception | D5; routerarena writes `FOR UPDATE` by hand; `DatabaseException` has unique/FK checks but no deadlock check; `Db::transaction()` has no retry | `->lockForUpdate()->skipLocked()`, `VERSION_FIELD` constant, `isDeadlock()`, `transaction($fn, retries: 3)` | high / M / low |
| F8 | **Bulk insert, upsert, bulk update** | `Db::insert()` is one row; no `ON DUPLICATE KEY` / `ON CONFLICT` anywhere; `Query` has `delete()` but no `update()` | `$db->insertMany($table, $rows, 500)`, `$db->upsert($table, $rows, $keys, $update)`, `Item::query()->where(...)->update([...])` (no hooks, documented like `delete()`) | high for imports / M / none |
| F9 | **Foreign-key autocomplete** | `Select::loadOptionsFromReferencedTable()` has no LIMIT (`Select.php:308-317`); asm has 26 jQuery UI autocompletes over about 10 hand-written JSON endpoints | `Autocomplete extends Select` answering its own `?laf_lookup=field&q=` request like PhpGrid does; column comment `"element":"Autocomplete"`; `form.select_autocomplete_threshold` | high / M / low |
| F10 | **Report filters bound to PhpGrid** | asm: 27 report files (6.3k lines) each with a GET date-range form, raw query, hand-built table and a separate XLSX branch (39 files) | `$grid->addFilter(new DateRangeFilter('date'))->addFilter(new SelectFilter('branch', $pairs))` mapping into `:params`, persisted in the URL, exported with a title/period header | high for asm / M / none |
| F11 | **Model-level events** across classes | Hooks are per-class protected no-ops; nothing can listen on all models (cache invalidation, outbox, search index) | `BaseObject::on('afterUpdate', Invoice::class, fn)`, `'*'` wildcard | medium / M / none |
| F12 | **Models on a named connection** | Every model path hard-codes `Db::getInstance()` (`BaseObject.php:1009`, `Query.php:481`, `ForeignKey.php:313`); asm has 3 named connections | `protected static ?string $connection`, `query()->on('replica')`; quoting follows the model's engine | medium / M / low |
| F13 | **Document number sequences** | asm `Grup::newID()` builds invoice numbers from a `MAX()` read (race-prone), 8 wrappers | `Sequence::next('invoice', ['year' => ..., 'branch' => ...], '{prefix}{Y}{n:6}')` on a `laf_sequence` row under `transaction()` | medium / S / none |
| F14 | **Tenant / branch scope** | asm filters `filialja` by hand in 24 files, 118 `getFilialjaId()` calls | `Query::addGlobalScope('branch_id', fn() => Tenant::id())`, read by PhpGrid params and inserts | medium (asm) / M / low |

# 4. Usability for end users

| # | Item | Evidence | Sketch | Value / effort |
|---|---|---|---|---|
| U1 | Validation errors invisible in inactive tabs and layout-hidden fields; `required` kept on `d-none` elements blocks the submit with no visible message | `FormLayout.php:505-548`, `TabContainer.php:43-56`, `Text.php:368-370` | activate the first tab with errors, `TabItem::setBadge()`, drop `required` from hidden elements (server `validate()` still enforces) | high / S |
| U2 | Runtime i18n of framework strings | Submit/Cancel/Previous/Next, "%s is required", "The form is out of date", "Details", grid.js "Search", "No records found", "Clear filters"; `settings.label.translations` is read only by `BaseObject.php:1448` and the generator; asm overrides the submit caption by hand 18 times | `Translator::t('validation.required', '%s is required', $label)`, `loadFromFile('sq.php')`, grid payload `texts`, generator emits `t()` calls | high / M |
| U3 | Locale formatting of values | `Locale` parses but cannot format; asm helpers `tsNeDate` (82 uses), `tsNeDateOre` (65), `GTE` (103), raw `number_format` (38); view mode shows `1234567.50` | `Locale::formatNumber/Date/DateTime/Money()`, `Format::yesNo()`; numeric inputs use it in view mode behind `form.format_numbers` | medium-high / S |
| U4 | Flash messages | Generated pages write `$_SESSION['flash_message']` in six places; asm passes messages in redirect URLs (80 occurrences); routerarena uses ad-hoc session keys | `Flash::success()/error()`, drained by `Html::draw()` as dismissible alerts; `redirectToViewPage($id, $flash)` | medium / S |
| U5 | Generated list/view pages: labels from column comments, `form.exclude` respected in lists, record identity in the view title, FK values linked in view mode, breadcrumb | `PageGenerator.php:239, 525, 554`; `Select.php:134-170` | generator reads `ColumnComment` labels; `setSubtitle($obj->getDisplayValue())`; `Breadcrumb` component; `Select::setViewLinkBuilder()` | medium / S-M |
| U6 | "Add" in related tabs with the parent key prefilled, and a return URL after save | `PageGenerator.php:342-351, 168-174` | `Form::prefillFromQuery(['customer_id'])` (FK-validated), same-origin `return` parameter | medium / S-M |
| U7 | Grid state survives Back; row selection and bulk actions | grid.js persists only rows-per-page and hidden columns | `setRememberState(true)` (sessionStorage + `history.replaceState`), `setSelectable('id')->addBulkAction()` posting ids with the CSRF token | medium / M |
| U8 | Accessibility: page title as a heading, `aria-hidden` on icons, `aria-selected` on tabs, error summary linking to fields, `<main>` landmark, required marker not read as "star" | `GenericPage.php:113`, `TabItem.php:106-117`, `Form.php:702-714`, `Html.php:278-289`, `Text.php:508-510` | opt-in setters, added attributes only | medium / S |
| U9 | Record history tab from the audit log | Both apps write `audit_log` automatically and no page reads it; asm keeps a parallel `syslog` activity log (130 calls) | `AuditLog::historyTab($obj)` diff table with user names; generator adds it to view pages | medium / S |

# 5. Usability for developers

| # | Item | Evidence | Sketch | Value / effort |
|---|---|---|---|---|
| X1 | New-application path: front controller, rewrite rule, page factory, assets | README never says how a request reaches a `.page`; `uriRewrite` has no shipped rewrite rule; `grid.js` only in `sample_files/`; nine hidden steps today | `sample_files/public/index.php`, `.htaccess` (`RewriteRule ^(.*)$ index.php?uriRewrite=$1 [QSA,L]`), nginx snippet, `Factory.php`; later `laf-generate --init` or a skeleton package | high / S (samples) |
| X2 | Generated classes help the IDE and PHPStan | Every getter is `@return mixed` although the type is known (`ClassGenerator.php:274-281`); no column-name constants; ENUM options not exposed; JSON columns return raw text | typed docblocks only (never native return types: leaf overrides), `COL_CUSTOMER_ID` constants, `STATUS_OPTIONS`, `getMetaArr()` | medium / S |
| X3 | Leaf-class boilerplate | 105 of 125 asm and 83 of 103 routerarena leaf classes repeat `__construct`, `returnLeafClass()`, `find()`, `findOne()` only to narrow types; `getIcon()` hand-written in 43 classes | `static` return types and `@return static[]` on `BaseObject`; icon and label from a table-comment JSON `{"table":{"icon":"...","label":"..."}}` | medium / S |
| X4 | Loading and finding | `new X($missingId)` is silently empty; `bOfind` with a misspelled key returns `[]` while `Query` throws; no `loadOrFail`, `firstOrCreate`, `__toString`/display value although `Table::getDisplayField()` exists | `X::load($id): ?static`, `loadOrFail()`, `firstOrCreate()`, `updateOrCreate()`, `getDisplayValue()`, `findByDisplay()`; leaf `find()` template passes `orderBy`/`limit` | medium / S |
| X5 | Test kit for applications | `LafShell` is autoload-dev only; `Db::setInstance/resetInstance` and `UrlParser::reset()` exist but are unpublished; asm tests cover helpers only, routerarena tests hit the live DB; `Form::setRequest()` needs rot13 keys, markers, token and signed field list built by hand (`tests/FormTest.php:670-676`) | `LafTestCase` (Settings snapshot, resets, `$_SESSION`), `Form::buildRequest($values, $mode)`, `HtmlAssert` | medium / S |
| X6 | Request, response and link helpers | asm reads `$_GET` 1,853 times with 63 `is_numeric` guards, builds 1,058 `?module=` links by hand (371 with numeric routing ids), 264 raw `header('location:')`; `UrlParser` builds links for the current module only and `linkId()` rejects `{col}` placeholders (D2) | `UrlParser::linkTo($module, $submodule, $action, $idOrTemplate)`, `settings.base_path`, `Request::int('id')`, `Request::date()`, `Response::json($data, $status, $terminate)`, `safeRedirectTarget()` | medium / S |
| X7 | Session and security helpers | sample bootstrap calls bare `session_start()`; asm hand-rolls cookie hardening, CSRF helpers, same-origin check (`functions.php:11-82`); 22 app files emit JSON headers by hand | `Session::start(['samesite' => 'Lax'])` (httponly, secure on HTTPS, strict mode) | medium / S |
| X8 | Settings from the database and the environment | Both apps' `AppConfiguration::getProperty()` runs `SELECT * FROM app_configuration` on every call; env mapping lives only in the sample bootstrap; both apps commit credentials in `data/config.php` | `Settings::addProvider(new DbSettingsProvider(...))` loaded once, `Settings::loadFromEnvironment('LAF_')`, `.env` loader | medium / S |
| X9 | SQL error logger implementation and migration | LAF ships the 14-method interface only; both apps hand-write the same `SqlError`; `setCustomMessage()` is a TODO stub in both | `SqlErrorLog` using `$db->insert()`, `migrations/create_sql_error_table*.sql`, fresh instance per entry (fixes D3) | medium / S |
| X10 | Exceptions and error page | Nine exceptions with no common marker; apps invent `NoFailureException`/`LoginErrorException`; routerarena's `Factory.php:59-63` prints `$e->getMessage()` unescaped and always answers 404 | `LafExceptionInterface`, `HttpException($status)`, `NotFoundException`, `AccessDeniedException`, `ErrorPage::render($e, $debug)` | medium / S |
| X11 | Many-to-many editing in forms | `detectJunctionTable()` feeds a read-only grid only; multi-selects store a comma-joined value in one column | `FormLayout::manyToMany('tags', 'order_tag', 'order_id', 'tag_id')` diffing junction rows in a transaction | medium / L |

# 6. Modernisation (all within the 8.0 floor)

| # | Item | Evidence | Sketch |
|---|---|---|---|
| M1 | Framework JavaScript companion and asset delivery | grid.js is forked in both apps (asm 1,350 lines, routerarena 1,553, five copies with different checksums); date inputs emit `date-picker` classes and rely on app JS (routerarena never initialises its picker); confirms are inline `onclick`; no unsaved-changes guard | `resources/js/laf.js` (vanilla, about 200 lines): `[data-laf-confirm]`, picker init from `data-format` produced by `Locale`, dirty guard, first-invalid focus with tab reveal, Ctrl+S; `Html::addLafAssets($baseUrl)` with a version cache-buster; `vendor/bin/laf assets:publish` |
| M2 | CSP-ready output | `Link::setConfirmationMessage()` writes `onclick`; Previous/Next use `href='javascript:;'`; `Html::addInlineScript()` takes no attributes although `HeaderComponent::inlineScript($code, $attributes)` could carry a nonce | `Html::setCspNonce($nonce)` on every inline tag it draws; `Link::setConfirmMode(Link::CONFIRM_DATA)`; default stays inline |
| M3 | Theming and icons | Fixed light colours (`Html.php:33`, `GenericPage.php:37-39`, `TabItem.php:116`, `Text.php:144`) break Bootstrap 5.3 `data-bs-theme="dark"`; `<html>` attributes cannot be set; Font Awesome classes hard-coded in PageGenerator, Form, TabContainer; asm ships FA 5, routerarena FA 6 | `Html::setHtmlAttribute('data-bs-theme', 'auto')`, `Icon::set([...])`/`Icon::cls('edit')`, theme-aware classes behind a flag |
| M4 | User attribution and clock | `setActiveUserId(?int)` and `AuditLog` bind the user as int (UUID user keys cannot be attributed); fallback hard-codes `<package>\Person::getLoggedUserId()`; timestamps use PHP `date()` ignoring `database.timezone` and unfreezable in tests | `BaseObject::setUserResolver(fn)`, `setClock(fn)`, parameter widened to `int|string|null` |
| M5 | `Query` LIKE parity and escaping | `Query` binds raw LIKE strings (`Query.php:367-370`): case-sensitive on PostgreSQL, `%`/`_` unescaped; the Grid fixed both | `whereContains/StartsWith/EndsWith()` escaping and using ILIKE on PostgreSQL; existing `where(..., 'LIKE')` unchanged |
| M6 | Decimals | `TypeDecimal` falls back to floats without bcmath silently (`TypeDecimal.php:67-79, 102-106`); no exact arithmetic helper, so line totals and VAT are computed in floats in app code | `Decimal::add/mul/sum/cmp()`, composer `suggest: ext-bcmath`, `orm.require_bcmath` |
| M7 | `json_validate()` in `TypeJson::validate()` with a fallback | from the fact-check | trivial |
| M8 | Runtime deprecation notices and a written policy | 22 `@deprecated` markers, no `trigger_error(E_USER_DEPRECATED)` anywhere | one helper, notices off by default via Settings |
| M9 | Floor-raising items (enums, readonly, typed constants, property hooks, `#[\Override]`, PHPUnit 10/11) | Gemini section 5; needs 8.1-8.4 | only after a decision to raise the floor; `#[\Override]` and `#[\Deprecated]` are harmless on older PHP but add little |

# 7. Packaging and releases

- Last tag v1.2.1 (2026-02-13); master is 4 commits past it while improvements1 carries the whole rework;
  the apps pin `dev-master` and `dev-improvements1`, so every push reaches them on the next update.
- Malformed tag `v.1.2.0`; no CHANGELOG or UPGRADE file; no `extra.branch-alias`, `scripts`, `suggest`,
  `support` in composer.json; `.gitattributes` has no `export-ignore`, so dist archives ship `agent/`
  (29 files), `.claude/` (including `settings.local.json`), `tests/` and the Docker files.
- `monolog/monolog` is a hard requirement that nothing in `src/` references; both apps use it only
  transitively, so it moves to `suggest` only after they require it themselves.
- Sketch: branch alias `dev-master: 1.x-dev`, scripts `test`/`stan`/`rector`, export-ignore list, a
  CHANGELOG seeded from the agent notes, tag 1.3.0 when improvements1 is merged.

# 8. What the apps copy between each other (candidate "admin kit")

About 40 classes exist in both apps under the same name; AuditLog, Backups*, GroupRole, GroupRoutingTable,
PersonGroup, SqlError, MonologHelper, the two exception classes and the ticket lookups are byte-identical
after namespace normalisation; App, Factory, RoutingTable, Person, Email and the backup helpers are forks.
Fifteen `.page` files are shared (`person_permissions`, `routing_table`, `reload_menu`, `processlist`,
`code_generator` identical; `account_settings`, `app_configuration`, `backups_*`, `change_password`,
`generator`, `person_device`, `sql_error` near-identical).

Themes, ranked by app code removed:

1. CRUD page scaffold (F5): about 15k routerarena and 3k asm page lines.
2. Authentication, session and user lifecycle (login, API key, login log, password history, time
   windows, device approval; asm adds passkeys): about 6-9k lines. Both copies carry dead code
   (`requestPasswordReset` calls a Twig factory that exists in neither app) and "login as" is a GET
   without CSRF or audit. Sketch: `Auth::attempt()/login()/logout()/user()/impersonate()`, a
   `UserProviderInterface` the app's Person implements, session hardening, `setActiveUserId` wired.
3. Router, routing table, per-module ACL and menu (F4): both `App::bootstrap()` copies, `RoutingTable`
   menu builder cached in the session, `person_permissions.page`; asm keeps hard-coded user-id lists in
   config (21 files) and 22 entry files bypass the router.
4. Report pages (F10).
5. Ops kit: backups to Google Drive, Email model with queue, error mail on uncaught exceptions,
   `processlist`/`sql_error` viewers, archive scripts: about 5.8k lines across both.
6. Formatting and i18n (U2, U3).
7. JSON API layer: routerarena `ApiRouter`/`ApiAuth`/`ApiResponse` (646 lines, single admin key, a
   hard-coded user id); asm `api.page`/`rest.page` nested switches. Sketch: `JsonResponse`,
   `ApiKernel->resource()`, `toArray()` with a field whitelist.
8. CLI commands with run logging, lock and CLI-only guard (asm's cron scripts sit in the web root; 2 of
   them check `php_sapi_name`).

Recommendation: keep `lavdiu/laf` the core (ORM, forms, grid, pages, generator) and grow the shared admin
shell as an optional package (`lavdiu/laf-admin`: auth, routing table, ACL, menu, SqlError, backups,
mailer, API kernel) extracted from the apps' identical classes, so both apps delete their copies instead of
maintaining forks.

Things LAF already provides that the apps bypass: routerarena's own `Document` (streams with
`file_get_contents`, stored MIME, unsanitised `Content-Disposition`; only `uploadFromUrl()` is missing in
LAF), the grid.js forks, web generator pages replaced by `laf-generate`, asm's own CSRF helpers while 67
existing CRUD pages were never regenerated (their deletes are unprotected GETs), `Db::setLogger()` unused
although both apps have a Monolog helper, `Locale` set but never used for display, settings arrays with
committed secrets instead of `loadFromFile()` plus environment overrides.

# 9. Recommended order

1. **Defects** (one commit each, S): D1 soft-delete value + `restore()`, D2 generator links via a
   `linkTo()` builder (related tabs without Delete), D3 logger instance per entry + shipped `SqlErrorLog`,
   D4 `whereGroup()`/`when()` + non-final `Query` with scopes, D5 row-count checks, the small items.
2. **Query and model features** (S-M): F2 aggregates/pagination/streaming, F1 scopes, F3 has-many
   generation, X2/X3/X4 generator and finder ergonomics, M5 LIKE helpers, F8 bulk writes.
3. **Forms and pages** (S-M): U1 errors in tabs, U4 flash, U3 formatting, U5/U6 generated page
   polish, F9 autocomplete, U8 accessibility, F6 validation rules.
4. **Application layer** (M): F4 dispatcher + factory + access policy, X1 samples, X6/X7/X8 helpers,
   X10 exceptions and error page, U2 translator.
5. **Assets and packaging** (S-M): M1 laf.js, M2 CSP, M3 theming, section 7 packaging, M8 deprecations.
6. **Larger, decide first**: F5 ResourcePage, F7 concurrency, F10 report filters, F11 events, F12
   connections, X11 many-to-many, the admin kit extraction, any PHP floor change (M9).

# 10. Not recommended

Schema flyweight (moderate measured cost, every generated Base class changes), joins on `Query` (PhpGrid
and `Db::getAllAssoc()` cover it), PSR-7/11/15 and worker runtimes (no such deployment), a UI/Database
split of `Field` and `BaseObject`, PSR-16 cache (the only cached thing is the menu), PHPUnit 10/11 (dev-only
churn), HTMX/partial rendering (no consumer), rendering-cost work (no measured problem).
