# Changelog

All notable changes to LAF are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions are git tags. How to move an application from
`master` to the unreleased work is described in [docs/20-upgrading.md](docs/20-upgrading.md).

## [Unreleased] (improvements1)

Everything on the `improvements1` branch since it left `master` at `v1.2.1` (commits from 2026-03-05 to 2026-10-14,
plus the work committed with this changelog: unique keys, opt-in linker tables, fluent many-to-many declarations). Commit hashes point at the main commit of each item; the
per-pass notes in `agent/` list every fix in detail. The PHP floor stays `>=8.0`; the code is tested on PHP 8.1-8.5
under `E_ALL`, on MariaDB and PostgreSQL.

### Added

- **Fluent many-to-many declarations**: `$this->belongsToMany('group')->through('person_group')->keys('person_id',
  'group_id')->label('Groups')->chips()->creatable()` (`Laf\Database\ManyToManyBuilder`; linker table and columns
  inferred when left out, resolved lazily, a second declaration refines the first; also `->hint()`, `->required()`,
  `->max()`, `->checkboxes()`, `->dualList()`, `->picker()`, `->group()`, `->tab()`, `->order()`, `->form()`);
  `BaseObject::manyToMany()` also takes the other table's name; `Util::pluralLabel()`.
- **Opt-in linker tables**: the generator writes many-to-many relations only for linker tables whose table comment
  says `{"linker": true}`; `generator.many_to_many` / `DatabaseGenerator::setManyToManyMode()` /
  `laf-generate --many-to-many=marked|auto|off`; unmarked matches are reported as generator notes
  (`getNotices()`, "Note:" lines); a marked table that is no linker gets a warning; inspectors read the table comment
  (`getTableComment()`).
- **Unique keys over several columns**: `Table::addUniqueKey($name, $columns)`, `getUniqueKeys()`,
  `hasUniqueKey()`, `removeUniqueKey()`; the generator writes `addUniqueKey()` for every multi-column unique index
  (both inspectors read unique indexes, partial/prefix/expression indexes left out);
  `BaseObject::duplicateUniqueKeys()` and `BaseObject::uniqueViolationFieldNames(DatabaseException)`; `validate()`,
  `store()` and forms check the combinations.
- **Many-to-many through linker tables** (`5517ccb`): `Generator\JunctionDetector`, `Table::addManyToMany()` emitted on
  both sides of every linker table, `Laf\Database\ManyToMany` (`ids()`, `objects()`, `labels()`, `options()`,
  `validIds()`, `sync()`, `diff()`), `BaseObject::manyToMany()`, pickers `ManyToManyCheckboxes`,
  `ManyToManyAutocomplete` (chips) and `ManyToManyDualList`, `RelationElementInterface`; `processForm()` stores the
  row and the links in one transaction; configuration through the linker column's comment (`"form":{"multi":false}`,
  `element`, `create`, label).
- **Autocomplete "create new"** (`5517ccb`): `Autocomplete::setAllowCreate()`, `setCreateText()`,
  `setCreateCallback()`, a CSRF-protected POST answered by `Form::handleLookupRequest()`, `getLookupStatus()`.
- **Validation on store and model rules** (`962cfda`): `orm.validate_on_store` / `setValidateOnStore()`,
  `validateRecord()` on the model, `Laf\Exception\ValidationException` (parent of `FormValidationException`),
  `Field::setPattern()`, `addValidator()`, `clearValidators()`, `setMinLength()` checked, decimal bounds
  (`setMinValue('0.01')`).
- **Foreign-key Autocomplete** (`962cfda`): `Input\Autocomplete`, `Form::handleLookupRequest()`, Settings
  `form.select_autocomplete_threshold`, vanilla `autocomplete.js`, demo `sample_files/autocomplete_demo.php`.
