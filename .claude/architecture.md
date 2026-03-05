# LAF Architecture

## File Structure
```
src/Laf/
├── Database/           # ORM layer (~3,700 lines)
│   ├── BaseObject.php  # Active Record base (1,286 lines) — CRUD, forms, audit
│   ├── Db.php          # Singleton PDO wrapper (695 lines)
│   ├── QueryBuilder.php # Fluent query builder (345 lines)
│   ├── Table.php       # Table metadata (332 lines)
│   ├── Field/          # Field types & validation
│   │   ├── Field.php   # Column definition (883 lines)
│   │   ├── FieldType.php, FieldTypeFactory.php
│   │   └── Type*.php   # Integer, Varchar, Date, DateTime, Time, Bool, Float, Text, Json, Blob, Char
│   ├── ForeignKey.php  # FK relationships
│   ├── PrimaryKey.php
│   ├── AuditLog.php    # Automatic change tracking (162 lines)
│   └── SqlErrorLoggerInterface.php
├── UI/                 # Component system (~3,200 lines)
│   ├── ComponentInterface.php (261 lines)
│   ├── Traits/
│   │   ├── ComponentTrait.php (578 lines) — CSS, attributes, composition
│   │   └── FormElementTrait.php (495 lines) — form input properties
│   ├── Form/
│   │   ├── Form.php (763 lines) — auto-generates forms from BaseObject
│   │   ├── DrawMode.php — VIEW, INSERT, UPDATE constants
│   │   └── Input/*.php — Checkbox, Date, Email, File, Hidden, Integer, Password, Select, Text, TextArea, etc.
│   ├── Grid/
│   │   ├── SimpleTable.php (757 lines) — server-side pagination
│   │   └── PhpGrid/ — client-side grid with JS (ActionButton.php, Column.php, Settings.php)
│   ├── Container/ — Div, Card, Modal, Row, Column, TabContainer, GenericContainer
│   ├── Component/ — Alert, Carousel, Dropdown, Image, Link
│   ├── Page/ — GenericPage, AdminPage, Html
│   └── Table/ — HTML table components (Table, Tr, Td, Th)
├── Generator/          # Code generation (~2,200 lines)
│   ├── ClassGenerator.php (566 lines) — generates Base + main classes
│   ├── PageGenerator.php (510 lines) — generates CRUD .page files
│   ├── DatabaseGenerator.php (230 lines) — orchestrator
│   ├── TableInspector.php (230 lines) — MySQL schema reader
│   ├── PostgresTableInspector.php (264 lines)
│   └── TableInspectorInterface.php
├── Util/
│   ├── Settings.php (112 lines) — singleton config store
│   ├── UrlParser.php (326 lines) — /module/submodule/action/id routing
│   └── Util.php (140 lines) — helpers (tableNameToClassName, uuid, toFloat, etc.)
├── Filesystem/Document.php — file handling
└── Exception/ — DbConnect, InvalidForeignKeyValue, InvalidValue, MissingConfigParam, MissingFieldValue, UniqueFieldDuplicate
```

## Frontend
- `sample_files/grid.js` (~1,200 lines) — ES6 Grid class with pagination, sorting, filters, callbacks

## Design Patterns
- **Active Record**: BaseObject — each class = table row with CRUD
- **Singleton**: Db, Settings, UrlParser
- **Builder**: QueryBuilder, Form (fluent interfaces throughout)
- **Factory**: FieldTypeFactory, FormElementFactory
- **Strategy**: FieldType classes for data type behavior
- **Composite**: UI components contain child components
- **Adapter**: TableInspector vs PostgresTableInspector
- **Template Method**: BaseObject defines CRUD skeleton
- **Decorator**: GenericPage wraps content in Bootstrap card

## Key Methods — BaseObject
- CRUD: `select()`, `insert()`, `update()`, `store()`, `hardDelete()`, `softDelete()`
- Query: `bOfind()`, `bOfindOne()`, `findOne()`, `findAsArray()`, `getQueryBuilder()`
- Fields: `setFieldValue()`, `getFieldValue()`, `getFieldValuesAsArray()`
- Forms: `getForm()`, `getAllFieldsAsFormElements()`
- Audit: `logAuditEntry()`, `auditLogDisable()`, `auditLogEnable()`
- Table registry: `populateTableObjectList()` — populates `object_list` table from DB schema

## URL Routing
- Pattern: `/module/submodule/action/id/`
- Actions: list, view, new, update, delete
- Config: `Settings::get('settings.use_pretty_url')`
- Falls back to `?module=m&submodule=s&action=a&id=i`

## Database Config
```php
Settings::set('database.hostname', '...');
Settings::set('database.database_name', '...');
Settings::set('database.username', '...');
Settings::set('database.password', '...');
Settings::set('database.port', 3306);
Settings::set('database.engine', 'mysql'); // or 'postgres'
Settings::set('project.package_name', 'MyApp');
```
