Reference map of LAF (master @ e884a24): directory tree, class inheritance trees, and how the pieces connect at runtime and at code-generation time.

# LAF architecture map

Read this top to bottom once; afterwards jump by section. Line counts are from master on 2026-10-11. "App" below means the generated project (namespace = `Settings 'project.package_name'`, e.g. `Asm`).

---

## 1. Directory tree (annotated)

```
laf/
├── composer.json            lavdiu/laf, PSR-4 "Laf\\" => src/Laf
├── phpunit.xml              bootstrap ./app/config/config.php (needs a DB)   [improvements1: tests/bootstrap.php]
├── CLAUDE.md                architecture notes (refs to 1-example/ are stale)
├── src/Laf/                 the framework (102 files, 17,633 lines)
│   ├── Database/            ORM: Active Record + metadata + PDO wrapper
│   │   ├── BaseObject.php      1285  every generated model extends this
│   │   ├── Db.php               694  PDO singleton, fetch helpers, SQL error logging
│   │   ├── Table.php            331  metadata: fields, PK, FKs, unique, display field
│   │   ├── PrimaryKey.php        87  list of PK fields
│   │   ├── ForeignKey.php       157  one FK column -> referenced table/column, isValidValue()
│   │   ├── AuditLog.php         161  static logInsert/logUpdate/logDelete -> audit_log table
│   │   ├── QueryBuilder.php     345  unfinished, MySQL-only (deleted on improvements1)
│   │   ├── SqlErrorLoggerInterface.php   app-supplied sink for failed SQL
│   │   └── Field/
│   │       ├── Field.php        882  one column: value, oldValue, type, validation attrs, form element cache
│   │       ├── FieldType.php     40  interface + DB type-name constants
│   │       ├── FieldTypeFactory.php 108  DB type string -> Type* instance / class literal (for generator)
│   │       └── Type{Blob,Bool,Char,Date,DateTime,Float,Integer,Json,Text,Time,Varchar}.php
│   ├── Exception/            6 empty subclasses of \Exception
│   ├── Filesystem/
│   │   └── Document.php         219  upload/serve files; wraps App\Document (a generated model)
│   ├── Generator/            schema -> code
│   │   ├── DatabaseGenerator.php 230  orchestrator: dirs, autoload.php, all tables, all pages
│   │   ├── ClassGenerator.php    566  writes Base/Base<Table>.php and <Table>.php
│   │   ├── PageGenerator.php     510  writes <table>.page (CRUD switch + grid)
│   │   ├── TableInspectorInterface.php
│   │   ├── TableInspector.php    230  MySQL information_schema reader
│   │   └── PostgresTableInspector.php 264
│   ├── UI/                   HTML component tree (Bootstrap 5 + Font Awesome markup)
│   │   ├── ComponentInterface.php 261  CSS/style/attribute/children/drawMode contract
│   │   ├── Traits/
│   │   │   ├── ComponentTrait.php    578  default impl of ComponentInterface
│   │   │   └── FormElementTrait.php  495  input attributes (name,id,value,required,min,max,...)
│   │   ├── Form/
│   │   │   ├── Form.php              763  builds <form> from a BaseObject, processForm()
│   │   │   ├── FormElementInterface.php   setField/getField/hint
│   │   │   ├── DrawMode.php          VIEW | INSERT | UPDATE
│   │   │   ├── FormRowDisplayMode.php ROW | INLINE
│   │   │   ├── InputType.php         HTML input type constants
│   │   │   ├── Control/Button.php, SubmitButton.php, ButtonType.php
│   │   │   └── Input/Text.php (base, 276) + Checkbox, CheckboxCollection, Color, Date,
│   │   │       DateTime, Email, File, Hidden, Integer, Password, Radio, Search,
│   │   │       Select (FK dropdown, 133), TextArea, Time, Url
│   │   ├── Container/
│   │   │   ├── GenericContainer.php  59  children + draw loop
│   │   │   ├── Div.php               57  <div class=...> wrapper (most used)
│   │   │   ├── Row.php, Column.php   Bootstrap row / col
│   │   │   ├── Card.php, CardHeader.php, CardBody.php, CardFooter.php
│   │   │   ├── HtmlContainer.php     61  raw HTML string as a component
│   │   │   ├── TabContainer.php 133, TabItem.php 162, TabContent.php 121
│   │   │   ├── Modal.php            365  Bootstrap modal, optional inner <form>
│   │   │   └── ContainerType.php     TYPE_DEFAULT | TYPE_FLUID
│   │   ├── Component/
│   │   │   ├── Link.php 256, Dropdown.php 211, Alert.php 136, Image.php 221, Carousel.php 287
│   │   │   └── Rel.php, Target.php   constants
│   │   ├── Grid/
│   │   │   ├── PhpGrid/PhpGrid.php  1527  server side of the AJAX grid (SQL wrap, JSON, export)
│   │   │   ├── PhpGrid/Column.php    372  public props -> serialised to JSON for grid.js
│   │   │   ├── PhpGrid/ActionButton.php 144
│   │   │   ├── PhpGrid/Settings.php   38
│   │   │   └── SimpleTable.php       757  DEPRECATED server-rendered table
│   │   ├── Page/
│   │   │   ├── Html.php             168  <!DOCTYPE> .. <head> .. <body> shell, menu slot
│   │   │   ├── HeaderComponent.php  111  one <link>/<script>/<meta> tag in <head>
│   │   │   ├── GenericPage.php      205  Bootstrap card: title + icon + right-side links + body + footer
│   │   │   ├── AdminPage.php         19  = GenericPage (draw guard only)
│   │   │   └── Page.php              42  public-site banner variant (calls app fn getBannerImage())
│   │   └── Table/                plain HTML table builder (Table, Tr, Cell, Td, Th)
│   └── Util/
│       ├── Settings.php         112  singleton key/value config (16 keys, see §9)
│       ├── UrlParser.php        326  /module/submodule/action/id  or ?module=&submodule=&action=&id=
│       └── Util.php             140  name conversions, uuid, toFloat, timeAgo
├── sample_files/             copy-paste starting points for an app
│   ├── config.php, database.php, constants.php, functions.php   bootstrap chain
│   ├── class_generator.php      runs DatabaseGenerator::generateEverything()
│   ├── grid.js                 1350  browser side of PhpGrid (vanilla ES6 class Grid)
│   ├── grid-callback-examples.md
│   └── populate_table_object_list.php   [improvements1]
├── migrations/create_audit_logs_table.sql
├── examples/audit_logging_example.php
├── tests/BaseObjectTest.php     199  (improvements1 adds 16 more test files + fixtures)
└── sample_project/asm/          untracked copy of the production ASM ERP (reference only)
```

