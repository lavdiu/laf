# Forms

A LAF form is bound to one model object. `$employee->getForm($drawMode)` builds a `Laf\UI\Form\Form` whose
components are the input elements of the object's fields, laid out by a `FormLayout`; drawing it gives Bootstrap 5
markup, and `processForm()` reads the submitted request back into the object, validates it and inserts or updates
the row. The same form object handles the read-only view, the "new" form and the "edit" form, protects itself with a
per-session CSRF token and a signed list of the fields it drew, and can be fed a request array directly for tests,
imports and API handlers. This chapter covers the form as a whole; the individual inputs are in
[07-form-inputs.md](07-form-inputs.md) and the foreign-key autocomplete and many-to-many pickers in
[08-relations.md](08-relations.md).

On this page:

- [Drawing a form](#drawing-a-form)
- [Draw modes](#draw-modes)
- [The request contract (what the browser posts)](#the-request-contract-what-the-browser-posts)
- [Laying out the form: FormLayout](#laying-out-the-form-formlayout)
- [Layout and hooks on the model](#layout-and-hooks-on-the-model)
- [Picking fields by hand](#picking-fields-by-hand)
- [Capturing a submission and storing it](#capturing-a-submission-and-storing-it)
- [Validation errors](#validation-errors)
- [CSRF protection](#csrf-protection)
- [The mass-assignment guard](#the-mass-assignment-guard)
- [Submitting from code: buildRequest() and setRequest()](#submitting-from-code-buildrequest-and-setrequest)
- [Footer buttons](#footer-buttons)
- [A complete create / edit / view page](#a-complete-create--edit--view-page)
- [Form reference](#form-reference)
- [FormLayout reference](#formlayout-reference)
- [See also](#see-also)

All examples use the shared schema (`company`, `employee`, `project`, `employee_project`) and the generated
`MyApp\*` classes from [`examples/generated/`](examples/generated/). The HTML excerpts below are real output, only
shortened (`...`) and re-indented.

## Drawing a form

```php
use MyApp\Employee;
use Laf\UI\Form\DrawMode;

$employee = new Employee(2);                  // loads row 2
$form = $employee->getForm(DrawMode::UPDATE); // laid out by the model
echo $form->draw();                           // or: echo $form;
```

`BaseObject::getForm(?string $drawMode = null, ?FormLayout $layout = null)` does four things:

1. creates `new Form($this)` and puts every field's element into it (`getAllFieldsAsFormElements()`),
2. sets `setHasFiles(true)` when a field points at the document table (multipart encoding),
3. when a draw mode is given, applies a layout: the `$layout` argument, else the model's `getFormLayout()`, else
   `FormLayout::auto($table)` (see [below](#laying-out-the-form-formlayout)),
4. calls the model's `configureForm(Form $form)` hook.

`getForm()` without a draw mode returns every field flat, in table order, with no layout and no draw mode (it draws in
view mode). Older pages use it and lay out the components themselves; new code should pass the draw mode.

The form's id and name are the table name (`employee`); every field element is identified by the **rot13** of its
column name: `first_name` is posted as `svefg_anzr`, `company_id` as `pbzcnal_vq`. The ids of the generated markup
follow the same names (`svefg_anzr_container`, `svefg_anzr_label`, `svefg_anzr_feedback`, ...), which is what an
application's JavaScript selects on.

> **Note:** rot13 is not a security measure, only an old naming convention kept for the apps. Security comes from the
> CSRF token and the signed field list described below.

## Draw modes

`Laf\UI\Form\DrawMode` holds three string constants plus two lists:

| Constant | Value | Meaning |
|---|---|---|
| `DrawMode::VIEW` | `'view'` | read-only presentation, no `<form>` tag |
| `DrawMode::INSERT` | `'insert'` | a `<form>` for a new record |
| `DrawMode::UPDATE` | `'update'` | a `<form>` for an existing record |
| `DrawMode::ALL` | all three | |
| `DrawMode::EDIT` | `[INSERT, UPDATE]` | the modes that post |

`DrawMode::isValid($mode)` checks a value (for example one taken from the URL).

The form hands its draw mode to every component that has none of its own; an element whose mode was set explicitly
keeps it. That is how a layout makes a field read-only inside an edit form: the element is set to `DrawMode::VIEW`
and draws as a value box while the rest of the form is editable.

### VIEW

```php
echo (new Employee(2))->getForm(DrawMode::VIEW);
```

```html
<div class='form formview py-2' id='employee_external_container'><div style='' class='Laf-UI-Container-TabContainer'>
  <ul class='nav nav-tabs' id='employee_form_tabs_tab_links' role='tablist'>
    ... General, Notes, Details ...
  </ul>
  ...
  <div id='pbzcnal_vq_container' style='min-width:0;' class='mb-3'>
    <label id='pbzcnal_vq_label' for='pbzcnal_vq' class='col-form-label'>Company :</label>
    <div id='pbzcnal_vq' class='Laf-UI-Form-Input-Select form-control-plaintext border border-secondary border-1 bg-light rounded p-2'
         style='max-width:100%;overflow-wrap:break-word;word-break:break-all;'>Forms Chapter Ltd</div>
  </div>
  ...
  <div id='ovegu_qngr_container' style='min-width:0;' class='mb-3'>
    <label id='ovegu_qngr_label' for='ovegu_qngr' class='col-form-label'>Birth Date :</label>
    <div id='ovegu_qngr' class='Laf-UI-Form-Input-Date form-control-plaintext ...'>December 10, 1985</div>
  </div>
  ...
  <div id='abgrf' class='Laf-UI-Form-Input-TextArea form-control-plaintext ...'>Likes &lt;b&gt;maths&lt;/b&gt;<br />
and engines</div>
```

View mode shows *display values*: the foreign key's label (`Forms Chapter Ltd`, not `2`), the date in the locale
format, `Yes`/`No` for a boolean. Text is escaped and line breaks become `<br />` (a field with
`Field::setAllowHtml(true)` is printed raw). The auto layout adds a "Details" tab with `created_by`, `created_on`,
`updated_by`, `updated_on` in view mode only.

### INSERT and UPDATE

```php
echo (new Employee(2))->getForm(DrawMode::UPDATE);
```

```html
<div class='form formupdate py-2' id='employee_external_container'>
    <form action='' method='POST' id='employee_id' class='Laf-UI-Form-Form' style=''
          name='employee_name' autocomplete='off'>
      <div style='' class='Laf-UI-Container-TabContainer'> ... General, Notes ...
        <div style='' class='row Laf-UI-Container-Div'>
          <div style='' class='col-lg Laf-UI-Container-Div'>
            <div class='d-none'>
              <input class='Laf-UI-Form-Input-Hidden' style='' type="hidden" name="vq" id="vq" value="2" />
            </div>
            <div id='pbzcnal_vq_container' style='' class='mb-3'>
              <label id='pbzcnal_vq_label' for='pbzcnal_vq' class='col-form-label'>Company :</label>
              <select class='Laf-UI-Form-Input-Select form-control form-select' style='' name="pbzcnal_vq" id="pbzcnal_vq">
                <option value=''>&nbsp;</option>
                <option value='2' selected='selected'>Forms Chapter Ltd</option>
                <option value='3'>Rel Acme Ltd</option>
              </select>
            </div>
            <div id='svefg_anzr_container' style='' class='mb-3'>
              <label id='svefg_anzr_label' for='svefg_anzr' class='col-form-label'>First Name* :</label>
              <input class='Laf-UI-Form-Input-Text form-control' style='' type="text" name="svefg_anzr" id="svefg_anzr"
                     value="Ada" maxlength="50" required="required" placeholder="First Name" />
            </div>
            ...
            <div id='vf_npgvir_container' style='' class='mb-3'>
              <label id='vf_npgvir_label' for='vf_npgvir' class='col-form-label'>Active :</label>
              <div class='form-check'>
                <input type='hidden' name='vf_npgvir' value='0' />
                <input class='Laf-UI-Form-Input-Checkbox form-check-input' style='' type="checkbox"
                       name="vf_npgvir" id="vf_npgvir" value="1" checked="checked" />
              </div>
            </div>
            <div id='m2m_employee_project_container' style='' class='mb-3'>
              <label ...>Projects :</label>
              <div id='m2m_employee_project' class='laf-choice-list'>
                <input type='hidden' name='employee_project[]' value='' /> ... one checkbox per project ...
```

...followed by the hidden inputs and the footer:

```html
<input type='hidden' name='employee_draw_mode' id='employee_draw_mode' value='update' />
<input type='hidden' name='employee_fields' value='vq,pbzcnal_vq,svefg_anzr,ynfg_anzr,rznvy,ovegu_qngr,fnynel,vf_npgvir,abgrf,_rel_employee_project|d317e0...' />
<input type='hidden' name='_csrf_token_employee' value='f8c86c06...' />
<div class='row '> <!-- footer -->
    <div class='col text-end'>
        ... <button type="submit" name="employee_submit" class='btn btn-md btn-success Laf-UI-Form-Control-SubmitButton' style=''>Submit</button> ...
        <input type='hidden' name='form_submit' id='employee_form_submit' value='employee'/>
        <input type='hidden' name='employee' id='employee_marker' value='1' />
    </div>
</div> <!-- footer -->
</form>
</div>
```

INSERT draws the same markup for an empty object, with `value='insert'` in the draw-mode input. The `created_*` and
`updated_*` columns are left out of both edit modes (BaseObject fills them), and the many-to-many picker for the
`employee_project` linker table appears after the columns (see [08-relations.md](08-relations.md)).

The form's `action` defaults to the current `$_SERVER['REQUEST_URI']` (empty on the CLI); `new Form($obj, $action)` or
`setAction()` sets another URL. `autocomplete='off'` is the form default (`setAutocomplete(Form::AUTOCOMPLETE_ON)`),
and `setValidate(false)` adds `novalidate` to switch off the browser's own checks.

## The request contract (what the browser posts)

| Posted name | Value | Used by |
|---|---|---|
| `form_submit` | the table name (`employee`) | `isSubmitted()` |
| `{table}` (`employee`) | `1` | `detectSubmission()` / `processForm()` |
| `{form}_draw_mode` | `insert` / `update` | decides insert vs update |
| rot13 column names (`svefg_anzr`, ...) | the values as typed | `applySubmittedValues()` |
| `{form}_fields` | `name,name,...|hmac` | the signed list of the editable elements drawn |
| `_csrf_token_{form}` | the session token | CSRF check (POST only) |
| `{linker_table}[]` (`employee_project[]`) | keys of the other table, after a hidden `''` | many-to-many pickers |
| `{form}_submit` | the submit button's name | not read by LAF; tells several submit buttons apart |

A form reads **only the request array of its own method**: a POST form (the default) ignores `$_GET` entirely, and
`setMethod('GET')` makes it read `$_GET` (and then it skips the CSRF check, which is POST only). `setRequest()`
replaces both with arrays you pass (see [below](#submitting-from-code-buildrequest-and-setrequest)).

## Laying out the form: FormLayout

`Laf\UI\Form\FormLayout` describes, per draw mode, which fields appear, in what sections, in how many columns, and
which are excluded, read-only or hidden. It is applied with `$form->applyLayout($drawMode, $layout)`, which
`getForm($mode, $layout)` calls for you.

### The automatic layout

`FormLayout::auto(Table $table, array $options = [])` is what you get when the model has no `getFormLayout()`:

- columns by field count: fewer than 10 fields (columns plus pickers) = 1 column, up to 20 = 2, more = 3;
- the metadata columns (`FormLayout::METADATA_FIELDS` = `created_by`, `created_on`, `record_status_id`, `updated_by`,
  `updated_on`) go into a "Details" tab of the view form;
- `FormLayout::EDIT_EXCLUDED_FIELDS` (`created_on`, `created_by`, `updated_on`, `updated_by`) are excluded from insert
  and update forms; `record_status_id` stays editable there;
- the fields' form options from the JSON column comments: `order`, `group` (a fieldset), `tab`, `exclude`, `readonly`,
  `hidden`, `element` (see [02-code-generation.md](02-code-generation.md)).

`$options` overrides the defaults: `columns` (int), `metadata` (string[]), `editExclude` (string[]), `detailsTab`
(string), `generalTab` (string).

The `employee` schema has `notes` commented `{"form":{"tab":"Notes"}}`, so the auto layout is (from
`json_encode(FormLayout::auto((new Employee())->getTable())->toArray())`, shortened):

```json
{
    "sections": [
        {"kind": "fields", "title": "", "icon": "", "columns": 2, "modes": ["insert", "update"],
         "fields": ["id", "company_id", "first_name", "last_name", "email", "birth_date", "salary", "is_active", "employee_project"]},
        {"kind": "fields", "title": "", "icon": "", "columns": 2, "modes": ["view"],
         "fields": ["id", "company_id", "first_name", "last_name", "email", "birth_date", "salary", "is_active", "employee_project"]},
        {"kind": "tab", "title": "Notes", "icon": "", "fields": ["notes"], "columns": 1, "modes": null},
        {"kind": "tab", "title": "Details", "icon": "", "fields": ["created_by", "created_on", "updated_by", "updated_on"],
         "columns": 2, "modes": ["view"]}
    ],
    "fields": {
        "created_on": {"exclude": ["insert", "update"]},
        "created_by": {"exclude": ["insert", "update"]},
        "updated_on": {"exclude": ["insert", "update"]},
        "updated_by": {"exclude": ["insert", "update"]}
    },
    "generalTab": "General",
    "tabContainerId": null,
    "appendUnlisted": true
}
```

### Building a layout

```php
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormLayout;

$layout = FormLayout::create()
    ->fieldset('Person', ['first_name', 'last_name', 'birth_date'], 3)
    ->fieldset('Employment', ['company_id', 'email', 'salary', 'is_active'], 2)
    ->html('<p class="text-muted">Salaries are gross, per month.</p>', DrawMode::EDIT)
    ->tab('Notes', ['notes'])
    ->readOnly(['email'], [DrawMode::UPDATE])  // editable when creating, fixed afterwards
    ->hidden(['id'])
    ->exclude(['created_on', 'created_by', 'updated_on', 'updated_by', 'employee_project'], DrawMode::EDIT)
    ->element('salary', 'Number');             // <input type="number"> instead of the default text box

$form = (new Employee(2))->getForm(DrawMode::UPDATE, $layout);
```

What it draws (excerpt):

```html
<fieldset style='' class='Laf-UI-Container-Fieldset'>
    <legend>Person</legend>
    <div style='' class='row Laf-UI-Container-Div'>
        <div style='' class='col-lg Laf-UI-Container-Div'> ... First Name ... </div>
        <div style='' class='col-lg Laf-UI-Container-Div'> ... Last Name ... </div>
        <div style='' class='col-lg Laf-UI-Container-Div'> ... Birth Date ... </div>
    </div>
</fieldset>
<fieldset style='' class='Laf-UI-Container-Fieldset'>
    <legend>Employment</legend>
    ...
    <div id='rznvy_container' style='min-width:0;' class='mb-3'>
        <label id='rznvy_label' for='rznvy' class='col-form-label'>E-mail* :</label>
        <div id='rznvy' class='Laf-UI-Form-Input-Text form-control-plaintext ...'>ada.forms@example.com</div>
    </div>
    ...
    <input class='Laf-UI-Form-Input-Number form-control' style='' type="number" name="fnynel" id="fnynel"
           value="4200.50" placeholder="Salary" inputmode="decimal" step="0.01" />
    ...
</fieldset>
<p class="text-muted">Salaries are gross, per month.</p>
<div class='d-none'> <input class='Laf-UI-Form-Input-Hidden' ... name="vq" id="vq" value="2" /> </div>
```

and the signed field list no longer contains `rznvy` (email), because a read-only element posts nothing:

```html
<input type='hidden' name='employee_fields' value='svefg_anzr,ynfg_anzr,ovegu_qngr,pbzcnal_vq,fnynel,vf_npgvir,vq,abgrf|c45e51...' />
```

The rules `apply()` follows:

- **Sections** are drawn in the order added. `fields()` is a plain block, `fieldset()` a `<fieldset>` with a legend,
  `tab()` a tab, `html()` a piece of raw HTML. Each section can be limited to some draw modes (`$modes`, null = all).
- A field listed in several sections is drawn in the **first** section active in the mode.
- Fields in no section are **appended** after the sections (then the many-to-many pickers), unless
  `setAppendUnlisted(false)`. In the example, `id` was appended (and hidden).
- As soon as one active section is a tab, everything that is not in a tab becomes a leading "General" tab
  (`setGeneralTabTitle()`), and the form holds one `TabContainer` whose id is `{table}_form_tabs`
  (`setTabContainerId()`).
- Columns are Bootstrap `col-lg` divs inside a `row` div. A form with any multi-column section is drawn in the
  `FormRowDisplayMode::INLINE` style (label above the control); otherwise `ROW` (label left, `col-sm-2`/`col-sm-10`).
- `exclude($names, $modes = all)`: the field is not on the form in those modes and is not processed.
- `readOnly($names, $modes = insert+update)`: the element is drawn in VIEW mode inside the edit form. It posts nothing,
  is not in the signed field list, and `processForm()` leaves it untouched.
- `hidden($names, $modes = all)`: only the wrapper is hidden (`d-none`). **The value still posts and is applied.**
- `element($name, $class)`: the input class of a field (short name under `Laf\UI\Form\Input`, a many-to-many picker
  name without its `ManyToMany` prefix such as `DualList`, or a fully qualified class). See
  [07-form-inputs.md](07-form-inputs.md#choosing-the-element-of-a-field).

> **Note:** `hidden()` is a presentation option, not protection. A user can still post a value for a hidden field.
> To keep a field out of a submission use `exclude()` or `readOnly()`.

> **Note:** `html()` sections are raw HTML (not escaped). Never put user input in them without `Util::e()`.

> **Note:** the layout owns the draw mode and the hidden state of the elements it places: `apply()` resets them. Tweak
> elements (suffixes, options, `setDisabled()`) **after** `getForm($mode)`, typically in `configureForm()`.

### Serialising a layout

`toArray()` gives the shape above and `FormLayout::fromArray($data)` reads it back, so a layout can live in a JSON or
PHP config file. In `fromArray()` a mode option may be `true` (meaning its default modes), and an unknown section kind,
draw mode, option or element class throws `\InvalidArgumentException`:

```php
$layout = FormLayout::fromArray([
    'sections' => [
        ['kind' => 'fieldset', 'title' => 'Person', 'fields' => ['first_name', 'last_name'], 'columns' => 2],
        ['kind' => 'tab', 'title' => 'Notes', 'fields' => ['notes']],
    ],
    'fields' => [
        'email' => ['readonly' => ['update']],
        'id' => ['hidden' => true],
        'salary' => ['element' => 'Number'],
    ],
]);
```

## Layout and hooks on the model

Put the layout in the leaf model class (never in `Base/`, which is regenerated), so every page that calls
`getForm($mode)` gets it. The generated `Employee.php` ships with a commented-out example. `configureForm()` runs at the
end of every `getForm()`, after the layout, so it is the place for element tweaks every form of this model needs:

```php
namespace MyApp;

use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Form;
use Laf\UI\Form\FormLayout;

class Employee extends Base\BaseEmployee
{
    public function getFormLayout(): ?FormLayout
    {
        return FormLayout::auto($this->getTable())
            ->readOnly(['email'], [DrawMode::UPDATE])
            ->element('salary', 'Number');
    }

    protected function configureForm(Form $form): void
    {
        $form->setSubmitButtonLabel('Save employee');
        $salary = $form->getFieldElement('salary'); // null when the field is not on this form
        if ($salary !== null) {
            $salary->setSuffix('EUR');
        }
    }
}
```

With that model, `getForm(DrawMode::UPDATE)->getEditableFieldNames()` returns
`id, company_id, first_name, last_name, birth_date, salary, is_active, notes` (no `email`), and the salary element is
a `Laf\UI\Form\Input\Number`.

> **Note:** elements are cached on the Field (one element per field per object), so the form, the generated
> `getXxxFormElement()` getters and a second `getForm()` on the same object all share the same element instances.
> That is what makes values and errors survive between processing and drawing.

## Picking fields by hand

Pages that do not want a layout can set the components themselves; the generated `.page` files show this as a
comment:

```php
$form = $employee->getForm(DrawMode::UPDATE);
$form->setComponents([])
    ->addComponent($employee->getIdFormElement())
    ->addComponent($employee->getFirstNameFormElement())
    ->addComponent($employee->getLastNameFormElement())
    ->addComponent($employee->getEmailFormElement());
```

Use `Laf\UI\Container\Div(['row'])` and `Div(['col-lg'])` for columns. Containers are searched recursively, so
`getFieldElement()`, validation and the signed field list keep working through them.

Two older helpers remain for existing pages: `addFieldsFromObject(?array $fields = null)` adds the elements of the
given (or all) fields, grouped into fieldsets when `groupFields($label, $fieldNames)` was called, and skipping fields
whose `setFieldVisibilityCondition($name, fn (Form $f): bool => ...)` returns false. Prefer `FormLayout` in new code.

## Capturing a submission and storing it

```php
use Laf\Exception\FormValidationException;
use Laf\Util\UrlParser;

$employee = new Employee(UrlParser::getId());
$form = $employee->getForm(DrawMode::UPDATE);

if ($form->isSubmitted()) {
    try {
        $id = $form->processForm();
        UrlParser::redirectToViewPage($id);
    } catch (FormValidationException $ex) {
        // fall through: the form is drawn again below with the submitted values and the messages
    }
}
echo $form;
```

`isSubmitted()` is true when `form_submit` of the form's method names this table. `processForm(?string
$documentHandlerClass = null)` then runs, in order:

1. `detectSubmission()`: the marker input must be present (else `processForm()` returns `null`); the posted
   `{form}_draw_mode` becomes the form's draw mode.
2. The CSRF check (POST forms with protection on): a missing or wrong token throws `Laf\Exception\CsrfTokenException`.
3. `applySubmittedValues()`: for every field, in order: a value set with `setSubmittedFieldValue()` wins; a document
   field is replaced only when a file was uploaded (through `$documentHandlerClass::upload()`, default
   `Laf\Filesystem\Document`, see [15-files-and-documents.md](15-files-and-documents.md)); a posted value is converted
   with the type's `fromInput()` (locale dates and numbers are parsed); a boolean drawn editable but absent becomes
   false. Values that do not convert become errors, not exceptions. Many-to-many keys are collected.
4. `validate()` (unless `setValidateOnSubmit(false)`): `Field::validate()` on every field that was drawn editable or set
   with `setSubmittedFieldValue()`, the many-to-many checks, and, only when all of that passed, the model's
   `validateRecord()` (see [05-validation.md](05-validation.md)).
5. Any error: `FormValidationException` with every message.
6. `insert()` or `update()`: an object with a primary key value is updated, except a non-auto-increment key in
   INSERT mode, which is inserted. When the form carries many-to-many pickers, the row and every link `sync()` run in
   one `BaseObject::transaction()`. Validation on store is switched off for this write because the form has just run
   the same rules.
7. A write refused for a missing required value or a duplicate unique value becomes a `FormValidationException` with the
   message on the field (`getPrevious()` is the original `MissingFieldValueException` /
   `UniqueFieldDuplicateValueException`). Other database errors propagate.
8. The object is `reload()`ed (database defaults, metadata) and the record id is returned.

The values end up in the object, and because each field mirrors its value into its cached element, a form drawn
after a failed submission shows what the user typed.

Example run (an insert through `buildRequest()`, see [below](#submitting-from-code-buildrequest-and-setrequest)):

```php
$employee = new Employee();
$form = $employee->getForm(DrawMode::INSERT);
$id = $form->setRequest($form->buildRequest([
    'company_id' => 2,
    'first_name' => 'Grace',
    'last_name'  => 'Hopper',
    'email'      => 'grace.forms@example.com',
    'birth_date' => 'December 9, 1986',     // the locale display format is parsed
    'salary'     => '5100.00',
    'is_active'  => '1',
    'employee_project' => [2],              // many-to-many picker: keys of `project`
], DrawMode::INSERT))->processForm();

$saved = new Employee($id);
var_dump($id, $saved->getBirthDateVal(), $saved->getIsActiveVal(), $saved->getCreatedByVal());
print_r($saved->manyToMany('employee_project')->ids());
```

```text
int(9)
string(10) "1986-12-09"
bool(true)
int(1)
Array ( [0] => 2 )
```

An update posts only what changed and the object writes only changed columns:

```php
$form = (new Employee(2))->getForm(DrawMode::UPDATE);
$form->setRequest($form->buildRequest(['salary' => '4300.00'], DrawMode::UPDATE))->processForm(); // returns 2
```

`is_active` was not in that request's field list, so it stayed as it was (true). A real browser always posts the
checkbox (the hidden `0` before the box), so unticking it in the drawn form stores false.

## Validation errors

`FormValidationException` extends `Laf\Exception\ValidationException`, so one `catch (ValidationException $ex)` covers
both forms and direct `store()` calls. `$ex->getErrors()` and `$form->getErrors()` are `field name => string[]`;
messages that belong to the record as a whole are under `Form::FORM_ERROR_KEY` (`'_form'`).

```php
$form = (new Employee())->getForm(DrawMode::INSERT);
try {
    $form->setRequest($form->buildRequest([
        'first_name' => '', 'last_name' => 'X', 'email' => 'grace.forms@example.com', 'birth_date' => 'not a date',
    ], DrawMode::INSERT))->processForm();
} catch (FormValidationException $ex) {
    echo $ex->getMessage(), "\n";
    print_r($ex->getErrors());
}
```

```text
Birth Date: invalid value; First Name is required
Array
(
    [birth_date] => Array ( [0] => Birth Date: invalid value )
    [first_name] => Array ( [0] => First Name is required )
)
```

The duplicate e-mail is not reported here because the write was never attempted. Once the fields are valid, the
unique index refuses it:

```text
Array ( [email] => Array ( [0] => E-mail: this value is already in use ) )
// $ex->getPrevious() is a Laf\Exception\UniqueFieldDuplicateValueException
```

`validateRecord()` messages arrive the same way, a record-level message (key `''` or
`ValidationException::RECORD_KEY`) under `_form`:

```php
protected function validateRecord(): array
{
    $errors = [];
    if ($this->getSalaryVal() !== null && $this->getIsActiveVal() === false) {
        $errors['salary'] = 'Inactive employees have no salary';
    }
    if ($this->getFirstNameVal() === $this->getLastNameVal()) {
        $errors[''] = 'First and last name must differ';
    }
    return $errors;
}
// processForm() -> ['salary' => ['Inactive employees have no salary'], '_form' => ['First and last name must differ']]
```

### How the redrawn form shows errors

Drawing the form after the exception gives an alert above the fields and per-field feedback:

```html
<div id='employee_errors' class='alert alert-danger laf-form-errors' role='alert'>
    <ul class='mb-0'>
        <li>First Name is required</li>
    </ul>
</div>
...
<div id='svefg_anzr_container' style='' class='mb-3'>
    <label id='svefg_anzr_label' for='svefg_anzr' class='col-form-label'>First Name* :</label>
    <input class='Laf-UI-Form-Input-Text form-control is-invalid' style='' type="text" name="svefg_anzr" id="svefg_anzr"
           maxlength="50" required="required" placeholder="First Name" aria-describedby='svefg_anzr_feedback' aria-invalid='true' />
    <div id='svefg_anzr_feedback' class='invalid-feedback d-block'>First Name is required</div>
</div>
```

Messages are escaped. `setShowErrorSummary(false)` removes the alert; `drawErrorSummary()` returns it so a page can put
it elsewhere (empty string when there are no errors). A value that could not be converted (the "not a date" above) is
shown back as typed.

You can add your own messages before drawing, for example after a business check of your own:

```php
$form->addError('salary', 'Salary needs approval above 10,000');
$form->addError(Form::FORM_ERROR_KEY, 'Saving is disabled during the audit');
```

`setErrors($errors)` replaces all messages, `clearErrors()` removes them, `getErrorMessages()` lists them flat,
`hasErrors()` tells whether there are any.

> **Note:** messages live on the elements and are drawn in edit modes. A message for a field that is not on the form
> (excluded) only appears in the summary. Messages on fields in an inactive tab are not visible until the tab is
> opened; the summary at the top is what tells the user.

## CSRF protection

Every POST form draws a hidden `_csrf_token_{form}` input and `processForm()` refuses a request without the matching
token:

```php
$form = (new Employee())->getForm(DrawMode::INSERT);
$request = $form->buildRequest(['first_name' => 'Eve', 'last_name' => 'Il', 'email' => 'eve.forms@example.com'], DrawMode::INSERT);
$request['_csrf_token_employee'] = 'forged';
try {
    $form->setRequest($request)->processForm();
} catch (\Laf\Exception\CsrfTokenException $ex) {
    echo $ex->getMessage(); // CSRF token validation failed. Please reload the page and try again.
}
```

- There is **one token per session and form name**, created on first use (`bin2hex(random_bytes(32))`) and reused by
  every draw. Several open tabs, the back button and two draws on one page all stay valid.
- The token lives in `$_SESSION['_csrf_token_{form}']`. The form starts the session itself when none is active and
  headers are not sent yet; start your session before any output.
- `getCsrfSessionKey()` is the input / session key, `getCsrfToken()` the current token, `regenerateCsrfToken()` a new
  one (call it after login and logout).
- `setCsrfProtection(false)` switches the check off for one form (for example an API endpoint with its own
  authentication); GET forms are never checked.

Links that write, such as delete links, carry the same token as a query parameter:

```php
echo $form->withCsrfToken('?module=employee&action=delete&id=7');
// ?module=employee&action=delete&id=7&_csrf_token_employee=2bfdf68f...

// in the delete action
if (!$form->isValidCsrfToken($_GET[$form->getCsrfSessionKey()] ?? null)) {
    // refuse
}
```

`isValidCsrfToken(?string $token)` compares with `hash_equals()`. The generated pages use exactly this for their
delete links (see [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md)) and the autocomplete
"create new" POST sends the token too (see [08-relations.md](08-relations.md)). The full picture is in
[17-security.md](17-security.md).

## The mass-assignment guard

A drawn form posts `{form}_fields`: the rot13 names of the elements it drew editable (plus `_rel_{linker}` for each
many-to-many picker), signed with an HMAC-SHA256 under a per-session secret (`$_SESSION['_laf_form_key']`) the browser
never sees. The list cannot be forged or moved to another form.

The list is always used to decide **which booleans and pickers were on the form** (an absent checkbox that was drawn
means false; one that was not drawn is left alone) and which fields `validate()` checks. Whether it also **limits which
posted fields are applied** depends on one switch:

```php
$form->setRestrictToRenderedFields(true);          // this form
\Laf\Util\Settings::set('form.restrict_to_rendered_fields', true); // every form (read in the constructor)
```

With the switch on, a posted field outside the list is ignored, and a request without a valid list fails with a
`FORM_ERROR_KEY` message "The form is out of date or was changed; reload the page and try again". Real run, with
`created_by` (`perngrq_ol`) smuggled into an update of employee 2 whose `created_by` is 1:

```php
$form = (new Employee(2))->getForm(DrawMode::UPDATE)->setRestrictToRenderedFields(true);
$request = $form->buildRequest(['salary' => '4500.00'], DrawMode::UPDATE);
$request['perngrq_ol'] = '999';
$form->setRequest($request)->processForm();
// created_by is still 1, salary is 4500.00

// the same request with the switch off (the default):
// created_by becomes 999
```

> **Note:** the guard is **off by default** for backwards compatibility. With it off, any column of the table posted
> under its rot13 name is applied, including excluded and read-only ones (`created_by`, a status, a price). Turn
> `form.restrict_to_rendered_fields` on in new applications. Values set in code with `setSubmittedFieldValue()` are
> always applied either way.

`getRenderedFieldNames()` lists the fields whose elements are on the form, `getEditableFieldNames()` those that post a
value (not drawn in VIEW mode, not disabled, not excluded). `signedFieldList($keys)` and `getFieldListInputName()` are
public for code that builds requests itself.

### Values set by code

`setSubmittedFieldValue($fieldName, $value)` (scalar or null, bools become `'1'`/`'0'`) overrides whatever the request
carries, for values the user must not choose:

```php
$form = (new Employee())->getForm(DrawMode::INSERT);
$form->setSubmittedFieldValue('company_id', $currentUser->getCompanyIdVal())
     ->setSubmittedFieldValue('is_active', false);
$id = $form->processForm();
```

`getSubmittedFieldValue($name)` reads it back; `fieldIsSubmitted($name)` tells whether the request carries a field.

## Submitting from code: buildRequest() and setRequest()

`setRequest(?array $data, ?array $files = null)` makes the form read the given arrays instead of `$_POST`/`$_GET` and
`$_FILES` (`null` goes back to the superglobals). `buildRequest(array $values, ?string $drawMode = null, bool
$withCsrfToken = true)` produces an array the form accepts as its own submission: the markers, the draw mode, the
session's CSRF token, the signed list of exactly the fields given, and the values under their rot13 names. Keys that
are linker tables become picker values; unknown keys are passed as given.

```php
$request = $form->buildRequest(['first_name' => 'Grace', 'is_active' => '1', 'employee_project' => [2]], DrawMode::INSERT);
print_r($request);
```

```text
Array
(
    [form_submit] => employee
    [employee] => 1
    [employee_draw_mode] => insert
    [svefg_anzr] => Grace
    [vf_npgvir] => 1
    [employee_project] => Array ( [0] => 2 )
    [employee_fields] => svefg_anzr,vf_npgvir,_rel_employee_project|a91b8c...
    [_csrf_token_employee] => 2bfdf68f...
)
```

Use it for:

- **imports**: one form per row, `processForm()` gives the same conversions, validation and messages as the UI;
- **JSON APIs**: decode the body, `buildRequest($payload, DrawMode::UPDATE)`, catch `FormValidationException` and
  answer 422 with `$ex->getErrors()`;
- **tests**: see [18-testing-and-tooling.md](18-testing-and-tooling.md).

Because the list names only the fields passed, a boolean you leave out is left alone (as for an element that was not
drawn).

> **Note:** `buildRequest()` signs with the current session's secret and copies the session's token, so a request
> built in one session is not valid in another. Pass `$withCsrfToken = false` to build a request without the token
> (for example to test the CSRF refusal).

## Footer buttons

The footer row holds, in order: the reset button (`setResetButton(?Button)`, none by default), the extra controls
added with `addButton()` / `setCancelLink()`, and the submit button (`btn btn-md btn-success`, caption "Submit",
named `{form}_submit`).

```php
use Laf\UI\Form\Control\SubmitButton;

$form->setSubmitButtonLabel('Save employee')
     ->setCancelLink('?module=employee&action=view&id=2');           // label 'Cancel', classes btn btn-outline-secondary
$saveAndNew = (new SubmitButton())->setValue('Save and new')->setName('save_and_new');
$saveAndNew->addCssClasses('btn', 'btn-outline-success');
$form->addButton($saveAndNew);

// after processForm(): which button was pressed?
$next = isset($_POST['save_and_new']) ? UrlParser::getNewLink() : UrlParser::getViewLink($id);
```

```html
<a href="?module=employee&amp;action=view&amp;id=2" style="" class="btn btn-outline-secondary Laf-UI-Component-Link">Cancel</a>
<button type="submit" name="save_and_new" class='btn btn-outline-success Laf-UI-Form-Control-SubmitButton' style=''>Save and new</button>
... <button type="submit" name="employee_submit" class='btn btn-md btn-success Laf-UI-Form-Control-SubmitButton' style=''>Save employee</button>
```

`addButton()` draws a `Button` bare (`setWrapInDiv(false)`); any `ComponentInterface` (a `Link`, a `Dropdown`) works.
`setButtons([...])` replaces the list, `getButtons()` reads it. `hideSubmitButton()` / `showSubmitButton()` toggle the
submit button, `setSubmitButton(Button)` replaces it. The button classes are covered in
[07-form-inputs.md](07-form-inputs.md#buttons).

> **Note:** the default submit button is drawn inside the input wrapper (a `mb-3 row` div with an empty label column),
> unlike the bare buttons from `addButton()`. Call `$form->getSubmitButton()->setWrapInDiv(false)` if that spacing
> gets in the way.

## A complete create / edit / view page

A trimmed version of the generated [`pages/employee.page`](examples/generated/pages/employee.page), as a plain PHP
page. Routing and the page classes are explained in [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md)
and [11-pages-and-ui.md](11-pages-and-ui.md).

```php
<?php
use Laf\Exception\FormValidationException;
use Laf\UI\Component\Alert;
use Laf\UI\Component\Link;
use Laf\UI\Flash;
use Laf\UI\Form\DrawMode;
use Laf\UI\Page\AdminPage;
use Laf\UI\Page\Html;
use Laf\Util\Settings;
use Laf\Util\UrlParser;
use MyApp\Employee;

require __DIR__ . '/../bootstrap.php';
session_start();                                   // before any output: CSRF token and field-list secret
Settings::set('form.restrict_to_rendered_fields', true);

$action = UrlParser::getAction();                  // new | update | view
$employee = new Employee(UrlParser::getId());
$mode = $action === 'new' ? DrawMode::INSERT : ($action === 'update' ? DrawMode::UPDATE : DrawMode::VIEW);
if ($mode !== DrawMode::INSERT && !$employee->recordExists()) {
    Flash::error('Record not found');
    UrlParser::redirectToListPage();
}

$form = $employee->getForm($mode);
$form->handleLookupRequest();                      // answers autocomplete searches (JSON) and exits, see 08-relations.md
$form->setCancelLink($mode === DrawMode::INSERT ? UrlParser::getListLink() : UrlParser::getViewLink());

$page = (new AdminPage())->setTitle('Employee');
foreach (Flash::drain() as $alert) {
    $page->addComponent($alert);
}

if ($form->isSubmitted()) {
    try {
        $id = $form->processForm();
        Flash::success('Employee saved');
        UrlParser::redirectToViewPage($id);        // 302 + exit
    } catch (FormValidationException $ex) {
        $page->addComponent(new Alert('', 'The record was not saved, please correct the fields marked below', Alert::Type_Danger));
    }
}

if ($mode === DrawMode::VIEW) {
    $page->addLink(new Link('Update', UrlParser::getUpdateLink(), 'fa fa-edit', [], ['btn', 'btn-sm', 'btn-outline-warning']));
    $delete = new Link('Delete', $form->withCsrfToken(UrlParser::getDeleteLink()), 'fa fa-trash', [], ['btn', 'btn-sm', 'btn-outline-danger']);
    $delete->setConfirmationMessage('Delete this employee?');
    $page->addLink($delete);
}

$page->addComponent($form);
echo (new Html())->setPageTitle('Employee')->addComponent($page)->draw();
```

The delete action (in the same page or another) checks `$form->isValidCsrfToken($_GET[$form->getCsrfSessionKey()] ??
null)` before deleting, as the generated page does.

## Form reference

`Laf\UI\Form\Form implements ComponentInterface` (also has every `ComponentTrait` method: css classes, attributes,
`addComponent()`, `setComponents()`, `getComponents()`, `setDrawMode()`, ... see [11-pages-and-ui.md](11-pages-and-ui.md)).

| Constant | Value |
|---|---|
| `METHOD_GET` / `METHOD_POST` | `'GET'` / `'POST'` |
| `AUTOCOMPLETE_ON` / `AUTOCOMPLETE_OFF` | `'on'` / `'off'` |
| `FORM_ERROR_KEY` | `'_form'` (record-level messages) |
| `SIGNING_KEY_SESSION_KEY` | `'_laf_form_key'` |
| `RELATION_KEY_PREFIX` | `'_rel_'` (picker entries in the signed list) |

**Building and drawing**

| Method | What it does | Notes |
|---|---|---|
| `__construct(BaseObject $obj, ?string $action = null)` | binds the object, default submit button | reads `form.restrict_to_rendered_fields` |
| `getObject()` / `setObject(BaseObject)` | the bound object | `setObject()` also sets id and name to the table name |
| `applyLayout(string $mode, ?FormLayout $layout = null)` | lays the form out | `$layout` ?? model `getFormLayout()` ?? `FormLayout::auto()` |
| `draw()` / `__toString()` | VIEW: `drawViewMode()`, INSERT/UPDATE: `drawUpdateMode()` | |
| `drawViewMode()` / `drawUpdateMode()` | draw one mode directly | |
| `drawErrorSummary()` | the alert with every message, `''` without errors | messages escaped |
| `setShowErrorSummary(bool)` / `isShowErrorSummary()` | alert above the fields (default true) | |
| `getId()` / `setId()`, `getName()` / `setName()` | form id and name (default: table name) | id `{id}_id`, name `{name}_name` in the markup |
| `getAction()` / `setAction(?string)` | the action URL | empty = `$_SERVER['REQUEST_URI']` |
| `getMethod()` / `setMethod(string)` | `GET` or `POST` (default) | anything but GET means POST |
| `getAutocomplete()` / `setAutocomplete(string)` | the form's `autocomplete` attribute | default `off` |
| `getValidate()` / `setValidate(bool)` | false adds `novalidate` | browser validation only |
| `hasFiles()` / `setHasFiles(bool)` | multipart encoding | also on when a `File` input is on the form |
| `getFormRowDisplayMode()` / `setFormRowDisplayMode(string)` | `FormRowDisplayMode::ROW` (`'row'`) or `INLINE` (`''`) | the layout sets it |
| `getComponentCssControlClass()` | `Laf-UI-Form-Form` | |
| `getFieldElement(string $name)` | the element of a field on the form, or null | recursive through containers |
| `getFieldElements()` | every field-bound element | |
| `getRelationElements()` / `getRelationElement(string $linker)` | many-to-many pickers | see 08-relations.md |
| `getRenderedFieldNames()` | fields whose elements are on the form | |
| `getEditableFieldNames()` | fields that post a value | not VIEW, disabled or excluded |
| `addFieldsFromObject(?array $fields = null)` | adds field elements (older API) | honours `groupFields()` and visibility conditions |
| `groupFields(string $label, array $names)` | fieldsets for `addFieldsFromObject()` | prefer `FormLayout::fieldset()` |
| `setFieldVisibilityCondition(string $name, callable)` / `isFieldVisible(string)` | `fn (Form $f): bool` | used by `addFieldsFromObject()` |

**Footer**

| Method | What it does | Notes |
|---|---|---|
| `getSubmitButton()` / `setSubmitButton(Button)` | the submit button | named `{form}_submit` at draw |
| `setSubmitButtonLabel(string)` | its caption | |
| `showSubmitButton()` / `hideSubmitButton()` / `isShowSubmitButton()` | toggle it | |
| `getResetButton()` / `setResetButton(?Button)` | a reset button before the others | none by default |
| `addButton(ComponentInterface)` | extra control between reset and submit | a `Button` is drawn bare |
| `getButtons()` / `setButtons(array)` | the extra controls | |
| `setCancelLink(string $href, string $label = 'Cancel', array $cssClasses = ['btn', 'btn-outline-secondary'])` | a Link via `addButton()` | |
| `showNavButtons()` / `hideNavButtons()` / `isShowNavButtons()` | Previous / Next buttons | only with the deprecated `addTab()` tabs |
| `addTab()`, `getTabs()`, `setTabs()`, `hasTabs()`, `getTabCount()` | **deprecated**: not drawn | use `FormLayout::tab()` |

**Processing**

| Method | What it does | Notes |
|---|---|---|
| `isSubmitted()` | `form_submit` names this table | request of the form's method |
| `detectSubmission()` | marker present; reads the posted draw mode | false = not this form's submission |
| `processForm(?string $documentHandlerClass = null)` | the whole pipeline, returns the record id | null when not submitted; throws `CsrfTokenException`, `FormValidationException` |
| `applySubmittedValues(?string $documentHandlerClass = null)` | step 3 alone | collects conversion errors |
| `validate()` | field rules + pickers + `validateRecord()`, returns the errors | pushes messages to the elements |
| `setValidateOnSubmit(bool)` / `isValidateOnSubmit()` | run `validate()` in `processForm()` (default true) | |
| `setRestrictToRenderedFields(bool)` / `isRestrictToRenderedFields()` | the mass-assignment guard | default from Settings, else false |
| `setRequest(?array $data, ?array $files = null)` | read these arrays instead of the superglobals | null = back to superglobals |
| `buildRequest(array $values, ?string $mode = null, bool $withCsrfToken = true)` | a request this form accepts | values by column name |
| `setSubmittedFieldValue(string $name, $value)` / `getSubmittedFieldValue(string)` | values set by code, always applied | scalar or null |
| `fieldIsSubmitted(string $name)` | the request carries the field | |
| `getErrors()` / `getErrorMessages()` / `hasErrors()` | the collected messages | |
| `addError(string $field, string $message)` / `setErrors(array)` / `clearErrors()` | manage messages | handed to the elements |
| `handleLookupRequest(?array $query = null, bool $terminate = true, ?array $post = null)` / `getLookupStatus()` | autocomplete search / create endpoint | see [08-relations.md](08-relations.md) |

**CSRF and field list**

| Method | What it does | Notes |
|---|---|---|
| `setCsrfProtection(bool)` / `isCsrfProtection()` | switch the check (default on) | POST only |
| `getCsrfSessionKey()` | `_csrf_token_{form}` | input name and session key |
| `getCsrfToken()` / `regenerateCsrfToken()` | the session token / a new one | starts the session when needed |
| `withCsrfToken(string $url)` | appends the token as a query parameter | for write links |
| `isValidCsrfToken(?string $token)` | compares with the session token | `hash_equals()` |
| `getFieldListInputName()` | `{form}_fields` | |
| `signedFieldList(array $rot13Names)` | `"a,b|hmac"` | |

## FormLayout reference

`Laf\UI\Form\FormLayout` (final). Constants: `KIND_FIELDS`, `KIND_FIELDSET`, `KIND_TAB`, `KIND_HTML`, `ALL_MODES`
(= `DrawMode::ALL`), `EDIT_MODES` (= `DrawMode::EDIT`), `METADATA_FIELDS`, `EDIT_EXCLUDED_FIELDS`.

| Method | What it does | Notes |
|---|---|---|
| `create()` | a new empty layout | static |
| `auto(Table $table, array $options = [])` | the generator's layout | static; options `columns`, `metadata`, `editExclude`, `detailsTab`, `generalTab` |
| `fields(array $names, int $columns = 1, ?array $modes = null)` | a plain block | |
| `fieldset(string $legend, array $names, int $columns = 1, ?array $modes = null)` | a `<fieldset>` | legend escaped by `Fieldset` |
| `tab(string $title, array $names, int $columns = 1, ?array $modes = null, string $icon = '')` | a tab | others become the "General" tab |
| `html(string $html, ?array $modes = null)` | raw HTML between sections | **not escaped** |
| `exclude(array $names, ?array $modes = null)` | not on the form | default: every mode |
| `readOnly(array $names, ?array $modes = null)` | drawn in VIEW mode, posts nothing | default: insert + update |
| `hidden(array $names, ?array $modes = null)` | wrapper `d-none`, still posts | default: every mode |
| `element(string $name, string $class)` | input class of a field or picker | short or fully qualified name |
| `clearFieldOptions(string $name)` | forget exclude/readonly/hidden/element of a field | |
| `setGeneralTabTitle()` / `getGeneralTabTitle()` | default `General` | |
| `setTabContainerId(?string)` / `getTabContainerId()` | default `{table}_form_tabs` | |
| `setAppendUnlisted(bool)` / `isAppendUnlisted()` | append fields in no section (default true) | |
| `isExcluded()`, `isReadOnly()`, `isHidden()` `(string $name, string $mode)` | queries | |
| `getElementClass(string $name)` | the class set with `element()` | |
| `getSections(?string $mode = null)` | all sections, or those active in a mode | |
| `getFieldOptions()` | name => options | |
| `isEmpty()` | no sections and no options | |
| `toArray()` / `fromArray(array $data)` | serialise | `fromArray()` throws `\InvalidArgumentException` on bad input |
| `resolveElementClass(string $name)` | short name to class | static; throws `\InvalidArgumentException` |
| `apply(Form $form, string $mode)` | replaces the form's components | `Form::applyLayout()` calls it |

## See also

- [07-form-inputs.md](07-form-inputs.md): every input element, choosing and customising elements
- [08-relations.md](08-relations.md): foreign-key Select/Autocomplete, `handleLookupRequest()`, many-to-many pickers
- [05-validation.md](05-validation.md): field rules, `validateRecord()`, `ValidationException`
- [03-models.md](03-models.md): `store()`, `insert()`, `update()`, hooks, exceptions
- [02-code-generation.md](02-code-generation.md): JSON column comments (`form` keys) that feed `FormLayout::auto()`
- [11-pages-and-ui.md](11-pages-and-ui.md): containers, pages, `Flash`
- [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md): the generated `.page` files
- [17-security.md](17-security.md): CSRF, mass assignment, escaping
- [18-testing-and-tooling.md](18-testing-and-tooling.md): testing forms with `buildRequest()`