- **Query** (`9b6ab75`, `8dab6f9`, `cc2513d`, `3b83f6f`): `Laf\Database\Query` via `Model::query()` with `where()`,
  `orWhere()`, `whereIn/NotIn/Null/NotNull/Between()`, `whereRaw()`, `whereGroup()`, `orWhereGroup()`, `when()`,
  `whereContains()`, `whereStartsWith()`, `whereEndsWith()`, `orderBy()`, `limit()`, `offset()`, `select()`, terminals
  `get()`, `first()`, `arrays()`, `column()`, `count()`, `exists()`, `sum()`, `min()`, `max()`, `avg()`, `pairs()`,
  `paginate()` (`Laf\Database\Pagination`), `cursor()`, `chunk()`, `delete()`, `getSql()`, `getBindings()`.
- **BaseObject** (`9b6ab75`, `cc2513d`, `3b83f6f`): lifecycle hooks (`beforeInsert()` … `afterLoad()`),
  `BaseObject::transaction()`, `load()` / `loadOrFail()` with `RecordNotFoundException`, `isNew()`, `isDirty()`,
  `isLoaded()`, `getChangedFields()`, `getChangedValues()`, `setFieldValues()`, `toArray()`, `JsonSerializable`,
  `validate()`, `hydrate()` / `loadRow()`, composite-key loading (`new Model(['a' => 1, 'b' => 2])`), memoised
  foreign-key getters through `relatedObject()` (`getXObj(bool $fresh)`).
- **Db** (`949aa04`, `e12fba9`): named connections (`database.<name>.*`), typed `statement()` and bindings,
  `insert()`, `insertGetId()`, `update()`, `delete()`, `fetchAllKeyed()` / `getAllKeyed()`, `tableExists()`,
  `columnExists()`, `getTableNames()`, nest-aware `transaction()` with savepoints, reconnect on a lost connection
  (`database.reconnect_on_loss`), PSR-3 logger, query log, the optional `database.*` connection settings (charset,
  socket, persistent, connect_timeout, options, dsn, dsn_extra, timezone, schema, init_commands,
  empty_string_as_null).
- **Field types** (`cec9e06`): `TypeDecimal`, `TypeEnum`, `TypeUuid`, the typed `FieldType` contract
  (`normalize()`, `fromInput()`, `fromDb()`, `toDb()`, `equals()`, `validate()`) in `AbstractFieldType`;
  `Laf\Util\Locale`; `Laf\Database\Identifier` (engine-aware quoting); `ForeignKey` and `PrimaryKey` helpers
  (`getReferencedObject()`, `setOnDelete()`, `isComposite()`, `getWhereClause()` …).
- **Forms** (`d10b72e`, `332d97d`, `17406e4`, `14f8f70`, `3b83f6f`): CSRF token per session and form
  (`CsrfTokenException`, `getCsrfToken()`, `withCsrfToken()`, `isValidCsrfToken()`), server-side validation with
  `FormValidationException` and error display (`is-invalid`, error summary, `addError()`), the signed field list and
  `setRestrictToRenderedFields()` / `form.restrict_to_rendered_fields`, `setRequest()`, `buildRequest()`,
  `FormElementFactory`, `Container\Fieldset`, `Form::addButton()`, `setCancelLink()`, `setSubmitButtonLabel()`, input
  prefixes/suffixes, `setSuggestions()`, `Checkbox::setSwitch()`, `Input\Number`, `Input\Tel`, `Input\Range`, native
  date inputs (`form.native_date_inputs`), `File::setDocumentUrlBuilder()`.
- **FormLayout** (`14f8f70`): `Laf\UI\Form\FormLayout` (`fields()`, `fieldset()`, `tab()`, `exclude()`,
  `readOnly()`, `hidden()`, `element()`, `html()`, `auto()`, `toArray()` / `fromArray()`),
  `BaseObject::getForm($drawMode, $layout)`, `getFormLayout()`, `configureForm()`, `Field::setFormOptions()`,
  `setFormElementClass()`.