Generated app layout (what `DatabaseGenerator` writes, given `library_path` = `app/lib`):

```
app/lib/<Package>/
├── autoload.php                   spl_autoload for the package
├── Base/Base<Table>.php           REGENERATED each run: fields, PK, FKs, get/set per column
├── <Table>.php                    written ONCE: your logic, returnLeafClass(), find()/findOne()
app/lib/generator_cache/pages/<table>.page   REGENERATED: CRUD page draft
app/lib/pages/<table>.page                   live pages (copy from cache, then edit)
```

---

## 2. Class trees

### 2.1 Database layer

```
BaseObject                                  (Database/BaseObject.php)
├── AuditLog                                static loggers; instance side is unused
└── App\Base\Base<Table>   (generated)      buildClass(): new Table + Field per column + PrimaryKey + ForeignKey
    └── App\<Table>        (generated once) returnLeafClass(), find(), findOne(), your methods

Table ──has many──> Field ──has one──> FieldType (TypeVarchar | TypeInteger | ...)
  │                   │
  │                   └──caches──> FormElementInterface (Text | Select | Date | ...)
  ├──has one──> PrimaryKey ──> Field[]
  ├──has many─> ForeignKey ──> Field + referencingTable/referencingField (strings)
  └──> displayField (Field|null; fallback: name > label > description > 2nd column)

Db (singleton)  ──wraps──> \PDO      ──optional──> SqlErrorLoggerInterface (app class), Psr\Log\LoggerInterface
Settings (singleton)  read by: Db, BaseObject, Field, Select, Document, UrlParser, Type{Date,DateTime,Time}, generators
```

FieldType mapping (`FieldTypeFactory`, DB type string → PHP type → default input):

