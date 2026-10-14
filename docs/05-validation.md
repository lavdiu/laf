# Validation

Validation in LAF lives on the model, so the same rules protect a form, an import script and an API. Each `Field`
carries its rules (required, maximum and minimum length, numeric range, pattern, custom validators); rules that
involve several fields or the database go in the model's `validateRecord()`. `$model->validate()` checks everything
and returns messages per field. Forms run the same rules on every submission and draw the messages next to the
inputs. Direct `store()` calls check only "required" and "unique" by default; switch on validation on store
(`orm.validate_on_store`, or per object) to make `store()` refuse invalid data with a `ValidationException`.

On this page:

- [Where rules come from](#where-rules-come-from)
- [Field rules](#field-rules)
- [Record rules: validateRecord()](#record-rules-validaterecord)
- [Checking a record: validate()](#checking-a-record-validate)
- [Validation on store](#validation-on-store)
- [ValidationException](#validationexception)
- [How forms show errors](#how-forms-show-errors)
- [Reference](#reference)
- [See also](#see-also)

All outputs below come from real runs against the shared schema (see [03-models.md](03-models.md)).

## Where rules come from

| Rule | Source | Checked by |
|---|---|---|
| required | `NOT NULL` without auto-increment (column comment `{"fields":{"setRequired":false}}` turns it off) | `store()` always (`MissingFieldValueException`), `validate()`, forms |
| unique | a `UNIQUE` index: single column (`setUnique()`) or several columns (`Table::addUniqueKey()`) | `store()` always (`UniqueFieldDuplicateValueException`), `validate()`, forms (as a message, also when only the database knows the index) |
| maximum length | `varchar(n)` / `char(n)` | `validate()`, forms, validation on store |
| integer range | the integer size (`int`: -2147483648..2147483647, unsigned variants) | same |
| decimal digits | `decimal(p, s)`: at most `p - s` digits before the point | same |
| value conversion | the field type: `'2024-02-30'` is not a date | the setter, immediately (`InvalidValueException`) |
| minimum length, pattern, min/max value, custom validators | your code in the leaf class | `validate()`, forms, validation on store |
| cross-field and database rules | your `validateRecord()` | same |

The first four are generated into the Base class (see
[BaseEmployee.php](examples/generated/MyApp/Base/BaseEmployee.php): `setRequired(true)`, `setMaxLength(50)`,
`addUniqueField()`); labels used in the messages come from the column names or the column comments
(`{"fields":{"setLabel":"E-mail"}}`). Everything else you add in the leaf class.

## Field rules

Add rules to the fields in the leaf class constructor, after `parent::__construct($id)`. They then apply to every
`Employee` object, whichever code path writes it:

```php
<?php

namespace MyApp;

class Employee extends Base\BaseEmployee
{
    public function __construct($id = null)
    {
        parent::__construct($id);

        $this->getFirstNameFld()->setMinLength(2);
        $this->getEmailFld()->setPattern('/@example\.(com|org)$/', 'Use your company e-mail address');
        $this->getSalaryFld()->setMinValue('0.01')->setMaxValue(50000);
        $this->getLastNameFld()->addValidator(
            fn ($value) => ctype_upper(mb_substr($value, 0, 1)) ? null : 'Last name starts with a capital letter'
        );
    }

    // find() / findOne() as generated ...
}
```

| Rule | Method | Message |
|---|---|---|
| required | `setRequired(bool)` | `First Name is required` |
| maximum length | `setMaxLength(int)` | `First Name must be at most 50 characters` |
| minimum length | `setMinLength(int)` | `First Name must be at least 2 characters` |
| pattern | `setPattern(?string $regex, ?string $message = null)` | your message, else `E-mail has an invalid format` |
| minimum / maximum | `setMinValue(int\|float\|string\|null)`, `setMaxValue(...)` | `Salary must be at least 0.01` / `must be at most 50000` |
| custom | `addValidator(callable)` | what the callable returns |

Details:

- **Empty values are the required rule's business.** Minimum length, pattern and custom validators skip `null` and
  `''`. An optional field left empty is valid; mark it required if it must be filled.
- **Patterns** use `preg` syntax with delimiters. A pattern that does not compile is refused immediately:
  `InvalidArgumentException('Invalid pattern for field email: /[unclosed/')`. `setPattern(null)` removes it.
- **Minimum / maximum** apply to integer, decimal and float fields. Give decimal bounds as numeric strings
  (`'0.01'`) to keep them exact; a non-numeric bound throws `InvalidArgumentException`. `null` removes a bound.
- **Custom validators** receive `($value, Field $field)` and return:

| Return | Meaning |
|---|---|
| `null`, `true`, `''`, `[]` | valid |
| `false` | invalid; message = `setInvalidValueErrorMessage()` text, else `Notes is invalid` |
| a string | invalid, with that message |
| an array of strings | invalid, with each message |

```php
$notes = $employee->getNotesFld();
$notes->addValidator(fn ($value) => false)
      ->addValidator(fn ($value, $field) => ['one ' . $field->getName(), 'two']);
$notes->setValue('x');
$notes->validate();    // ['Notes is invalid', 'one notes', 'two']
$notes->setInvalidValueErrorMessage('Notes are not allowed');
$notes->validate();    // ['Notes are not allowed', 'one notes', 'two']
$notes->setValue('');
$notes->validate();    // []  (validators skip an empty value)
$notes->clearValidators();
```

Type rules come for free:

```php
$employee->setSalaryVal('123456789.99');         // decimal(10,2)
$employee->getSalaryFld()->validate();           // ['Salary must have at most 8 digits before the decimal point']

$employee->setFirstNameVal(str_repeat('x', 60));
$employee->getFirstNameFld()->validate();        // ['First Name must be at most 50 characters']
$employee->getFirstNameFld()->isValidValue();    // false
```

> **Note:** a value the type cannot represent never reaches validation: the setter throws
> `Laf\Exception\InvalidValueException` at once (see [03-models.md](03-models.md#reading-and-writing-values)).
> Forms catch that for you and show `Birth Date: invalid value` on the field, keeping what the user typed.

## Record rules: validateRecord()

Rules that compare fields, depend on other rows or apply to the record as a whole go in `validateRecord()`. Override
it in the leaf class and return field => message (or a list of messages); use the key `''` or
`ValidationException::RECORD_KEY` for a message that belongs to no single field:

```php
use Laf\Exception\ValidationException;

class Employee extends Base\BaseEmployee
{
    protected function validateRecord(): array
    {
        $errors = [];
        if ($this->getIsActiveVal() === false && $this->getSalaryVal() !== null) {
            $errors['salary'] = 'Inactive employees have no salary';
        }
        if ($this->getBirthDateVal() !== null && $this->getBirthDateVal() > date('Y-m-d', strtotime('-16 years'))) {
            $errors[ValidationException::RECORD_KEY] = 'Employees must be at least 16 years old';
        }
        return $errors;
    }
}
```

`validateRecord()` may query the database (for example "the company must not be soft-deleted":
`Company::load($this->getCompanyIdVal())?->getRecordStatusIdVal() !== 0`). It runs whenever the record is
validated: `validate()`, forms, and `store()` with validation on. `recordErrors()` is the public view of it with
every entry as a list and `''` mapped to `ValidationException::RECORD_KEY` (`'_record'`).

## Checking a record: validate()

`$model->validate()` runs, without writing anything: every field's rules (required, type, length, range, pattern,
validators), the unique checks against the database (unique columns and unique combinations), and `validateRecord()`. It returns
column => list of messages, empty when valid:

```php
$e = new Employee();
$e->setFirstNameVal('A')->setLastNameVal('smith')->setEmailVal('a@gmail.com')
  ->setSalaryVal('0')->setIsActiveVal(false)->setBirthDateVal('2020-01-01');

$e->validate();
```

```php
[
    'first_name' => ['First Name must be at least 2 characters'],
    'last_name'  => ['Last name starts with a capital letter'],
    'email'      => ['Use your company e-mail address'],
    'salary'     => ['Salary must be at least 0.01', 'Inactive employees have no salary'],
    '_record'    => ['Employees must be at least 16 years old'],
]
```

A blank object reports its required fields:

```php
(new Employee())->validate();
// ['first_name' => ['First Name is required'], 'last_name' => ['Last Name is required'], 'email' => ['E-mail is required']]

$dup = new Company();
$dup->setNameVal('Acme');           // a company with this name exists
$dup->validate();                   // ['name' => ['Name must be unique: the value already exists']]

$link = new EmployeeProject();      // employee 32 is already on project 11
$link->setEmployeeIdVal(32)->setProjectIdVal(11);
$link->validate();
// ['employee_id' => ['Team members, Projects must be unique together: the combination already exists']]
$link->duplicateUniqueKeys();       // [['employee_id', 'project_id']]
```

Use `validate()` in imports and APIs to report every problem at once instead of stopping at the first exception.

## Validation on store

By default `store()` / `insert()` / `update()` check only required and unique values (they throw
`MissingFieldValueException` / `UniqueFieldDuplicateValueException`, see [03-models.md](03-models.md#exceptions)).
The other rules are opt-in for direct writes, so existing code and old rows keep working when you add a rule:

```php
$e = new Employee();
$e->setFirstNameVal('V')->setLastNameVal('lower')->setEmailVal('v@gmail.com');
$e->getValidateOnStore();     // null  (follow Settings)
$e->isValidateOnStore();      // false (Settings orm.validate_on_store defaults to false)
$e->store();                  // true: written despite three broken rules
```

Switch it on for the whole application in the settings:

```php
// config/settings.php (Settings::loadFromFile()) or at bootstrap
use Laf\Util\Settings;

Settings::set('orm.validate_on_store', true);
```

or per object, which wins over the setting (`null` follows the setting again):

```php
$e->setValidateOnStore(true);    // this object validates on store
$e->setValidateOnStore(false);   // this object never does, whatever the setting
$e->setValidateOnStore(null);    // follow orm.validate_on_store
```

With validation on, a write that breaks a rule throws `Laf\Exception\ValidationException` before any SQL:

```php
use Laf\Exception\ValidationException;

$e = new Employee();
$e->setFirstNameVal('V')->setLastNameVal('lower')->setEmailVal('x@gmail.com')->setValidateOnStore(true);
try {
    $e->store();
} catch (ValidationException $ex) {
    $ex->getMessage();
    // 'First Name must be at least 2 characters; Last name starts with a capital letter; Use your company e-mail address'
    $ex->getFieldNames();   // ['first_name', 'last_name', 'email']
    $ex->getTableName();    // 'employee'
}
```

What is checked:

| Write | Field rules | `validateRecord()` |
|---|---|---|
| insert | every field | yes |
| update | only the changed fields | yes |

Updates check only changed fields on purpose: a row written before a rule existed stays editable. In a run with
`orm.validate_on_store` on, an employee whose stored first name `'V'` breaks the minimum length could still be
updated by changing only `notes` (`store()` returned `true`), while setting the first name to `'Q'` threw with
`['first_name' => ['First Name must be at least 2 characters']]`. `validateRecord()` runs on every update, so keep
record rules true for existing data, or make them look only at what changed (`$this->getChangedFields()`).

Order of the checks inside `insert()` / `update()`: the `before*` hook, then required values
(`MissingFieldValueException`), then unique values (`UniqueFieldDuplicateValueException`), then the rules
(`ValidationException`). A missing required value is therefore reported as `MissingFieldValueException` even when
validation on store is on; `validate()` reports everything together.

## ValidationException

`Laf\Exception\ValidationException` (extends `\Exception`):

| Method / constant | Returns |
|---|---|
| `getErrors()` | field => list of messages, record-level messages under `RECORD_KEY` |
| `getMessages()` | every message as a flat list, in field order |
| `getFieldNames()` | the fields with messages (`RECORD_KEY` left out) |
| `getTableName()` / `setTableName()` | the table, set by `store()` |
| `RECORD_KEY` | `'_record'` |
| `__construct(array $errors, string $message = '', int $code = 0, ?\Throwable $previous = null)` | the message defaults to the messages joined with `'; '` |

A record-level failure only has the `RECORD_KEY` entry:

```php
$e->setFirstNameVal('Valid')->setLastNameVal('Young')->setEmailVal('young@example.com')->setBirthDateVal('2020-01-01');
// store() with validation on:
// getErrors()     === ['_record' => ['Employees must be at least 16 years old']]
// getFieldNames() === []
```

`Laf\Exception\FormValidationException` extends it, so one `catch (ValidationException $ex)` handles both a form
submission and a direct `store()`. Throw a `ValidationException` yourself from services that validate input outside
a model, to keep one error shape across the application.

## How forms show errors

A form built from the model (`$employee->getForm(DrawMode::INSERT)`) validates every submission with the same rules:
the editable fields' rules first, then `validateRecord()` once the fields are valid. `processForm()` throws
`FormValidationException`, and the form drawn again shows the messages:

```php
use Laf\Exception\FormValidationException;
use Laf\UI\Form\DrawMode;
use MyApp\Employee;

$employee = new Employee();
$form = $employee->getForm(DrawMode::INSERT);
try {
    $id = $form->processForm();       // null when the form was not submitted
} catch (FormValidationException $ex) {
    $errors = $ex->getErrors();
    // ['first_name' => ['First Name must be at least 2 characters'],
    //  'last_name'  => ['Last name starts with a capital letter'],
    //  'email'      => ['Use your company e-mail address'],
    //  'salary'     => ['Salary must be at least 0.01']]
}
echo $form->draw();
```

The drawn form starts with a summary of every message:

```html
<div id='employee_errors' class='alert alert-danger laf-form-errors' role='alert'>
	<ul class='mb-0'>
		<li>First Name must be at least 2 characters</li>
		<li>Last name starts with a capital letter</li>
		<li>Use your company e-mail address</li>
		<li>Salary must be at least 0.01</li>
	</ul>
</div>
```

and marks each input with Bootstrap's `is-invalid`, the submitted value and its own message:

```html
<input class='Laf-UI-Form-Input-Text form-control is-invalid' style=''
	type="text"
	name="svefg_anzr"
	id="svefg_anzr"
	value="Q"
	maxlength="50"
	minlength="2"
	required="required"
	placeholder="First Name" aria-describedby='svefg_anzr_feedback' aria-invalid='true' />
<div id='svefg_anzr_feedback' class='invalid-feedback d-block'>First Name must be at least 2 characters</div>
```

Things to know:

- Messages are escaped when drawn.
- `setMinLength()` and `setMaxLength()` also become `minlength` / `maxlength` attributes, so the browser checks
  them before submitting; the server check still runs.
- Record-level messages (`RECORD_KEY`) appear in the summary under the form's own key `Form::FORM_ERROR_KEY`
  (`'_form'`).
- Taken unique values and combinations are reported with the other field messages ("E-mail: this value is already
  in use"). A unique violation that only the database detects comes back the same way, never as an error page (see
  [06-forms.md](06-forms.md#unique-values)).
- Missing required values refused by the write also come back as `FormValidationException` with the message on the
  field.
- The form switches off validation on store for its own write, so the rules do not run twice.

Error summary placement, `addError()`, `setValidateOnSubmit(false)` and the full submission flow are covered in
[06-forms.md](06-forms.md).

## Reference

`Laf\Database\Field\Field`:

| Method | What it does |
|---|---|
| `validate(): array` | messages for the current value (empty = valid) |
| `isValidValue(): bool` | `validate()` is empty |
| `setRequired(bool)`, `isRequired()` | required rule |
| `setMaxLength(int)`, `getMaxLength()` | checked for varchar, char and text types |
| `setMinLength(int)`, `getMinLength()` | skips empty values |
| `setMinValue($min)`, `getMinValue()`, `setMaxValue($max)`, `getMaxValue()` | numeric bounds; numeric strings stay exact |
| `setPattern(?string $regex, ?string $message = null)`, `getPattern()`, `getPatternMessage()` | regex rule; throws when it does not compile |
| `addValidator(callable)`, `getValidators()`, `clearValidators()` | custom rules `fn ($value, Field $field)` |
| `setInvalidValueErrorMessage(?string)`, `getInvalidValueErrorMessage()` | message for a validator returning `false` |

`Laf\Database\BaseObject`:

| Method | What it does |
|---|---|
| `validate(): array` | every field rule, unique values, `validateRecord()`; field => messages |
| `protected validateRecord(): array` | your record rules; field (or `''` / `RECORD_KEY`) => message(s) |
| `recordErrors(): array` | `validateRecord()` normalised to field => list |
| `setValidateOnStore(?bool)`, `getValidateOnStore()`, `isValidateOnStore()` | validation inside `store()` |
| `protected assertValidForStore(bool $inserting)` | the check `insert()` / `update()` run; throws `ValidationException` |

Setting: `orm.validate_on_store` (bool, default `false`), see [16-settings-locale-utilities.md](16-settings-locale-utilities.md).

## See also

- [03-models.md](03-models.md) — the write path, `MissingFieldValueException`, `UniqueFieldDuplicateValueException`
- [06-forms.md](06-forms.md) — submissions, `FormValidationException`, error summary, `buildRequest()`
- [07-form-inputs.md](07-form-inputs.md) — how inputs draw errors and HTML attributes
- [02-code-generation.md](02-code-generation.md) — column comments that set labels and required flags
- [18-testing-and-tooling.md](18-testing-and-tooling.md) — testing models and forms without a browser