- **Generator** (`14f8f70`, `abdc912`, `bf1b714`): `vendor/bin/laf-generate` (`Laf\Generator\Cli`: `--bootstrap`,
  `--lib`, `--tables`, `--exclude`, `--classes`, `--pages`, `--install-pages`, `--force`, `--list`, `--labels`,
  `--strict`, `--quiet`), JSON column comments (`Laf\Generator\ColumnComment`, `fields` and `form` keys),
  `TableInspectorFactory`, `AbstractTableInspector`, `PostgresTableInspector` parity, `DatabaseGenerator::setTables()`,
  `setExcludedTables()`, `setLabelTranslations()`, `setOutput()`, `setInstallMissingPages()`, `plan()`,
  `getGeneratedFiles()`, `getWarnings()`; generated pages: per-foreign-key related tabs, CSRF-protected delete,
  `page_factory` / `page_factory_method` / `module_for_table` config.
- **UI** (`91087a6`, `aef481a`, `26c53fa`, `38b0df0`, `3b83f6f`): `Html::addScript()`, `addInlineScript()`,
  `addStylesheet()`, `addMeta()`, `addLinkTag()`, `setFavicon()`, `setLanguage()`, body attributes and footer;
  `GenericPage::setSubtitle()`, `setHeader()`, `setFooter()`, card classes; `HeaderComponent` factories; container
  attributes and `setTag()`; `Modal::setCentered()`, `setStaticBackdrop()`, `addHiddenInput()`; `TabContainer::setActiveTab()`;
  `Link::setDisabled()`; `Dropdown::addDivider()`, `addHeader()`, `addHtml()`; `Image::setLazy()`;
  `Alert::setDismissible()`, `setIcon()`; `Laf\UI\Table\Table` as a component (`fromArray()`, `addRow()`,
  `Cell::setText()`); `Laf\UI\Flash`.
- **Grid** (`0a84963`): `GridRequest`, `GridQuery`, `setRowsPerPageOptions()`, `setMaxRowsPerPage()`,
  `setInlineScript()`, `setCsvBom()`, searchable/sortable columns, operators `in`, `between`, `isnull`, `notnull`,
  `notlike`, `toArray()` / `toJson()`, `getHttpStatus()`, `bootstrap($terminate)`; `grid.js` rewritten for Bootstrap 5
  (`Grid.autoInitAll()`), demo `sample_files/grid_demo.php`.
- **Documents** (`fe6a2f2`, `8b40734`): `Document::storeFile()`, `uploadMany()`, `setModelClass()`,
  `setNotFoundUrl()`, `upload.max_image_size`, `upload.thumbnail_size`, `upload.cache_max_age`,
  `upload.image_mime_types`, `upload.not_found_url`.
- **Audit log** (`b7fafd5`): `AuditLog` as a real model, readers (`findForRecord()`, `findForObject()`,
  `findForUser()`, `findBy()`, `getChanges()`), `enable()` / `disable()`, `setStrict()`, `getLastError()`,
  `audit.max_value_length`, `migrations/create_audit_logs_table_postgres.sql`.
- **Settings and utilities** (`475a9c4`, `17406e4`, `cc2513d`, `3b83f6f`): `Settings::has()`, `getBool()`,
  `getString()`, `getInt()`, `load()`, `remove()`, working `loadFromFile()` / `saveToFile()`, `loadFromEnvironment()`,
  `loadEnvFile()`; `Locale::formatNumber()`, `formatDate()`, `formatDateTime()`, `formatTime()`; `UrlParser::getRawId()`,
  `getLink()`, `reset()`, UUID ids, redirect `$terminate` flag; `Util::e()` / `Util::attr()`.
- **Tooling** (`a6544c3`, `e0383b3`, `cc2513d`): PHPUnit Unit and Integration suites with `tests/bootstrap.php` and
  the `LafShell` fixtures, CI on PHP 8.1-8.5 with MariaDB and PostgreSQL, PHPStan level 5 (`phpstan.neon`), advisory
  Rector (`rector.php`), Dockerfile and docker-compose, composer `scripts`, `branch-alias`, `suggest`, `export-ignore`.
- **Documentation** (`cec40b8`): `docs/` with 18 chapters on a four-table example and the real generator output.

### Changed