| DB type | FieldType class | Input class |
|---|---|---|
| varchar, character varying | TypeVarchar | Text |
| char | TypeChar | Text |
| text | TypeText (also the default) | TextArea |
| int, bigint, smallint, tinyint, integer | TypeInteger | Integer |
| decimal, numeric, float, double, real | TypeFloat | Text |
| bool | TypeBool | Checkbox |
| date | TypeDate | Date |
| datetime, timestamp* | TypeDateTime | DateTime |
| time* | TypeTime | Time |
| json | TypeJson | TextArea |
| blob | TypeBlob | Text |
| any FK column (regardless of type) | (as above) but `Field::getFormElement()` → **Select** via `Table::isForeignKey()` |
| FK to table `document` | **File** (`Field::isDocumentField()`) |

### 2.2 UI layer

```
ComponentInterface  (44 methods: css, style, attributes, wrapper*, children, drawMode, draw)
└── implemented via `use ComponentTrait` by:
    ├── GenericContainer ─┬── Row, Column, HtmlContainer, TabContainer, TabItem
    │                     └── (Div is a sibling, not a child: Div implements the interface directly)
    ├── Div ──> Card, CardHeader, CardBody, CardFooter
    ├── Modal
    ├── Form
    ├── GenericPage ──> AdminPage, Page
    ├── SimpleTable (deprecated)
    ├── Alert, Carousel
    └── FormElementInterface + ComponentInterface (both traits):
        ├── Text ──> Checkbox, CheckboxCollection, Color, Date, DateTime, Email, File, Hidden,
        │            Integer, Password, Radio, Search, Select, TextArea, Time, Url
        ├── Button ──> SubmitButton
        ├── Link, Dropdown, Image        (form-element trait only for name/id/value plumbing)

Not components (plain classes): Html, HeaderComponent, TabContent, PhpGrid, Column, ActionButton,
                                UI\Table\{Table,Tr,Cell,Td,Th}
```

Draw contract: every component has `draw(): string`. Containers concatenate `draw()` of their children. `Form::draw()` first pushes its `DrawMode` and `FormRowDisplayMode` into every child, so one field list renders as read-only text (VIEW), inputs with values (UPDATE), or empty inputs (INSERT).

### 2.3 Generator layer

```
DatabaseGenerator ──for each table──> ClassGenerator ──uses──> TableInspector | PostgresTableInspector
                  └─for each table──> PageGenerator  ──uses──> same inspectors (+ inspects each FK target for display column)
TableInspectorInterface: inspect(), getColumns()  (columns carry COLUMN_NAME, DATA_TYPE, IS_NULLABLE,
                         CHARACTER_MAXIMUM_LENGTH, COLUMN_KEY, EXTRA, COLUMN_COMMENT, FOREIGN_KEY[...])
```

---

## 3. Boot sequence of an app (what `config.php` must do, in order)

```
1. vendor/autoload.php                       Laf\ + composer deps
2. app/lib/<Package>/autoload.php            generated models        (DatabaseGenerator::generateAutoLoader)
3. Settings::set('database.*', ...)          Db::getInstance() reads these lazily on first use
   Settings::set('project.package_name','X') BaseObject/Field/Select/Document build "\X\ClassName" from this
   Settings::set('settings.use_pretty_url', bool)
   Settings::set('404', '/url'), 'upload.*', 'locale.*.format', 'settings.label.translations'
4. session_start()
5. BaseObject::setActiveUserId($_SESSION['uid'] ?? null)   -> audit_log.user_id
   (created_by / updated_by STILL come from \X\Person::getLoggedUserId(), see §5)
6. Db::getInstance()->setSqlErrorLogger(new AppSqlError())  optional
7. dispatch: read UrlParser::getModule()/getSubmodule()/getAction()/getId() and include the .page file
   (LAF does not ship a front controller; ASM's App::bootstrap() does step 7 + login + permissions)
```

---

## 4. Request lifecycle through a generated page

