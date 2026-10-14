# Relations: foreign keys, autocomplete and many-to-many

LAF knows two kinds of relations and shows each in forms. A **foreign key** column (`employee.company_id → company`)
holds exactly one key. It is drawn as a `<select>`, or as an `Autocomplete` search box when the referenced table is
too big for a drop-down. A **many-to-many** relation exists only through a **linker table**
(`employee_project(employee_id, project_id)`). The generator detects the linker table and adds a relation definition
to both sides' Base classes. `$employee->manyToMany('employee_project')` then reads and writes the links, and the
Employee form shows a "Projects" picker by itself, as checkboxes, chips with search, or two lists. This chapter covers
both kinds: the lookup/create JSON endpoint that the autocomplete widgets use, the `ManyToMany` API, the three
pickers, how a form stores the row and its links in one transaction, and the browser-side events and
`LafAutocomplete` API.

**On this page**

- [The example tables](#the-example-tables)
- [Foreign keys on the model](#foreign-keys-on-the-model)
- [Foreign keys in forms: Select or Autocomplete](#foreign-keys-in-forms-select-or-autocomplete)
- [The lookup endpoint: `handleLookupRequest()`](#the-lookup-endpoint-handlelookuprequest)
- [Searching: columns, custom sources, labels](#searching-columns-custom-sources-labels)
- ["Create new" from the list](#create-new-from-the-list)
- [Many-to-many: linker tables and `addManyToMany()`](#many-to-many-linker-tables-and-addmanytomany)
- [The `ManyToMany` API](#the-manytomany-api)
- [The three pickers](#the-three-pickers)
- [Configuring a picker](#configuring-a-picker)
- [Saving: row and links in one transaction](#saving-row-and-links-in-one-transaction)
- [Browser side: markup, events and `LafAutocomplete`](#browser-side-markup-events-and-lafautocomplete)
- [The demo page](#the-demo-page)
- [Reference](#reference)
- [See also](#see-also)

## The example tables

This chapter uses the shared example schema ([`examples/schema.mysql.sql`](examples/schema.mysql.sql)):

```sql
CREATE TABLE `employee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL COMMENT '{"fields":{"setLabel":"Company"}}',
  ...
  CONSTRAINT `employee_company_fk` FOREIGN KEY (`company_id`) REFERENCES `company` (`id`)
);

CREATE TABLE `employee_project` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL COMMENT '{"fields":{"setLabel":"Team members"}}',
  `project_id` int(11) NOT NULL COMMENT '{"fields":{"setLabel":"Projects"}}',
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_project_unique` (`employee_id`, `project_id`),
  CONSTRAINT `employee_project_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_project_project_fk` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`) ON DELETE CASCADE
);
```

The comment on a linker column labels the picker on the **other** side. The comment on `project_id` says
"Projects", which is the label of the picker on the Employee form. The comment on `employee_id` says
"Team members", which is the label of the picker on the Project form.

The generator turns this into the following lines (from
[`examples/generated/MyApp/Base/BaseEmployee.php`](examples/generated/MyApp/Base/BaseEmployee.php)):

```php
$this->getTable()->addForeignKey(
    (new ForeignKey())
        ->setField($this->getTable()->getField("company_id"))
        ->setKeyName('employee_company_fk')
        ->setReferencingTable("company")
        ->setReferencingField("id")
);

$this->getTable()->addManyToMany("employee_project", ['owner' => 'employee_id', 'other' => 'project_id', 'table' => 'project', 'label' => 'Projects']);
```

[`BaseProject.php`](examples/generated/MyApp/Base/BaseProject.php) gets the mirror image:

```php
$this->getTable()->addManyToMany("employee_project", ['owner' => 'project_id', 'other' => 'employee_id', 'table' => 'employee', 'label' => 'Team members']);
```

> **Note:** `ForeignKey::setReferencingTable()` / `getReferencingTable()` hold the table that is **referenced**
> (`company`). The naming is historical. The aliases `setReferencedTable()` / `getReferencedTable()` and
> `setReferencedField()` / `getReferencedField()` mean the same thing and read better in new code.

## Foreign keys on the model

Every foreign key column gets a generated getter `get{Column}Obj()` that returns the referenced row as its model
object:

```php
use MyApp\Employee;

$ada = Employee::findOne(['email' => 'rel-ada@example.com']);

$company = $ada->getCompanyIdObj();         // MyApp\Company, loaded once
echo $company->getNameVal();                // Rel Acme Ltd

$ada->getCompanyIdObj() === $company;       // true: cached while company_id keeps its value
$ada->getCompanyIdObj(true) === $company;   // false: $fresh = true reloads and replaces the cached object

(new Employee())->getCompanyIdObj()->isLoaded();   // false: an empty key gives a new, empty Company
```

The getter calls the protected `BaseObject::relatedObject($class, $fieldName, $fresh)`. That method caches the object
per key value, so calling `$order->getCustomerIdObj()` in a loop runs one SELECT per order, not one per call. Changing
`company_id` invalidates the cache automatically, because the cache key is the column's value.

There are two shortcuts that work without the generated getter:

```php
$ada->getCompanyIdFld()->getReferencedValue();      // "Rel Acme Ltd": the display field of the referenced row

$fk = $ada->getTable()->getForeignKey('company_id');
$fk->getReferencedClassName();                       // "\MyApp\Company"
$fk->resolveReferencedFieldName();                   // "id"
$fk->getReferencedObject();                          // new \MyApp\Company(<current company_id>)
$fk->getReferencedTableMeta();                       // the Company Table metadata (cached, no row loaded)
$fk->isValidValue(3);                                // true when company 3 exists (prepared statement)
```

The full `ForeignKey` metadata API (on-delete rules, nullability, validation cache) is in
[03-models.md](03-models.md).

### The display field

A select, an autocomplete or a picker labels a row with its table's **display field**. `Table::getDisplayField()`
returns the field set by `setDisplayField()`. Otherwise it falls back to the first of `name`, `label` or
`description` that exists, and if none exists, to the **second column** of the table. `company` and `project` have a
`name` column, so they label well. `employee` has none. Without help its display field would fall back to
`company_id`, and the "Team members" picker on the Project form would show company ids. The example schema
therefore marks `employee.email` with the column comment `{"fields":{"setLabel":"E-mail","setDisplayField":true}}`,
and the generator writes `$this->getTable()->setDisplayField($this->getTable()->getField("email"));` into
`BaseEmployee` ([see it](examples/generated/MyApp/Base/BaseEmployee.php)). `"setDisplayField": "other_column"` names
another column of the same table. You can also set it in the leaf class:

```php
namespace MyApp;

class Employee extends Base\BaseEmployee
{
    public function __construct($id = null)
    {
        parent::__construct($id);
        $this->getTable()->setDisplayField($this->getTable()->getField('last_name'));
    }
}
```

Other tables read the metadata through `ForeignKey::getReferencedTableMeta()`, which instantiates the leaf class, so a
display field set in the leaf constructor applies everywhere.

## Foreign keys in forms: Select or Autocomplete

A foreign key field's element is created by its field type (`AbstractFieldType::getFormElement()`):

| Condition | Element |
|---|---|
| The FK points at the document table | `Input\File` (see [15-files-and-documents.md](15-files-and-documents.md)) |
| Settings `form.select_autocomplete_threshold` > 0 and the referenced table has more rows than that | `Input\Autocomplete` |
| Otherwise | `Input\Select` with every row of the referenced table, ordered by the display field |

The row count is read once per request and per table (`SELECT COUNT(*)`). The default threshold is 0, which turns
the automatic switch off.

You can also choose the element for one field. The class must be a `Select` (`Select`, `Radio`,
`CheckboxCollection`, `Autocomplete`), because only these keep the foreign key's options:

```php
// 1. column comment, picked up by the generator and FormLayout::auto()
//    `company_id` int(11) DEFAULT NULL COMMENT '{"form":{"element":"Autocomplete"}}'

// 2. a layout
$layout = FormLayout::auto($employee->getTable())->element('company_id', 'Autocomplete');

// 3. the field
$employee->getCompanyIdFld()->setFormElementClass('Autocomplete');

$employee->getCompanyIdFld()->setFormElementClass('Text');
// InvalidArgumentException: Field company_id is a foreign key: its element must be a Select, "Laf\UI\Form\Input\Text" given
```

**A foreign key always holds one value.** On a field bound to a foreign key, `Select::isMultiple()` returns false
even after `setMultiple(true)`, and `CheckboxCollection` draws radio buttons. To pick several values you need a
linker table ([below](#many-to-many-linker-tables-and-addmanytomany)).

The default `Select` drawn for `company_id` on the Employee form (the field name is ROT13-encoded, as with every
form field; see [06-forms.md](06-forms.md)):

```html
<select class='Laf-UI-Form-Input-Select form-control form-select' style=''
    name="pbzcnal_vq"
    id="pbzcnal_vq"><option value=''>&nbsp;</option>
    <option value='3' selected='selected'>Rel Acme Ltd</option>
    ...
</select>
```

The same field as an `Autocomplete` with create enabled. It has a hidden input that posts the key under the field's
name, a visible text box that only searches, and an empty menu that the script fills:

```html
<div class="laf-autocomplete position-relative"
    data-laf-autocomplete="employee.company_id"
    data-min-chars="1"
    data-no-results="No matches"
    data-create="1"
    data-create-text="Create &quot;%s&quot;"
    data-csrf-name="_csrf_token_employee"
    data-csrf-token="edd67e53...">
    <input type="hidden" class="laf-autocomplete-value" id="pbzcnal_vq_value" name="pbzcnal_vq" value="3" />
    <input type="text" class="Laf-UI-Form-Input-Autocomplete form-control laf-autocomplete-input" style="" value="Rel Acme Ltd"
        id="pbzcnal_vq" placeholder="Company" ... autocomplete="off" role="combobox"
        aria-autocomplete="list" aria-expanded="false" aria-controls="pbzcnal_vq_listbox" />
    <div class="dropdown-menu w-100 laf-autocomplete-menu" id="pbzcnal_vq_listbox" role="listbox" style="max-height:20rem;overflow-y:auto"></div>
</div>
```

Because the posted value is the same as a `Select`'s, `processForm()` handles both identically. Your page code does
not change when a field switches between them.

> **Note:** The visible text box inherits the attributes of the field type. On an integer key such as `company_id`
> these are `step="1" inputmode="numeric" min=… max=…` (elided as `...` above). On phones,
> `inputmode="numeric"` opens a numeric keypad for what is a name search. This is a known shortcoming of the current
> version. Until it is fixed, remove the attribute in the browser if it matters to you
> (`box.querySelector('.laf-autocomplete-input').removeAttribute('inputmode')`).

## The lookup endpoint: `handleLookupRequest()`

An autocomplete has no endpoint of its own. The **page that draws the form answers the searches**: after the form is
built and before any output, call:

```php
use Laf\UI\Form\DrawMode;
use MyApp\Employee;

$employee = new Employee($id);
$form = $employee->getForm(DrawMode::UPDATE);
$form->handleLookupRequest();   // a lookup request is answered with JSON and the script exits here
// ... normal page rendering
```

Generated pages already make this call (see [`examples/generated/pages/employee.page`](examples/generated/pages/employee.page)).
The script requests the current URL with two extra parameters:

| Parameter | Meaning |
|---|---|
| `laf_lookup` | the element's lookup key: `table.field` for a foreign key (`employee.company_id`), `linker_table.other_column` for a many-to-many picker (`employee_project.project_id`) |
| `laf_q` | the typed text (trimmed, at most `Autocomplete::MAX_TERM_LENGTH` = 100 characters) |
| `laf_create=1` | makes it a create request (POST only, see [below](#create-new-from-the-list)) |

Only autocomplete elements **on this form** are answered, both field elements and relation pickers. Any other key
gets a 404. Because of this, a page cannot be used to read arbitrary tables.

Signature: `handleLookupRequest(?array $query = null, bool $terminate = true, ?array $post = null): bool`.

- `$query` defaults to `$_GET`, and `$post` (for a create) defaults to `$_POST` when the request method is POST.
- With `$terminate = true` the method discards all output buffers, sends the status code, `Content-Type:
  application/json; charset=utf-8` and `Cache-Control: no-store`, prints the JSON and exits. With `false` it prints
  the JSON and returns. Use `false` in tests and long-running runtimes, and read the status with
  `getLookupStatus()`.
- The method returns `false` when the request is not a lookup (no `laf_lookup`). In that case nothing is printed
  and `getLookupStatus()` keeps its previous value.
- `terminateRequest()` (protected, `exit` by default) is the hook a subclass can override.

Real output from the example database. The Projects picker is drawn as chips with `setLimit(2)`, and the Company
field as an autocomplete that searches `name` and `vat_number`:

```php
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormLayout;
use MyApp\Employee;

$ada = Employee::findOne(['email' => 'rel-ada@example.com']);
$layout = FormLayout::auto($ada->getTable())
    ->element('employee_project', 'Autocomplete')
    ->element('company_id', 'Autocomplete');
$form = $ada->getForm(DrawMode::UPDATE, $layout);
$form->getRelationElement('employee_project')->setAllowCreate(true)->setLimit(2);
$form->getFieldElement('company_id')->setSearchColumns(['name', 'vat_number']);

$form->handleLookupRequest(['laf_lookup' => 'employee_project.project_id', 'laf_q' => 'rel'], false);
// {"results":[{"value":"2","label":"Forms: Website relaunch"},{"value":"4","label":"Rel Apollo"}],"more":true}
$form->getLookupStatus();   // 200

$form->handleLookupRequest(['laf_lookup' => 'employee.company_id', 'laf_q' => 'acme'], false);
// {"results":[{"value":"4","label":"Ch10 Acme Corp"},{"value":"3","label":"Rel Acme Ltd"}],"more":false}

$form->handleLookupRequest(['laf_lookup' => 'employee.first_name', 'laf_q' => 'x'], false);
// {"results":[],"more":false,"error":"Unknown lookup"}   status 404

$form->handleLookupRequest(['page' => 2], false);   // false: not a lookup, nothing printed
```

The JSON contract:

| Situation | Status | Body |
|---|---|---|
| Search | 200 | `{"results": [{"value": "…", "label": "…"}], "more": bool}`. `more` = more rows than `getLimit()` exist |
| Unknown key | 404 | `{"results": [], "more": false, "error": "Unknown lookup"}` |
| The search threw | 500 | `{"results": [], "more": false, "error": "Lookup failed"}` |
| Create | see [create](#create-new-from-the-list) | |

Values are always strings. Matching is "contains" and case-insensitive on both engines (`Query::whereContains()`, so
`%` and `_` in the typed text are matched literally). Results are ordered by the display field. The field's
`setDbSelectionCriteria()` filters are applied to the search, as they are to a `Select`'s options. A many-to-many
search returns rows that are already chosen as well. The script filters those out in the browser.

> **Note:** Call `handleLookupRequest()` **after** you configure the elements (search columns, callbacks, create)
> and **before** anything is echoed. The lookup uses the element as configured at that moment.

## Searching: columns, custom sources, labels

| Method | Default | What it does |
|---|---|---|
| `setSearchColumns(['name', 'vat_number'])` | display field | Columns of the referenced table that may contain the text (OR). Unknown columns are ignored |
| `setLimit(int)` | 20 | Results per search, clamped to 1–100 |
| `setMinChars(int)` | 1 | Characters typed before the first search. 0 = search on focus |
| `setNoResultsText(string)` | `No matches` | Text of the empty list |
| `setLookupUrl(?string)` | current URL | Where the script sends searches (and creates). Useful when the form is drawn in a modal from another page |
| `setOptions([value => label])` | – | A static list instead of the table: searched in PHP, case-insensitive contains |
| `setLookupCallback(fn (string $term, int $limit, Autocomplete $el))` | – | A custom source. It returns `value => label` or a list of `['value' => …, 'label' => …]`. It is called with `$limit + 1` so that `more` can be computed |
| `setLabelCallback(fn (string $value, Autocomplete $el): ?string)` | – | The label of a stored value for a custom source (null = show the value) |

A custom source that shows the VAT number next to the company name (run against the example database):

```php
use Laf\Database\Db;
use MyApp\Company;
use MyApp\Employee;

$employee = new Employee();
$field = $employee->getCompanyIdFld();
$field->setFormElementClass('Autocomplete');

/** @var \Laf\UI\Form\Input\Autocomplete $company */
$company = $field->getFormElement();
$company
    ->setLookupCallback(fn (string $term, int $limit) => Db::getInstance()->getAllAssoc(
        "SELECT id AS value, CONCAT(name, ' (', COALESCE(vat_number, 'no VAT'), ')') AS label
           FROM company WHERE name LIKE :t ORDER BY name LIMIT " . $limit,
        [':t' => '%' . $term . '%']
    ))
    ->setLabelCallback(fn (string $value) => Company::load((int)$value)?->getNameVal());

$company->search('rel');
// ['results' => [['value' => '3', 'label' => 'Rel Acme Ltd (no VAT)']], 'more' => false]

$employee->setCompanyIdVal(3);
$company->getSelectedLabel();   // "Rel Acme Ltd"
```

`search(string $term, ?int $limit = null): array` is public, so you can call it directly in tests. It returns the
same array that the endpoint encodes as JSON.

## "Create new" from the list

`setAllowCreate(true)` (or the column comment `{"form":{"create":true}}`) adds a last entry `+ Create "typed text"`
to the list when no result label matches the typed text exactly (case-insensitive). Choosing that entry POSTs the
text to the lookup URL with `laf_create=1`. The server stores a row of the referenced table and answers with its key,
and the script selects the new row.

Rules on the server (`Form::handleLookupRequest()` → `Autocomplete::create()`):

1. Only POST. A GET is answered with 405.
2. The form's CSRF token must be in the body under the form's token name (`_csrf_token_employee`). When the form is
   drawn, `Form` puts the name and token on the element as `data-csrf-name` / `data-csrf-token`, so the script
   sends them. A bad token gives 403.
3. The element must allow creating, be editable (not disabled, excluded or read-only), and the form must not be a
   VIEW form. Otherwise the answer is 403.
4. **Default create:** if a row of the referenced table already has the text as its label (case-insensitive), that
   row is returned with `created: false`. Otherwise a new row is stored with the **display field = the text**, plus
   the field's db selection criteria, with `setValidateOnStore(true)`. A refused write becomes a 422 message for
   the user: a required column without a value gives `"X" cannot be added here: more details are required`, a
   duplicate gives `"X" already exists`, and a validation failure gives the validation messages.
5. **Custom create:** `setCreateCallback(fn (string $term, Autocomplete $el) => …)` replaces the default and implies
   `setAllowCreate(true)`. It returns the stored `BaseObject`, an array `['value' => …, 'label' => …]` (optionally
   `'created' => false`), a string (the refusal reason, 422), or null (refused without a reason, 422 with a
   generic message). Use the callback for tables that have other required columns.

Run against the example database, with the Projects picker drawn as chips (`setAllowCreate(true)`):

```php
$token = $form->getCsrfToken();
$lookup = ['laf_lookup' => 'employee_project.project_id', 'laf_create' => '1'];

$form->handleLookupRequest($lookup, false, ['laf_q' => 'Rel Zephyr', '_csrf_token_employee' => $token]);
// 200 {"value":"8","label":"Rel Zephyr","created":true}

$form->handleLookupRequest($lookup, false, ['laf_q' => 'rel zephyr', '_csrf_token_employee' => $token]);
// 200 {"value":"8","label":"Rel Zephyr","created":false}      the existing row is reused

$form->handleLookupRequest($lookup, false, ['laf_q' => 'Rel X', '_csrf_token_employee' => 'nope']);
// 403 {"error":"The form is out of date; reload the page and try again"}

$form->handleLookupRequest(['laf_lookup' => 'employee.company_id', 'laf_create' => '1'], false,
    ['laf_q' => 'Rel X', '_csrf_token_employee' => $token]);
// 403 {"error":"New entries cannot be added here"}            company_id has no setAllowCreate()

$form->handleLookupRequest($lookup, false);                   // CLI: method is not POST, no body given
// 405 {"error":"Use POST"}

$form->getRelationElement('employee_project')->setCreateCallback(
    fn (string $term) => strlen($term) < 8 ? 'Project names need at least 8 characters' : null
);
$form->handleLookupRequest($lookup, false, ['laf_q' => 'Rel Z', '_csrf_token_employee' => $token]);
// 422 {"error":"Project names need at least 8 characters"}
```

The full status table for creates:

| Status | Body | When |
|---|---|---|
| 200 | `{"value", "label", "created": bool}` | Stored (or an exact match reused) |
| 403 | `{"error": "The form is out of date; reload the page and try again"}` | Missing or wrong CSRF token (checked first) |
| 403 | `{"error": "New entries cannot be added here"}` | Creating is off, the element is not editable, or the form is a VIEW form |
| 404 | `{"error": "Unknown lookup"}` | No autocomplete with that key on the form |
| 405 | `{"error": "Use POST"}` | Not a POST request |
| 422 | `{"error": "<reason>"}` | Refused. The script shows the reason as a red row in the list |
| 500 | `{"error": "The entry could not be added"}` | An unexpected exception |

`setCreateText('New company: %s')` changes the caption of the entry (`%s` = the typed text). The script adds the
leading `+ `.

> **Note:** Creating writes to the database from a search box, so treat it like any other form submission: it needs
> the session-bound CSRF token, and it works only on edit forms that drew that element as editable. Enable it per
> field. It is off by default. The created row goes through the referenced model's `insert()`, so the audit log and
> model hooks run.

## Many-to-many: linker tables and `addManyToMany()`

LAF supports multi-value selection **only through a linker table**. The generator's `JunctionDetector` treats a
table as a linker of table T when:

- it has **exactly two foreign keys**, pointing at **two different tables**, one of which is T (if both point at T,
  it is a plain child table), and
- it has **at most two data columns** (`JunctionDetector::MAX_EXTRA_COLUMNS`) besides the primary key and the
  metadata columns `id, created_on, created_by, updated_on, updated_by, is_deleted, deleted, record_status_id`.

`employee_project(id, employee_id, project_id)` qualifies. A child table with its own data (an `invoice_line`) does
not. For each linker found, the generator writes one `addManyToMany()` into **both** sides' Base classes. The
definition is configured by the comment of the linker column that points at the **picked** table:

| Comment key (on `employee_project.project_id`) | Effect on the Employee form's picker |
|---|---|
| `fields.setLabel` | label (default: the other table's name pluralised, "Projects") |
| `fields.setHint` | hint under the picker |
| `fields.setRequired: true` | at least one row must be chosen |
| `form.element` | `Autocomplete` (chips), `DualList`, `CheckboxCollection` / `Checkboxes`. The alias `TransferList` works only at runtime (`FormLayout::element()`, `getFormElement('TransferList')`). In a column comment the generator drops it with a warning, so write `DualList` there |
| `form.create: true` | the chips or dual list may create rows of the other table |
| `form.order`, `form.group`, `form.tab` | placement by `FormLayout::auto()` |
| `form.exclude`, `form.readonly`, `form.hidden` | true or draw modes, as for a column |
| `form.multi: false` | **no picker** for this side (the definition is not generated) |

The generator also refuses a picker, with a warning, when the linker table has a required data column without a
default. Such a link cannot be inserted from a picker. Details are in [02-code-generation.md](02-code-generation.md).

You can also declare a relation by hand, for example for a linker the generator did not detect:

```php
$this->getTable()->addManyToMany('employee_project', [
    'owner' => 'employee_id',          // linker column pointing at this table (required)
    'other' => 'project_id',           // linker column pointing at the picked table (required)
    'table' => 'project',              // informative; the other table is read from the linker model's FK
    'label' => 'Projects',
    'hint' => 'Projects this person bills time to',
    'required' => true,
    'form' => ['element' => 'DualList', 'create' => true, 'tab' => 'Assignments'],
]);
```

`Table` also has `hasManyToMany($junction)`, `getManyToManyDefinition($junction)`, `getManyToManyDefinitions()` and
`removeManyToMany($junction)`. A relation needs the **linker table's generated model** (`MyApp\EmployeeProject`), and
both columns must be foreign keys on it. `ManyToMany::isAvailable()` checks this. A form leaves out a relation that
is not available without raising an error.

## The `ManyToMany` API

`$employee->manyToMany('employee_project')` returns the relation of that row. It is cached per object, reset when the
object is cloned, and throws `InvalidArgumentException` for an unknown name. Real output:

```php
use Laf\Database\ManyToMany;
use MyApp\Employee;

$ada = Employee::findOne(['email' => 'rel-ada@example.com']);   // projects: Rel Apollo 4, Rel Gemini 5, Rel Mercury 6, Rel Voyager 7
$projects = $ada->manyToMany('employee_project');

$projects->getLabel();          // "Projects"
$projects->ids();               // []   (no links yet)

$projects->sync([4, 5]);        // ['added' => ['4', '5'], 'removed' => []]
$projects->ids();               // ['4', '5']
$projects->sync(['5', 6, 6]);   // ['added' => ['6'], 'removed' => ['4']]
$projects->sync([6, 5]);        // ['added' => [], 'removed' => []]   same set, nothing written

foreach ($projects->objects() as $project) {   // MyApp\Project objects, ordered by the display field
    echo $project->getNameVal(), "\n";           // Rel Gemini, Rel Mercury
}

$projects->labels([6, 99999, 4]);     // ['6' => 'Rel Mercury', '4' => 'Rel Apollo']   given order, unknown keys dropped
$projects->validIds([7, 99999, ' 4 ', 4]);   // ['7', '4']   existing keys, trimmed, without duplicates
$projects->options();                 // every project: ['4' => 'Rel Apollo', '5' => 'Rel Gemini', ...] ordered by label
$projects->countOptions();            // rows of project (counted once per request)

ManyToMany::diff([3, 5], ['5', 7, 7, '']);         // ['added' => ['7'], 'removed' => ['3']]
ManyToMany::cleanIds([' 3', 3, true, null, [1], '', 'x']);   // ['3', '1', 'x']
```

Things to know about `sync()`:

- It makes the owner linked to **exactly** the given keys. It deletes the missing links (`hardDelete()` on each
  linker row) and inserts new ones (`insert()` on a new `EmployeeProject`), inside `BaseObject::transaction()`. If
  you are already in a transaction it joins it. Because every link goes through the linker's model, the audit log
  records one INSERT or DELETE per link, and the hooks of `EmployeeProject` run.
- It does **not** check that the keys exist. The database's foreign key refuses an unknown key with a
  `DatabaseException`. Call `validIds()` first if the keys come from user input. Forms do this check for you.
- The owner must be stored first:

```php
(new Employee())->manyToMany('employee_project')->sync([1]);
// LogicException: Many-to-many employee_project: store the owner before linking rows to it
(new Employee())->manyToMany('employee_project')->ids();   // [] (no exception)
```

Keys are always returned as strings. `ids()` is cached until the next `sync()`. On the other side, the same linker
reads in reverse: `Project::findOne(['name' => 'Rel Gemini'])->manyToMany('employee_project')->ids()` lists employee
ids, labelled by the Employee display field, `email` ([see the note above](#the-display-field)).

## The three pickers

Every relation definition puts a picker on both sides' forms. `FormLayout::auto()` places it after the columns,
unless the definition's `order` / `group` / `tab` says otherwise. A layout built by hand appends relations it did not
list, just as it appends unlisted fields. The picker's value starts as `ids()`.

| Class | Chosen by | Best for | Posts |
|---|---|---|---|
| `Input\ManyToManyCheckboxes` (extends `CheckboxCollection`) | default; `CheckboxCollection`, `Checkboxes`, `Checkbox` | small tables (all rows shown) | `employee_project[]` per ticked box |
| `Input\ManyToManyAutocomplete` (extends `Autocomplete`) | `Autocomplete`; or by default when the other table has more rows than `form.select_autocomplete_threshold` | big tables (search) | `employee_project[]` per chip |
| `Input\ManyToManyDualList` (extends `ManyToManyAutocomplete`) | `DualList`, `TransferList` | up to a few hundred rows, when users move many at once | `employee_project[]` per selected option |

All three post an empty hidden `employee_project[]` value first, so that removing every choice is still submitted.
They post under the **relation name** (the linker table), not under a ROT13 column name. Their element id is
`m2m_{relation}`.

Ways to choose the picker, from most general to most specific:

```php
// comment on the linker column employee_project.project_id: {"form":{"element":"DualList"}}

$layout = FormLayout::auto($employee->getTable())->element('employee_project', 'Autocomplete');
$form = $employee->getForm(DrawMode::UPDATE, $layout);

$picker = $employee->manyToMany('employee_project')->getFormElement('DualList');   // before getForm()
```

`ManyToMany::resolveElementClass()` maps the short names: `Autocomplete` → `ManyToManyAutocomplete`;
`CheckboxCollection`, `Checkboxes`, `Checkbox` → `ManyToManyCheckboxes`; `DualList`, `TransferList` →
`ManyToManyDualList`. A class implementing `RelationElementInterface` is used as is. Anything else throws
`InvalidArgumentException`.

To get at the picker after the form is built, use `Form::getRelationElement('employee_project')`.
`getRelationElements()` lists all of them.

### Checkboxes (default)

Employee "Ada" linked to Rel Gemini and Rel Mercury, UPDATE mode (excerpt, real output):

```html
<div id='m2m_employee_project_container' style='' class='mb-3'>
    <label id='m2m_employee_project_label' for='m2m_employee_project_1' class='col-form-label'>Projects :</label>
    <div id='m2m_employee_project' class='laf-choice-list'>
        <input type='hidden' name='employee_project[]' value='' />
        <div class='form-check'>
            <input type='checkbox' id='m2m_employee_project_3' name='employee_project[]' value='4' class='Laf-UI-Form-Input-ManyToManyCheckboxes form-check-input' style='' />
            <label class='form-check-label' for='m2m_employee_project_3'>Rel Apollo</label>
        </div>
        <div class='form-check'>
            <input type='checkbox' id='m2m_employee_project_4' name='employee_project[]' value='5' class='Laf-UI-Form-Input-ManyToManyCheckboxes form-check-input' style='' checked="checked" />
            <label class='form-check-label' for='m2m_employee_project_4'>Rel Gemini</label>
        </div>
        ...
    </div>
</div>
```

The checkboxes need no script. `setInline(true)` (from `CheckboxCollection`) puts them on one line. `setOptions()`
replaces the rows of the other table with a static list.

### Chips with search (`Autocomplete`)

```html
<div class="laf-autocomplete laf-autocomplete-multi position-relative"
    data-laf-autocomplete="employee_project.project_id"
    data-multiple="1"
    data-name="employee_project[]"
    data-min-chars="1"
    data-no-results="No matches"
    data-create="1"
    data-create-text="Create &quot;%s&quot;"
    data-csrf-name="_csrf_token_employee"
    data-csrf-token="930c9fce...">
    <input type="hidden" class="laf-autocomplete-empty" name="employee_project[]" value="" />
    <div class="laf-autocomplete-chips d-flex flex-wrap gap-1 mb-1" id="m2m_employee_project_chips" aria-live="polite">
        <span class="badge bg-secondary laf-autocomplete-chip d-inline-flex align-items-center" data-value="5"><span class="laf-autocomplete-chip-label">Rel Gemini</span><button type="button" class="btn-close btn-close-white ms-1 laf-autocomplete-remove" style="font-size:.6em" aria-label="Remove Rel Gemini"></button><input type="hidden" name="employee_project[]" value="5" /></span>
        <span class="badge bg-secondary laf-autocomplete-chip d-inline-flex align-items-center" data-value="6">...Rel Mercury...</span>
    </div>
    <input type="text" class="Laf-UI-Form-Input-ManyToManyAutocomplete form-control laf-autocomplete-input" style="" value=""
        id="m2m_employee_project" autocomplete="off" role="combobox" aria-autocomplete="list"
        aria-expanded="false" aria-controls="m2m_employee_project_listbox" />
    <div class="dropdown-menu w-100 laf-autocomplete-menu" id="m2m_employee_project_listbox" role="listbox" style="max-height:20rem;overflow-y:auto"></div>
</div>
```

Typing searches the other table through `handleLookupRequest()` (key `employee_project.project_id`). Chosen rows are
left out of the results, Backspace in the empty box removes the last chip, and × removes a chip. Every option of
`Autocomplete` applies (`setMinChars()`, `setLimit()`, `setSearchColumns()`, `setAllowCreate()`,
`setCreateCallback()`, …). Creating stores a row of the **other** table (a `Project`), and the field's db selection
criteria do not apply here.

### Two lists (`DualList`)

```html
<div class="laf-duallist" id="m2m_employee_project_box"
    data-laf-autocomplete="employee_project.project_id"
    data-name="employee_project[]"
    data-create="1" data-create-text="Create &quot;%s&quot;"
    data-csrf-name="_csrf_token_employee" data-csrf-token="11d246c7...">
    <input type="hidden" class="laf-duallist-empty" name="employee_project[]" value="" />
    <div class="laf-duallist-values"><input type="hidden" name="employee_project[]" value="5" /><input type="hidden" name="employee_project[]" value="6" /></div>
    <div class="row g-2 align-items-center">
        <div class="col-md">
            <label class="form-label small text-muted mb-1" for="m2m_employee_project_available">Available</label>
            <input type="search" class="form-control form-control-sm mb-1 laf-duallist-filter" placeholder="Filter" ... />
            <select multiple size="8" id="m2m_employee_project_available" class="form-select laf-duallist-available">
                <option value="4" data-position="2">Rel Apollo</option>
                <option value="7" data-position="5">Rel Voyager</option>
            </select>
            <div class="input-group input-group-sm mt-1"><input type="text" class="form-control laf-duallist-new" ... placeholder="New entry" maxlength="100" /><button type="button" class="btn btn-outline-primary laf-duallist-create">Add new</button></div>
            <div class="small text-danger laf-duallist-error" role="alert"></div>
        </div>
        <div class="col-md-auto d-flex flex-wrap flex-md-column gap-1 justify-content-center">
            <button type="button" class="btn btn-outline-secondary btn-sm laf-duallist-add">Add</button> ... Add all / Remove / Remove all
        </div>
        <div class="col-md">
            <label class="form-label small text-muted mb-1" for="m2m_employee_project">Selected</label>
            <select multiple size="8" id="m2m_employee_project" class="Laf-UI-Form-Input-ManyToManyDualList form-select laf-duallist-selected">
                <option value="5" data-position="3">Rel Gemini</option>
                <option value="6" data-position="4">Rel Mercury</option>
            </select>
        </div>
    </div>
</div>
```

The native `<select>`s post nothing. The script rebuilds the hidden inputs in `.laf-duallist-values` from the
selected list after every move, so the keys post in the selected list's order. The lists sit side by side from the
`md` breakpoint and stack on phones. The buttons are labelled with words rather than arrows, because the direction
changes when the lists stack. Interaction: Add / Add all / Remove / Remove all, a double click on an option, Enter
in the available list, Delete or Backspace in the selected list, and the filter box narrows the available list
("Add all" moves only the visible options). With `setAllowCreate(true)`, a "New entry" box under the available list
creates a row through the same create request as the chips, and moves the new row across.

`setSize(int)` sets the visible rows (minimum 3, default 8). `setTexts([...])` replaces the captions (`available`,
`selected`, `filter`, `add`, `addAll`, `remove`, `removeAll`, `newEntry`, `create`). `getTexts()` returns them.
The dual list loads **every** row of the other table, so use the chips for big tables.

### VIEW mode

In a VIEW form every picker draws the chosen labels as text, comma-separated and escaped:

```html
<div id='m2m_employee_project' class='Laf-UI-Form-Input-ManyToManyCheckboxes form-control-plaintext border border-secondary border-1 bg-light rounded p-2' style='...'>Rel Gemini, Rel Mercury</div>
```

## Configuring a picker

```php
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormLayout;
use Laf\UI\Form\Input\ManyToManyAutocomplete;

$layout = (new FormLayout())
    ->fields(['first_name', 'last_name', 'email'], 2)
    ->tab('Assignments', ['company_id', 'employee_project'])   // a relation name works wherever a field name does
    ->element('employee_project', 'Autocomplete');

$form = $employee->getForm(DrawMode::UPDATE, $layout);

/** @var ManyToManyAutocomplete $projects */
$projects = $form->getRelationElement('employee_project');
$projects->setLabel('Projects')
    ->setHint('Type to search; Backspace removes the last project')
    ->setMinChars(0)            // list on focus
    ->setMaxItems(5)            // validated on submit; the script stops adding at 5
    ->setAllowCreate(true);
$projects->setRequired(true);   // at least one project

$form->handleLookupRequest();
```

`FormLayout::exclude(['employee_project'])`, `readOnly([...])`, `hidden([...])` and `element()` accept the relation
name in the same way. When excluded, the picker is not on the form at all (`getRelationElement()` returns null), and
the links are left alone.

> **Note:** `FormLayout::auto()` already places every field and relation in a section. Calling
> `->tab('Assignments', [...])` on an `auto()` layout does not move fields it already placed, so build the layout
> with `new FormLayout()` as above, or set `{"form":{"tab":"Assignments"}}` in the column comments. See
> [06-forms.md](06-forms.md).

## Saving: row and links in one transaction

`processForm()` treats a picker like any other input, with three additions:

1. **Reading.** `applySubmittedRelations()` reads `employee_project[]` into the picker (`setSelectedIds()`). A picker
   that the request **does not carry** is left alone, and its links stay as they are. The signed field list
   (`employee_fields`) carries `_rel_employee_project` for each editable picker. When a signed list is posted, or
   with `setRestrictToRenderedFields(true)`, a relation outside that list is ignored, which is the same
   mass-assignment guard that applies to columns.
2. **Validating.** `validateRelations()` adds `Projects: choose at least one` (required), `Projects: choose at most N`
   (`setMaxItems()`), and `Projects: unknown value` (a key that `validIds()` does not find) under the relation name.
3. **Writing.** When at least one picker was posted, the row and every `sync()` run inside one
   `BaseObject::transaction()`. A refused link rolls the row back and becomes the picker's error
   (`Projects: the choice could not be saved`). Forms without pickers keep the plain write without a transaction.
   An update that changes only the links is fine: `update()` writes nothing for a clean row, and the links still
   sync.

After a successful write, each posted picker is refreshed from the database (`ids()`).

A complete run with `buildRequest()`, which builds the same request a drawn form would post, including the CSRF
token and the signed list. The output is real:

```php
use Laf\Exception\FormValidationException;
use Laf\UI\Form\DrawMode;
use MyApp\Employee;

$employee = new Employee();
$form = $employee->getForm(DrawMode::INSERT);
$request = $form->buildRequest([
    'company_id' => 3,
    'first_name' => 'Grace',
    'last_name' => 'Hopper',
    'email' => 'rel-grace@example.com',
    'employee_project' => [4, 7],          // a relation name takes a list of keys
]);
```

```text
[form_submit] => employee
[employee] => 1
[employee_draw_mode] => insert
[pbzcnal_vq] => 3
[svefg_anzr] => Grace
[ynfg_anzr] => Hopper
[rznvy] => rel-grace@example.com
[employee_project] => [4, 7]
[employee_fields] => pbzcnal_vq,svefg_anzr,ynfg_anzr,rznvy,_rel_employee_project|8d9b965f...
[_csrf_token_employee] => 06648e56...
```

```php
$id = $form->setRequest($request)->processForm();      // 17
// employee_project now holds (17, 4) and (17, 7)
$form->getRelationElement('employee_project')->getSelectedIds();   // ['4', '7']

// Update only the links
$employee = new Employee($id);
$form = $employee->getForm(DrawMode::UPDATE);
$form->setRequest($form->buildRequest(['employee_project' => [7, 5]]))->processForm();
$employee->manyToMany('employee_project')->ids();       // ['5', '7']

// Validation
$form = (new Employee($id))->getForm(DrawMode::UPDATE);
$form->getRelationElement('employee_project')->setMaxItems(1);
try {
    $form->setRequest($form->buildRequest(['employee_project' => [7, 999999]]))->processForm();
} catch (FormValidationException $ex) {
    $ex->getErrors();
    // ['employee_project' => ['Projects: choose at most 1', 'Projects: unknown value']]
}

// A request without the picker leaves the links alone
$form = (new Employee($id))->getForm(DrawMode::UPDATE);
$form->setRequest($form->buildRequest(['notes' => 'changed notes only']))->processForm();
(new Employee($id))->manyToMany('employee_project')->ids();   // still ['5', '7']
```

A page does the same with the real request. For the full create/edit page pattern see [06-forms.md](06-forms.md):

```php
$form = $employee->getForm($employee->isLoaded() ? DrawMode::UPDATE : DrawMode::INSERT);
$form->handleLookupRequest();
if ($form->isSubmitted()) {
    try {
        $id = $form->processForm();
        UrlParser::redirectToViewPage($id);
    } catch (FormValidationException $ex) {
        // the redrawn form shows the errors and keeps the chosen projects
    }
}
echo $form->draw();
```

## Browser side: markup, events and `LafAutocomplete`

All three autocomplete flavours (single, chips, dual list) share one vanilla script, `autocomplete.js`. It has no
dependencies. It needs Bootstrap 5 **CSS** for the dropdown and badges, but not Bootstrap's JavaScript. The first
autocomplete drawn in a request prints the script inline, once. For a Content-Security-Policy without inline
scripts, turn that off and serve the file yourself:

```php
use Laf\UI\Form\Input\Autocomplete;

Autocomplete::setInlineScript(false);           // process-wide, before drawing
copy(Autocomplete::getScriptPath(), __DIR__ . '/assets/laf-autocomplete.js');   // or serve getScript()
```

```html
<script src="assets/laf-autocomplete.js" defer></script>
```

The script binds delegated listeners on `document`, so autocompletes added later (modals, tabs loaded on demand)
work without initialisation. Loading it twice does nothing (`window.LafAutocomplete` guards it). The checkbox picker
needs no script.

Keyboard and mouse behaviour:

- Typing searches after 250 ms (`LafAutocomplete.debounceMs`) once `data-min-chars` characters are typed. A min of
  0 searches on focus.
- ArrowDown / ArrowUp move through the list (they open it when closed). Enter picks the active item, or the only
  item. Enter never submits the form while the list is open or the typed text was not confirmed. Escape closes the
  list and restores the label.
- On blur, typed text that was not chosen reverts to the chosen label. Emptying the box of a single autocomplete
  clears the key.
- Chips: Backspace in the empty box removes the last chip, and × removes one chip. When `data-max-items` is
  reached, no more chips are added.
- The create entry `+ Create "…"` appears when no result label equals the text (case-insensitive). A refusal is
  shown as a red row in the list (`.laf-autocomplete-error`), or under the dual list (`.laf-duallist-error`).

### Events

All are dispatched with `bubbles: true`, so you can listen on `document` or on a form.

| Event | Target | `detail` | When |
|---|---|---|---|
| `change` | the hidden `.laf-autocomplete-value` input | – | single value: the key changed |
| `laf:autocomplete:select` | the `.laf-autocomplete` box | `{value, label}` (value `''` when cleared) | single value: chosen or cleared |
| `laf:autocomplete:add` | the box (chips) or `.laf-duallist` | `{value, label}` | a value was added (one event per moved option in the dual list) |
| `laf:autocomplete:remove` | same | `{value, label}` | a value was removed |
| `change` | the chips box or `.laf-duallist` | – | several values: the set changed |
| `laf:autocomplete:create` | the box or `.laf-duallist` | `{value, label}` | a row was created on the server (fires after the select/add events) |

```js
document.addEventListener('laf:autocomplete:select', function (e) {
    if (e.target.getAttribute('data-laf-autocomplete') === 'employee.company_id') {
        console.log('company is now', e.detail.value, e.detail.label);
    }
});

document.addEventListener('laf:autocomplete:add', function (e) {
    console.log('project added', e.detail.value, e.detail.label);
});
```

### `window.LafAutocomplete`

`box` is the `.laf-autocomplete` element (or `.laf-duallist` for `values()`).

| Method | Single value | Chips | Dual list |
|---|---|---|---|
| `set(box, value, label)` | sets the key and the shown label, fires `change` + `select` | adds a chip | not supported |
| `clear(box)` | clears the key | removes every chip | not supported |
| `add(box, value, label)` | no-op | adds a chip (respects max items) | not supported |
| `remove(box, value)` | no-op | removes the chip of that value | not supported |
| `values(box)` | `[key]` or `[]` | the chip keys | the selected options' keys |
| `debounceMs` | 250 (read-only constant) | | |

```js
var box = document.querySelector('[data-laf-autocomplete="employee_project.project_id"]');
LafAutocomplete.add(box, '7', 'Rel Voyager');
LafAutocomplete.values(box);   // ['5', '6', '7']
```

> **Note:** Labels are always inserted as text (`textContent`), never as HTML, both in the list and in the chips.
> The server escapes labels it draws (`Util::e()` / `Util::attr()`). A label such as `<b>Bold</b>` therefore shows
> literally.

## The demo page

[`sample_files/autocomplete_demo.php`](../sample_files/autocomplete_demo.php) runs against the LAF test database
(the integration suite creates its tables). It shows a person form with groups through the linker
`m2m_person_group`, as checkboxes (default), as chips (`?style=chips`, with create and min chars 0), and as two lists
(`?style=dual`, with create). `?demo=company` shows a single foreign key as an `Autocomplete` searching `code` and
`name`, with create. A line under the form prints the last `laf:autocomplete:*` event. Open it through the local web
server at `http://localhost/laf/sample_files/autocomplete_demo.php`.

## Reference

### `Laf\Database\ManyToMany`

| Method | What it does | Notes |
|---|---|---|
| `__construct(BaseObject $owner, string $junction, ?array $definition = null)` | the relation of a row | null definition = the owner table's; `InvalidArgumentException` without one or without `owner`/`other` |
| `getName()` | the linker table name | also the posted name and the error key |
| `getOwner()` | the row | |
| `getOwnerColumn()` / `getOtherColumn()` | linker columns (`employee_id` / `project_id`) | |
| `getDefinition()` | the definition array | `form` always an array |
| `getLabel()` / `getHint()` / `isRequired()` | from the definition | label falls back to the table name |
| `getFormOption($key, $default = null)` | one layout hint | order, group, tab, exclude, readonly, hidden, element, create |
| `getJunctionClass()` | `\MyApp\EmployeeProject` | |
| `isAvailable()` | linker model exists and both columns are FKs on it | |
| `junctionMeta()` / `otherMeta()` | `Table` metadata | `LogicException` when unavailable |
| `getOtherKeyColumn()` / `getOtherDisplayColumn()` / `getOtherClass()` | `id` / `name` / `\MyApp\Project` | |
| `getOwnerKey()` | the owner's key, null while not stored | |
| `ids()` | linked keys (strings) | cached until `sync()` |
| `objects()` | linked rows as models | ordered by display field |
| `labels(array $ids)` | key => label in the given order | unknown keys dropped |
| `options()` | every row of the other table, key => label | ordered by label |
| `validIds(array $ids)` | the keys that exist | cleaned, given order |
| `countOptions()` | rows of the other table | once per request |
| `sync(array $ids)` | link exactly these keys | returns `['added' => [...], 'removed' => [...]]`; transaction; `LogicException` for an unstored owner |
| `static diff(array $current, array $wanted)` | what `sync()` would change | |
| `static cleanIds(array $ids)` | trimmed non-empty strings, no duplicates | non-scalars dropped, `true` → `'1'` |
| `static resetRequestState()` | forget row counts | tests, workers |
| `getFormElement(?string $class = null)` | the picker, cached | class, else definition `element`, else checkboxes / chips by threshold |
| `hasFormElement()` | picker built yet | |
| `static resolveElementClass(string $name)` | short name → picker class | `InvalidArgumentException` |
| `static namesOf(Table $table)` | relation names of a table | |
| `getLayoutModes(string $key)` | internal, used by FormLayout | |

`BaseObject::manyToMany(string $junction): ManyToMany` is cached per object.
`Table::addManyToMany()`, `hasManyToMany()`, `getManyToManyDefinition()`, `getManyToManyDefinitions()` and
`removeManyToMany()` manage the definitions.

### `Laf\UI\Form\Input\Autocomplete` (extends `Select`)

| Member | What it does |
|---|---|
| `REQUEST_KEY` / `REQUEST_TERM` / `REQUEST_CREATE` | `laf_lookup` / `laf_q` / `laf_create` |
| `MAX_TERM_LENGTH` | 100 |
| `setMinChars()` / `getMinChars()` | default 1, 0 = on focus |
| `setLimit()` / `getLimit()` | default 20, 1–100 |
| `setSearchColumns()` / `getSearchColumns()` | default: display field |
| `setLookupCallback()` / `setLabelCallback()` | custom source |
| `setLookupUrl()` / `getLookupUrl()` | default: current URL |
| `setNoResultsText()` / `getNoResultsText()` | default `No matches` |
| `setAllowCreate()` / `isCreateAllowed()` | `isCreateAllowed()` is also true with a create callback |
| `setCreateText()` / `getCreateText()` | default `Create "%s"` |
| `setCreateCallback()` | custom create |
| `setCreateCsrfToken(?string $inputName, ?string $token)` | set by `Form` when it draws |
| `getLookupKey()` | `table.field` (`linker.column` for chips/dual list) |
| `search(string $term, ?int $limit = null)` | `['results' => [...], 'more' => bool]` |
| `create(string $term)` | `['value', 'label', 'created']`; `InvalidValueException` with the user message |
| `getOptions()` | only static options (an autocomplete never loads the whole table) |
| `static setInlineScript(bool)` / `getScriptPath()` / `getScript()` | the script |
| `static suitsField(Field $field)` | the threshold rule |
| `static resetRequestState()` | forget "script printed" and row counts |

### Pickers (`RelationElementInterface`, `RelationElementTrait`)

| Method | What it does |
|---|---|
| `setRelation(ManyToMany $relation)` | binds name, id (`m2m_{name}`), label, hint, required and the current `ids()` |
| `getRelation()` / `getRelationName()` | |
| `getSelectedIds()` / `setSelectedIds(array $ids)` | chosen keys (the value is the keys joined with commas) |
| `getMaxItems()` / `setMaxItems(?int)` | null or ≤ 0 = no limit |
| `getLabel()` / `isRequired()` | |

`getField()` returns null for pickers, because they are not bound to a column. `ManyToManyCheckboxes` adds
`getOptions()` (the other table's rows unless `setOptions()`) and `clearOptionsCache()`. `ManyToManyDualList` adds
`setTexts()`, `getTexts()` and `getOptions()`.

### `Form` methods for relations

| Method | What it does |
|---|---|
| `handleLookupRequest(?array $query = null, bool $terminate = true, ?array $post = null): bool` | answers searches and creates |
| `getLookupStatus(): ?int` | the HTTP status of the last answer |
| `getRelationElements()` / `getRelationElement(string $name)` | the pickers on the form |
| `buildRequest(array $values, ?string $drawMode = null, bool $withCsrfToken = true)` | relation names take a list of keys |
| `addError($name, $message)` | also accepts a relation name |

## See also

- [02-code-generation.md](02-code-generation.md): linker-table detection, `addManyToMany()` output, column comments
- [03-models.md](03-models.md): `ForeignKey`, `Table`, `relatedObject()`, transactions, hooks
- [05-validation.md](05-validation.md): `setValidateOnStore()` (used by create), validation messages
- [06-forms.md](06-forms.md): `getForm()`, `FormLayout`, `processForm()`, CSRF, signed field list, `buildRequest()`
- [07-form-inputs.md](07-form-inputs.md): `Select`, `Radio`, `CheckboxCollection` and the other inputs
- [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md): generated pages, junction tabs
- [14-audit-log.md](14-audit-log.md): links written by `sync()` are audited
- [17-security.md](17-security.md): CSRF on creates, escaping of labels