- Linker tables no longer become pickers by themselves (`5517ccb` detected them automatically): mark them with the
  table comment `{"linker": true}`, or use `--many-to-many=auto` for the previous behaviour. Generated Base classes
  declare the relation as `$this->belongsToMany(...)->through(...)->keys(...)->label(...)` instead of
  `$this->getTable()->addManyToMany(...)` (the array form still works).

- **Values are stored verbatim and escaped on output** (`cec9e06`): text types no longer apply `strip_tags()` +
  `htmlspecialchars()` when storing; every LAF output sink escapes (`Util::e()`, `Util::attr()`);
  `Field::setAllowHtml(true)` remains the raw opt-in.
- `TabItem` titles, `Dropdown` texts, `Html::setPageTitle()`, form labels, hints and option labels are escaped
  (`14f8f70`, `26c53fa`, `91087a6`, `d10b72e`); page titles/subtitles, `Link` captions, `Alert` texts and the `Modal`
  title stay raw HTML.
- Forms (`d10b72e`, `332d97d`): `processForm()` validates and throws `FormValidationException`
  (`setValidateOnSubmit(false)` opts out); write refusals (missing required, duplicate unique) become field errors; a
  form reads only the request of its own method; booleans drawn disabled, read-only or not at all are left alone; field
  values are mirrored into their elements; Bootstrap 5 markup (`form-check`, `mb-3`); `required` never on checkboxes;
  hidden-input ids `{form}_form_submit` / `{form}_marker`.