```
GET /sales/invoice/view/42        (or ?module=sales&submodule=invoice&action=view&id=42)
 │
 ├─ UrlParser (singleton, built from $_GET['uriRewrite'] or $_GET[module|submodule|action|id])
 │    module=sales submodule=invoice action=view id=42   (_getId() → null unless numeric)
 │
 ├─ app front controller includes pages/invoice.page
 │
 └─ invoice.page  (PageGenerator template)
      $invoice = new Invoice(UrlParser::getId());        ← Base ctor: buildClass(); if id numeric → select($id)
      $form    = $invoice->getForm();                    ← Form(obj) + components = every Field->getFormElement()
      $html    = Factory::GeneralPage();                 ← app-made Html shell with <head> assets + menu
      $page    = new AdminPage();                        ← card with title/icon/links
      if ($form->isSubmitted()) { $id = $form->processForm(); UrlParser::redirectToViewPage($id); }
      switch (UrlParser::getAction()) {
        'new'    → $form->setDrawMode(INSERT); $page->addComponent($form)
        'update' → $form->setDrawMode(UPDATE); ...
        'view'   → $form->setDrawMode(VIEW); + Dropdown(update, delete) + TabContainer of child-table grids
        'delete' → softDelete() if canSoftDelete() else hardDelete(); redirectToListPage()
        'list'   → PhpGrid over SELECT ... LEFT JOIN fk tables; if isReadyToHandleRequests() → bootstrap() (JSON) 
      }
      echo $html->draw();     Html → menu + each component → GenericPage card → Form → inputs
```

Form submit round trip (`Form::processForm`, Form.php:158):

```
browser POST  form_submit=<table>  <table>=rand  <table>_draw_mode=UPDATE  <rot13(field)>=value ...
 │   (field names are rot13-scrambled: Field::getNameRot13(); table hidden input name is plain)
 ├─ isSubmitted(): $_POST['form_submit'] == table name
 ├─ for each Field: value = $_POST[rot13] → trim → FieldType::formatForDb() → Field::setValue()
 │     setValue: FieldType::isValid() else InvalidValueException; sanitize via getValueDbSanitized(); marks updatedOnTs
 │     document fields: Document::upload(rot13name) → inserts App\Document row, returns its id
 │     bool fields: unchecked checkbox → formatForDb(null/'') → false
 ├─ PK empty → $object->insert()  else → $object->update()
 ├─ reload()  (re-select, clears cached form elements)
 └─ return record id
```

---

## 5. Persistence path inside BaseObject

```
new App\Invoice(42)
  Base ctor → buildClass() → setTable(new Table('invoice')) + Field per column (+label/placeholder/required/maxLength/type)
            → PrimaryKey, ForeignKey objects
            → select(42): "SELECT * FROM invoice WHERE id = :recordId" → Field::loadValueFromDb() (sets value AND oldValue)
            → recordSelected = true

$invoice->setCustomerIdVal(7)                                   (generated setter → setFieldValue)
  setFieldValue(): if Field isForeignKey → ForeignKey::isValidValue(7) runs "SELECT 1 FROM customer WHERE id=7"
                   then Field::setValue(7)  (validates, sanitizes, tracks change)

$invoice->store()
  PK value empty → insert()            else → update()
  insert(): checkFieldsForMissingRequiredValues() (throws MissingFieldValueException)
            checkUniqueFieldsForDuplicateValues()  (TODO, no-op)
            created_on = now; created_by = \App\Person::getLoggedUserId()   ← hard app dependency
            INSERT all non-autoincrement fields (string PK auto-filled with Util::uuid())
            setRecordId(lastInsertId); logAuditEntry(INSERT) → AuditLog::logInsert → INSERT audit_log
  update(): requires recordSelected; updated_on/by; UPDATE only fields where Field::hasChanged()
            (oldValue != value); PK bound as PARAM_INT; logAuditEntry(UPDATE) with old/new per changed field
  hardDelete(): logAuditEntry(DELETE) first, then DELETE
  softDelete(): record_status_id=0  or deleted=1, then store()

Finders (static, on the leaf class):
  Invoice::findOne(['number'=>'A-1'])  → bOfindOne → SELECT id WHERE ... → new static(id)        (2 queries)
  Invoice::find(['customer_id'=>7])    → bOfind    → SELECT id WHERE ... → new static(id) per row (1+N queries)
  Invoice::findAsArray([...])          → SELECT * → plain arrays (1 query)
  $invoice->listAllArray()/listAllObjects()  (generated in Base)
  Invoice::getQueryBuilder()           → QueryBuilder (unfinished)

Logging: BaseObject::setLogger(PSR-3) → addLoggerDebug/Info/Error... called on every operation.
```

Audit trail wiring: `BaseObject::$activeUserId` (static) → `AuditLog::logChange(action, table, id, changesJson, userId)` → table `audit_log(user_id, table_name, record_id, action, changes, created_on)` from `migrations/create_audit_logs_table.sql`. Per-object switch: `auditLogDisable()`.

---

## 6. Foreign keys end to end

```
schema: invoice.customer_id → customer.id
ClassGenerator emits in BaseInvoice::buildClass():
    $this->getTable()->addForeignKey((new ForeignKey())->setField(customer_id field)
        ->setKeyName('fk_...')->setReferencingTable('customer')->setReferencingField('id'));
and a navigator:
    getCustomerIdObj(): new \App\Customer($this->getCustomerIdVal())

Field::getFormElement()  → because isForeignKey → Select
Select::getOptions()     → new \App\Customer() to learn PK + display field, then
                           "SELECT id, <display> FROM customer WHERE 1=1 [AND dbSelectionCriteria] ORDER BY <display>"
                           (ALL rows; criteria values interpolated)
Select VIEW mode         → Field::getReferencedValue() → new \App\Customer(id) → displayField value
Display field choice     → Table::getDisplayField(): explicit (column comment {"fields":{"setDisplayField":1}})
                           else name > label > description > second column
PageGenerator list grid  → LEFT JOIN customer ON invoice.customer_id = customer.id, column "customer_<display>"
PageGenerator view page  → for each table referencing invoice.id → a TabItem with a PhpGrid filtered by that FK
```

Per-field config via MySQL column comment (JSON): `{"fields":{"setLabel":"Customer","setHint":"...","setDisplayField":1}}` → emitted as `$field->setLabel(...)` in the Base class.

---

## 7. PhpGrid: PHP ↔ grid.js contract

```
PHP side (page):                                   Browser side (sample_files/grid.js):
$g = new PhpGrid('invoice_list');                  window.grid['invoice_list'] = new Grid('invoice_list')
$g->setSqlQuery("SELECT ... ")                     Grid.initialize() → fetch(buildUrl())
  ->addColumn(new Column('invoice_id','#'))         buildUrl = current URL
  ->addColumn(new Column('customer_name','Customer'))   + &load_grid_by_name=invoice_list
  ->addActionButton(new ActionButton('View','?..id={invoice_id}','fa fa-eye'))
  ->setRowsPerPage(20);                                 + &page=N &limit=N [&sort=col &dir=asc|desc]
if ($g->isReadyToHandleRequests())  ← $_GET['load_grid_by_name'] == name   + [&searchParams=json [{property,operator,value}]]
    $g->bootstrap();                 → execute() → outputJsonWithHeaders() → exit
echo $g->draw();                     ← empty <table> skeleton + <script> that constructs Grid

execute(): wraps user SQL:  SELECT cols FROM ( <userSql> ) <gridName> WHERE 1=1 AND col LIKE :ph ... ORDER BY .. LIMIT .. OFFSET ..
           + COUNT(*) query + optional SUM() totals query. Sort column whitelisted against addColumn() list; filter values bound.
JSON: {success, columns[], actionButtons[], rows[], rowCount, pageCount, rowsPerPage, columnTotals, rowLevelJsCallback, ...}
Export: &export_grid_to_excel=1|2 (PhpSpreadsheet | OpenSpout), &export_grid_to_csv=1
Column public props (serialised as-is): index, fieldName, label, format, href, target, inner/outer css+attrs,
    visible, exportable, caseInsensitive, caseInsensitiveSort, searchOperator, wildcardMode, callback(improvements1)
Href templates: "{column_name}" substituted per row by grid.js formatLinkHref().
```

Page assets needed in the Html shell: Bootstrap 5 CSS/JS, Font Awesome, `grid.js`. The framework does not add them; `Factory::GeneralPage()` in the app does (via `Html::addHeaderComponent(HeaderComponent)`).

---

## 8. Code generation flow