- Forms: taken unique values and combinations are reported with the other validation errors ("E-mail:
  this value is already in use", "A + B: this combination is already in use"); a database unique violation becomes a
  field message instead of an uncaught `DatabaseException`.
- A foreign key always holds one value (`5517ccb`): `Select::setMultiple()` is ignored and `CheckboxCollection` draws
  radios on a foreign key.
- BaseObject (`9b6ab75`, `b7fafd5`): a no-op `store()` writes nothing; unset optional columns are left out of the
  INSERT; `INSERT … RETURNING` on PostgreSQL; composite keys in every `WHERE`; statement failures are
  `DatabaseException`; `canSoftDelete()` covers `record_status_id`; a soft delete is one DELETE audit entry; getters
  return typed values; `select()` on a missing id unloads the object; clones are deep copies.
- `hardDelete()` returns false when no row was deleted (`8dab6f9`).
- Db (`949aa04`, `e12fba9`): `database.port` / `engine` optional; a failed `getInstance()` is not cached;
  `DbConnectException` extends `\PDOException`; `getRowObject()` returns null when nothing matches; nested
  `transaction()` uses savepoints; SQL error-log entries raised in a transaction are written after it ends; a missing
  error-log table no longer replaces the original error; a fresh error-logger object per entry (`8dab6f9`).
- Field types (`cec9e06`): `false` stored as 0; integers reject fractions; decimals map to `TypeDecimal`, `timestamp`
  to `TypeDateTime`, `tinyint(1)` / `bit(1)` to `TypeBool`; locale-aware parsing; default time format `g:i A`.
- Generator (`14f8f70`): bound metadata queries, PostgreSQL comments / identity / unique parity, `setMaxLength()` only
  on character columns, string foreign keys loaded by `getXObj()`, `setDisplayField` honoured, `var_export()` for
  every generated literal, quoted identifiers in generated SQL, views no longer listed, `generateEverything()` throws on
  a bad configuration.
- Generated pages (`14f8f70`, `8dab6f9`, `3b83f6f`): forms from `getForm($drawMode)`, try/catch around
  `processForm()`, CSRF-checked delete, one related tab per foreign key column linking to the child table's page,
  flash messages through `Laf\UI\Flash`.
- Grid (`0a84963`): `execute()` returns false on an SQL error, LIKE is case-insensitive on PostgreSQL (`ILIKE`),
  refused/failed exports answer 403/500, XLSX export through OpenSpout; the JSON payload keeps the shape older
  `grid.js` copies read.
- Documents (`fe6a2f2`): MIME type from the file, only raster images resized, `upload()` throws `RuntimeException` on
  storage failures, streamed responses with `ETag` / 304, RFC 6266 `Content-Disposition`, new stored-name format.
- Audit log (`b7fafd5`): INSERT entries skip null columns, `created_on` from the PHP clock, `user_id` falls back to
  `<package>\Person::getLoggedUserId()`, failures reported through `getLastError()`.
- UrlParser (`475a9c4`): `getId()` returns `int|string|null` (UUIDs), link parts URL-encoded, a UUID passed to the
  link builders is used; `settings.use_pretty_url` read as a boolean (`"false"` is false, `8dab6f9`).
- UI (`91087a6`, `aef481a`, `26c53fa`): `Html::addComponent()` and `GenericPage::addLink()` require a
  `ComponentInterface`; containers hand their draw mode only to children without one (`Row`/`Column` too); the
  explicitly active tab wins; typed fluent setters return `static`.
- Typed properties and return types across `src/` (`0dfa985`, `4ed759d`, `38b0df0`, `475a9c4`, `fe6a2f2`), clean under
  `E_ALL` on PHP 8.5; `Field::getPrimaryKey()` is nullable; `Cell::setData(?string)`.
- Dependencies (`e0383b3`, `eac3820`): `psr/log` required directly; locks on `claviska/simpleimage` 4.4.0,
  `monolog/monolog` 2.x, current PHPUnit 9.6 / PHPStan / Rector.

### Deprecated

- `Laf\Generator\TableInspector`: use `MysqlTableInspector` or `TableInspectorFactory::create()` (`abdc912`).
- The legacy `FieldType` methods `isValid()`, `getValueDbSanitized()`, `formatForDb()`, `getPdoType()`: use
  `normalize()`, `fromInput()`, `toDb()` (`cec9e06`).
- `Form::addTab()` / `getTabs()` and `Container\TabContent` (tabs added there are never drawn): use `TabContainer` +
  `TabItem` or `FormLayout::tab()`.
- `PhpGrid::exportToExcelPhpExcel()` / `exportToExcelSpout()` (use `exportToXlsx()`), `setUseIlikeOnPg()` /
  `getUseIlikeOnPg()` (no effect), `ActionButton::setJsCallbackk()` (use `setJsCallback()`).
- `Modal::SISZE_NORMAL` (use `SIZE_NORMAL`), `InputType::Datetime` (use `DatetimeLocal`),
  `Select::NON_SELECT_ATTRIBUTES`; `SimpleTable` stays deprecated in favour of `PhpGrid`.

### Removed

- `Laf\Database\QueryBuilder` and its eager-loading stub `BaseObject::loadRelation()` (`f62d6aa`, `9b6ab75`);
  `BaseObject::getQueryBuilder()` now returns a `Query`.
- `phpoffice/phpspreadsheet` from the requirements (`e0383b3`): unused by LAF; applications that use it require it
  themselves.
- The protected accumulators `GenericPage::$bodyHtml` / `$footerHtml` (`91087a6`).
- Obsolete `sample_files/` scripts (`config.php`, `database.php`, `constants.php`, `functions.php`, `test.php`),
  replaced by `bootstrap.php`, `settings.php`, `class_generator.php` and `inspect_table.php` (`bf1b714`).

### Fixed

Only the larger items; each pass's notes in `agent/` list every defect with its fix.

- Generator: `JunctionDetector` no longer counts a metadata column that is a foreign key
  (`record_status_id`, `created_by`) as a linker key, so a child table with a status foreign key is no longer taken
  for a linker, and a linker with a `created_by` foreign key is still recognised.
- BaseObject (`9b6ab75`): infinite recursion in `returnLeafClass()` and `getAllFieldsAsFormElements()`, `reset()`
  leaving an unusable object, a missed `select()` keeping the previous row (a later `store()` updated the wrong row),
  updates and deletes touching several rows on composite keys, the PostgreSQL insert id, fatal errors without a
  `Person` class, `softDelete()` inserting a new row for a missing one.
- Db (`949aa04`, `e12fba9`): a dead instance cached after a failed connection, `mariadb` / `pgsql` engine names
  ignored, execution errors never reaching the SQL error logger, the error log lost inside transactions or overwritten
  within a request, a dead cached connection after a server drop, `getOne()` swallowing a PostgreSQL `false`.
- Field types (`cec9e06`): broken `TypeChar` / `TypeJson` / `TypeBlob` imports, boolean `false` stored as NULL,
  decimals rounded through floats, date parsing defaulting missing parts to "now", PostgreSQL `bytea` and enum
  columns.
- Forms (`d10b72e`, `332d97d`, `17406e4`): the missing `FormElementFactory` (fatal), the stored document wiped on
  every update, unrendered booleans reset to false, a new CSRF token per draw breaking second tabs, stale values after a
  failed submission, generated pages crashing on validation errors, required checkboxes that could not be saved
  unticked, multi selects that could not be cleared, duplicate hidden-input ids, `Radio` markup.
- Generator (`14f8f70`, `bf1b714`): generated `autoload.php` that did not parse, PostgreSQL composite foreign keys as a
  cross product, composite unique columns marked unique each, labels with an apostrophe breaking generated pages,
  related tabs filtered on the wrong foreign key, `Util::tableFieldNameToLabel()` stripping inner `_id`.
- UI: `GenericPage::enable()` / `disable()` TypeError and pages drawn twice repeating their body, `Page` fataling,
  `HtmlContainer::addComponent()` throwing a non-existent class, the broken `Modal` title id, `Carousel` returning
  nothing, `Link` confirmation messages broken by an apostrophe, `Dropdown::leftAlign()` aligning right, getters
  throwing for unset attributes (`91087a6`, `aef481a`, `26c53fa`).
- Audit log (`b7fafd5`): `new AuditLog($id)` and the example fatal, duplicate UPDATE entries on a second `store()`,
  DELETE entries for deletes that failed, empty `changes` for invalid UTF-8.
- `Util::formatDateForDb()` crashing on invalid input, `Settings::loadFromFile()` / `saveToFile()` stubs, UUID ids
  ignored by the link builders (`475a9c4`).
- Grid (`0a84963`): the PostgreSQL branch never taken, `LOWER()` on integer columns, unquoted identifiers (reserved
  words), the Excel export broken without PhpSpreadsheet.

### Security

- SQL injection: `ForeignKey::isValidValue()` and the SQL error-log table name now use bound parameters / sanitised
  identifiers (`f62d6aa`); generator metadata queries are bound and generated SQL quotes identifiers (`14f8f70`);
  `Query` validates column names and binds every value.
- CSRF: forms carry and check a per-session token (`d10b72e`); a POST form no longer accepts a GET submission, which
  bypassed the check (`332d97d`); generated delete links carry the token and the delete action checks it (`14f8f70`);
  Autocomplete "create new" posts with the token (`5517ccb`); `Modal::addHiddenInput()` for modal forms (`aef481a`).
- Mass assignment: the signed field list (HMAC with a per-session key) and the opt-in
  `form.restrict_to_rendered_fields` guard (`332d97d`).
- XSS: output escaping at every sink, including attributes and the form `action` built from the request URI, which
  was a reflected XSS (`cec9e06`, `d10b72e`); `grid.js` never puts row data into `on*` attributes (`feb14d9`).
- Uploads: the MIME type is read from the file instead of trusted from the browser (`fe6a2f2`); only raster images,
  PDF and plain text are served inline, everything else as an attachment under `Content-Security-Policy: sandbox` with
  `nosniff`, so an uploaded SVG or HTML file cannot run as a page of the site (`52d9b15`).
- `phpoffice/phpspreadsheet ^1.10` (open advisories) dropped from the requirements; `composer audit` clean
  (`e0383b3`).

## [1.2.1] - 2026-02-13

The last tag, and the point where `improvements1` branched off `master`. `master` has four commits after it
(README updates and the Monolog constraint, `8f369d6`). Earlier releases: see the git tags (`v1.0` … `v1.2.1`).

[Unreleased]: https://github.com/lavdiu/laf/compare/v1.2.1...improvements1
[1.2.1]: https://github.com/lavdiu/laf/releases/tag/v1.2.1