```
php sample_files/class_generator.php   (or ASM: ?module=generator)
 └─ new DatabaseGenerator($libraryPath, $forceRewriteClasses)
      config = {namespace, base_class_dir, class_dir, page_dir(generator_cache/pages), live_page_dir(pages), rewrite_class}
      ->generateEverything($labelTranslations)
          checkConfig() → createDirectoryStructure() → generateAutoLoader()
          processTables(): for each information_schema.tables row
              ClassGenerator(Table, config)
                 ->saveBaseClassToFile()   always overwrites Base/Base<T>.php
                       processBaseClass(): inspector->inspect(); generateFields(); generateForeignKeys();
                                           per column: set<Col>Val / get<Col>Val / get<Col>Fld / get<Col>FormElement
                                           per FK:     get<Col>Obj
                                           + listAllArray(), listAllObjects(), getAllFieldsAsFormElements()
                 ->saveClassToFile()       writes <T>.php only if missing (or rewrite_class)
          processPages(): PageGenerator(Table, config, labels)->savePageToFile()  → generator_cache/pages/<t>.page
                 getDbTableDetails(): SELECT with LEFT JOIN per FK (display column), hidden *_id column
                 buildGrid(): PhpGrid + Column per alias + View/Update/Delete ActionButtons
                 related tabs: inspector->getReferencingTables() → one grid per child table filtered by parent id
```

Regeneration rule: schema change → rerun → `Base/` is replaced, `<Table>.php` and live `pages/` are untouched. Labels/hints put in the main class (ASM pattern: `setUpLabels()` in the constructor) survive; anything in `Base/` does not.

---

## 9. Settings keys the framework reads

| Key | Read by |
|---|---|
| `database.hostname/database_name/username/password/port/engine` | `Db::getInstance()`; `engine` ('mysql' \| 'postgres') also by BaseObject quoting, Select, generators |
| `project.package_name` | BaseObject (Person), Field (FK display), Select (FK class), Document, generators (namespace) |
| `settings.use_pretty_url` | UrlParser |
| `settings.label.translations` | BaseObject::getListAllSimpleTableObject (view/update/delete labels) |
| `locale.date.format`, `locale.datetime.format`, `locale.time.format` | Type{Date,DateTime,Time}::formatForDb (input format → DB format) |
| `upload.documents_directory`, `upload.allowed_mime_types` | Filesystem\Document |
| `404` | Filesystem\Document redirects |

App classes the framework expects to exist under the package namespace: `Person` (static `getLoggedUserId()`), `Document` (generated model for table `document` with file_name, file_name_original, file_extension, thumbnail_name, file_size, mime_type, encrypt_key), and (by convention) `Factory::GeneralPage(): Html`.

---

## 10. Where to look for what

| I want to… | Go to |
|---|---|
| change how a column is validated or converted | `Database/Field/Type*.php` (`isValid`, `getValueDbSanitized`, `formatForDb`) |
| change which input a column gets | `Type*::getFormElement()` or per-field `$obj->get<Col>FormElement(new Select())` override |
| change the SQL INSERT/UPDATE behaviour | `BaseObject::insert()/update()` (lines 499-733) |
| add a finder | leaf class (`find*` wrappers call `bOfind*`) |
| change how a form row looks | `UI/Form/Input/Text.php::drawUpdateMode()/drawViewMode()` (all inputs inherit) |
| change the page card chrome | `UI/Page/GenericPage.php::draw()` |
| change the `<head>` / layout | `UI/Page/Html.php` + app `Factory::GeneralPage()` |
| change the grid's SQL wrapping or JSON | `UI/Grid/PhpGrid/PhpGrid.php::generateSql()/outputJsonWithHeaders()` |
| change grid rendering in the browser | `sample_files/grid.js` (`draw`, `drawTableHeader`, `formatLinkHref`, `_resolveCallback`) |
| change generated class shape | `Generator/ClassGenerator.php::processBaseClass()/processClass()` (string templates) |
| change generated pages | `Generator/PageGenerator.php::generatePageFile()/buildGrid()` |
| read schema from a new engine | implement `TableInspectorInterface` like `PostgresTableInspector` |
| know the URL scheme | `Util/UrlParser.php` (`getViewLink`, `getListLink`, `getUpdateLink`, `getDeleteLink`, `getNewLink`) |
