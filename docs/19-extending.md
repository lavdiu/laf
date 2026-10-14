# Extending LAF

LAF is built to be extended from the outside: you never edit `src/Laf/` or the generated `Base/` classes, you add
your own classes next to them and plug them in. This chapter covers the extension points that the other chapters
only mention: writing your own **field type** (how values are converted, bound, compared, validated and drawn),
your own form elements and UI components, **logging** what a model does through a PSR-3 logger, styling the
**wrapper** of inputs and components, and the two exception factories you can reuse in your own data code. It ends
with an overview table of every hook and setting you can plug into, with a link to the chapter that covers each.
All examples run against the shared four-table schema (`company`, `employee`, `project`, `employee_project`).

**On this page**

- [Custom field types](#custom-field-types)
  - [The FieldType contract](#the-fieldtype-contract)
  - [How the built-in types use it](#how-the-built-in-types-use-it)
  - [A real custom type: lower-cased e-mail addresses](#a-real-custom-type-lower-cased-e-mail-addresses)
  - [A type from scratch: percentages](#a-type-from-scratch-percentages)
  - [Using a custom type: leaf class, column comment, FieldTypeFactory](#using-a-custom-type-leaf-class-column-comment-fieldtypefactory)
- [Custom form elements and UI components](#custom-form-elements-and-ui-components)
- [Logging what a model does](#logging-what-a-model-does)
- [Wrapper styling and attributes](#wrapper-styling-and-attributes)
  - [Wrapper classes and style](#wrapper-classes-and-style)
  - [Wrapper attributes](#wrapper-attributes)
  - [Input attribute getters](#input-attribute-getters)
  - [Label text: suffix and required marker](#label-text-suffix-and-required-marker)
  - [Wrapper settings in your own component](#wrapper-settings-in-your-own-component)
- [Exceptions you can build yourself](#exceptions-you-can-build-yourself)
- [Extension points at a glance](#extension-points-at-a-glance)
- [See also](#see-also)

## Custom field types

Every `Field` of a model has a type object (`Laf\Database\Field\FieldType`). The type is the single place that
knows what a valid value of the column looks like: the generator picks one per column (`TypeInteger`,
`TypeDecimal`, `TypeVarchar`, ... see [03-models.md](03-models.md#field-types) and the
[type mapping](02-code-generation.md#type-mapping)), and you can replace it with your own class when a column
holds something more specific than its SQL type says: an e-mail address, a percentage, a phone number, money in
cents, a comma list.

Why a type and not a validator? A validator (`Field::addValidator()`, [05-validation.md](05-validation.md)) only
says yes or no. A type also **normalises** the value (so `"John.DOE@Example.COM"` and `"john.doe@example.com"` are
the same value, compare equal and are stored once), chooses the **PDO binding**, adds HTML **input attributes** and
picks the **form element**. Use a validator for a rule, a type for a kind of value.

### The FieldType contract

A value crosses four boundaries, each with one method:

```text
DB row ──fromDb()────► PHP value ──toDb()──► bound parameter
form   ──fromInput()─► PHP value
code   ──normalize()─► PHP value            (setXxxVal(), setFieldValue(), Field::setValue())
```

The "PHP value" is the **canonical** value of the type (an `int`, a decimal string `"1234.50"`, a `bool`, a
`'Y-m-d'` string, a lower-cased e-mail ...). Keeping one canonical form is what makes change tracking, the audit
log and JSON output stable.

| Method | Called by | Contract |
|---|---|---|
| `getTypeName(): string` | messages (`Invalid value 'x' for type email`), your code | short machine name: `'integer'`, `'decimal'`, `'email'` |
| `normalize(mixed $value): mixed` | `Field::setValue()` (every setter) | any PHP value to the canonical value; `null` and `''` (or whitespace) become `null`; throws `Laf\Exception\InvalidValueException` when the value cannot be represented. Must be idempotent: forms call it again on the output of `fromInput()` |
| `fromDb(mixed $raw): mixed` | `Field::loadValueFromDb()` (loading rows) | raw PDO value to the canonical value; **never throws**: a value the type cannot parse is returned untouched, so a row can always be loaded |
| `fromInput(?string $input): mixed` | `Form` when it applies a submission | text a user typed to the canonical value (may accept locale formats); throws `InvalidValueException`, which the form turns into the message "*Label*: invalid value" |
| `toDb(mixed $value): array` | `BaseObject` when it binds INSERT / UPDATE / DELETE values | `[value to bind, PDO::PARAM_*]`; `null` must give `[null, PDO::PARAM_NULL]` |
| `equals(mixed $a, mixed $b): bool` | `Field::hasChanged()`, so `isDirty()`, `getChangedFields()`, the UPDATE column list and the audit log | type-aware equality: `"2.10"` equals `"2.1"` for a decimal |
| `validate(mixed $value, ?Field $field = null): array` | `Field::validate()`, so `$model->validate()`, forms, validation on store | constraint messages beyond the shape (length, range, precision, format); `[]` when valid. Only called for a non-null value |
| `getInputAttributes(?Field $field = null): array` | the input element when it is bound to the field | extra HTML attributes (`step`, `min`, `max`, `pattern`, `inputmode`, ...); attributes already set on the element win |
| `generatePrimaryKeyValue(): ?string` | `BaseObject::insert()` when a non-auto-increment key is empty | a new key (`TypeVarchar`, `TypeUuid`: a v4 UUID), or `null` when the type cannot generate one |
| `getFormElement(Field $field)` | `Field::getFormElement()` when no element class was chosen | the default input; see below for the role rules |
| `getPdoType()` | `toDb()` in `AbstractFieldType` | legacy: the `PDO::PARAM_*` constant of a non-null value |
| `isValid($value)` | old application code | legacy: `normalize()` succeeded |
| `getValueDbSanitized($value)` | `Field::getValueDbSanitized()`, old code | legacy: `normalize()`, but `null` instead of an exception |
| `formatForDb(?string $value)` | `Field::getValueForDbInsert()`, old code | legacy: `fromInput()`, but `null` instead of an exception |

New code uses the first nine; the legacy four stay so applications written against the old interface keep working.

**`AbstractFieldType`** implements the whole contract on top of four abstract methods, so a new type usually writes
only these:

| You write | What it does |
|---|---|
| `getTypeName(): string` | the name |
| `protected convert(mixed $value): mixed` | one **non-empty** value to the canonical value; `throw $this->invalid($value, 'reason')` when impossible |
| `getPdoType()` | the PDO binding |
| `protected createFormElement(Field $field)` | the natural input of the type (`Text`, `Email`, `Number`, `TextArea`, `Checkbox`, ...) |

and gets: `normalize()` (empty to `null`, else `convert()`), `fromInput()` (trim, then `normalize()`), `fromDb()`
(`convert()`, raw value on failure), `toDb()` (`[value, getPdoType()]`), `equals()` (normalises both sides, then
`compareCanonical()`, strict `===` by default), empty `validate()` / `getInputAttributes()`, `null` from
`generatePrimaryKeyValue()`, and the legacy four. Protected helpers: `isEmpty()`, `invalid($value, $reason)` (builds
the `InvalidValueException`), `toStringOrFail($value)` (scalars and `Stringable` to string, everything else
rejected), `compareCanonical($a, $b)`.

`AbstractFieldType::getFormElement()` applies the **column's role before its type**: a foreign key gets a `Select`
(an `Autocomplete` above `form.select_autocomplete_threshold`, a `File` when it points at the document table), an
auto-increment primary key gets `Hidden`; only other columns get your `createFormElement()`. Override
`getFormElement()` itself only if your type must win over those rules.

### How the built-in types use it

Reading the built-in types is the fastest way to learn the contract. The public extras they add:

| Type | Constructor | Extras | What it shows |
|---|---|---|---|
| `TypeDecimal` | `(?int $precision = null, ?int $scale = null)` | `getPrecision(): ?int`, `getScale(): ?int`, `static round(string $number, int $scale): string` | the canonical value is a **string** rounded half-up to the scale (no float rounding); `validate()` counts digits before the point; `getInputAttributes()` gives `inputmode="decimal"` and `step` from the scale; `compareCanonical()` compares with `bccomp()` |
| `TypeInteger` | `(string $size = 'int', bool $unsigned = false)` | `getSize()`, `isUnsigned(): bool`, `getMinValue(): string`, `getMaxValue(): string` | the range of the size (`tinyint` ... `bigint`, `year`) checked in `validate()` and drawn as `min` / `max`; `toDb()` binds big values as strings |
| `TypeVarchar` (and `TypeChar`, `TypeText`) | none | | `convert()` trims and keeps the text verbatim (no HTML escaping at storage); `validate()` checks `Field::getMaxLength()`; `generatePrimaryKeyValue()` returns a UUID |
| `TypeUuid` | none | | `convert()` accepts upper case, braces and the 32-digit form and returns the lower-case hyphenated form; `generatePrimaryKeyValue()` = `Util::uuid()`; `pattern` and `maxlength` input attributes |
| `TypeDateTime` | `(bool $withTimezone = false)` | `isWithTimezone(): bool` | `true` for `timestamptz` / `timestamp with time zone` columns |
| `TypeEnum` | `(array $options = [], bool $multiple = false)` | `getOptions(): array`, `isMultiple(): bool` | case-insensitive match corrected to the declared label (`'DONE'` becomes `'done'`); a SET (`$multiple`) is the comma list in declaration order; the element is a `Select` (or `CheckboxCollection` for a SET) with the options |
| `TypeBool` | none | | overrides `normalize()` because `''` means `false`, not "no value"; `toDb()` binds 0/1 |
| `TypeJson` | none | `decode(?string $json, bool $assoc = true)` | arrays and objects are encoded on the way in; `compareCanonical()` compares decoded values |

Values from a real run on the example schema (`employee.salary` is `decimal(10,2)`, `employee.id` is `int(11)`):

```php
use Laf\Database\Field\TypeEnum;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeVarchar;
use MyApp\Employee;

$salary = (new Employee())->getSalaryFld()->getType();
// TypeDecimal: getPrecision() 10, getScale() 2, getInputAttributes() {"inputmode":"decimal","step":"0.01"}

$id = (new Employee())->getIdFld()->getType();
// TypeInteger: getSize() 'int', isUnsigned() false, getMinValue() '-2147483648', getMaxValue() '2147483647'
// getInputAttributes() {"step":"1","inputmode":"numeric","min":"-2147483648","max":"2147483647"}

(new TypeVarchar())->generatePrimaryKeyValue();   // a UUID string
(new TypeInteger())->generatePrimaryKeyValue();   // null: auto-increment keys come from the database

$enum = new TypeEnum(['draft', 'done']);
$enum->normalize('DONE');                         // 'done'
$enum->getOptions();                              // ['draft', 'done']
$enum->isMultiple();                              // false
```

### A real custom type: lower-cased e-mail addresses

`employee.email` is a `varchar(150)` with a unique index. Users type `John.Doe@Example.com` one day and
`john.doe@example.com` the next; the database treats them as different strings on PostgreSQL (and the audit log
records a "change" when only the case differs). A type that stores the lower-cased address fixes all of that in one
place. Extending `TypeVarchar` keeps its trimming, its max-length check and its `maxlength` input attribute:

```php
namespace MyApp\Type;

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeVarchar;
use Laf\UI\Form\Input\Email;

class TypeEmail extends TypeVarchar
{
    public function getTypeName(): string
    {
        return 'email';
    }

    /** One non-empty value: trimmed (parent) and lower-cased */
    protected function convert(mixed $value): mixed
    {
        return mb_strtolower(parent::convert($value));
    }

    /** The parent's max-length check plus the address format */
    public function validate(mixed $value, ?Field $field = null): array
    {
        $messages = parent::validate($value, $field);
        if ($value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $label = $field ? ($field->getLabel() ?: $field->getName()) : 'Value';
            $messages[] = sprintf('%s is not a valid e-mail address', $label);
        }
        return $messages;
    }

    public function getInputAttributes(?Field $field = null): array
    {
        return ['inputmode' => 'email', 'autocomplete' => 'email', 'spellcheck' => 'false'];
    }

    /** TypeVarchar would generate a UUID for an empty key; an address is never generated */
    public function generatePrimaryKeyValue(): ?string
    {
        return null;
    }

    protected function createFormElement(Field $field)
    {
        $element = new Email();
        $element->setField($field);
        return $element;
    }
}
```

Plug it into the model in the leaf class `MyApp/Employee.php` (the generated `Base/BaseEmployee.php` would lose
the change on the next generation run). The generated constructor builds the table and loads the row in one go, so
build without a key first, swap the type, then load; that way loaded values already go through the new type:

```php
class Employee extends Base\BaseEmployee
{
    public function __construct($id = null)
    {
        parent::__construct();                                  // builds the fields, loads nothing
        $this->getEmailFld()->setType(new \MyApp\Type\TypeEmail());
        if ($id !== null && $id !== '') {
            $this->select($id);
        }
    }
    // ... find(), findOne() as generated
}
```

`Employee::query()->get()`, `hydrate()`, `load()` and the related-object getters all create models with
`new static(...)`, so they run this constructor too.

What it does (output of a real run; `setEmailVal()` is the generated setter):

```php
$employee = new Employee();
$employee->setFirstNameVal('Ch19')->setLastNameVal('Typecheck')->setEmailVal('  Ch19.TYPE@Example.COM ');
$employee->getEmailVal();            // 'ch19.type@example.com'
$employee->store();                  // the column holds 'ch19.type@example.com'

$again = new Employee($employee->getIdVal());
$again->setEmailVal('CH19.type@example.com');
$again->isDirty();                   // false: equals() compares canonical values, no UPDATE, no audit entry

$again->setEmailVal('not-an-address');
$again->validate();                  // ['email' => ['E-mail is not a valid e-mail address']]

$again->setEmailVal(str_repeat('a', 150) . '@x.com');
$again->validate()['email'];         // ['E-mail must be at most 150 characters', 'E-mail is not a valid e-mail address']
```

The type's contract methods directly:

```php
$type = new TypeEmail();
$type->normalize('  John.DOE@Example.COM ');   // 'john.doe@example.com'
$type->normalize('');                           // null
$type->fromInput(" A@B.C\n");                    // 'a@b.c'
$type->fromDb('MiXeD@x.org');                   // 'mixed@x.org'
$type->toDb('a@b.c');                           // ['a@b.c', PDO::PARAM_STR]  (2)
$type->toDb(null);                              // [null, PDO::PARAM_NULL]    (0)
$type->equals('A@b.c', 'a@B.C');                // true
$type->normalize([]);                           // InvalidValueException: Invalid value 'array' for type email
$type->isValid([]);                             // false   (legacy)
$type->getValueDbSanitized([]);                 // null    (legacy)
$type->formatForDb(' X@Y.Z');                   // 'x@y.z' (legacy)
```

On a form the field now draws an `Email` input with the type's attributes and the inherited `maxlength`
(`$employee->getEmailFormElement()` in UPDATE mode):

```html
<div id='rznvy_container' style='' class='mb-3 row'>
    <label id='rznvy_label' for='rznvy' class='col-sm-2 col-form-label'>E-mail* :</label>
    <div class='col-sm-10'>
        <input class='Laf-UI-Form-Input-Email form-control' style=''
            type="email" name="rznvy" id="rznvy" value="ch19.second@example.com"
            maxlength="150" required="required" placeholder="Email"
            inputmode="email" autocomplete="email" spellcheck="false" />
    </div>
</div>
```

A submission with `Nope` in the e-mail box fails with
`FormValidationException::getErrors()` = `['email' => ['E-mail is not a valid e-mail address']]`, and the form
redraws with that message under the input ([06-forms.md](06-forms.md#validation-errors)).

> **Note:** where to reject a value. Throwing from `convert()` rejects it before it reaches the field: a setter
> throws `InvalidValueException`, a form shows the generic "E-mail: invalid value". Reporting it from `validate()`
> keeps the value on the object and gives your own message. Throw only when the value cannot be represented at all
> (an array for a string, letters for a number); report everything else from `validate()`.

> **Note:** a type that does not extend `TypeVarchar` (or `TypeJson`, `TypeUuid`, `TypeEnum`, `TypeBlob`) does not
> get the `maxlength` / `minlength` input attributes: the input only copies them for those character types. Extend
> the closest built-in type, or return `maxlength` from your `getInputAttributes()`.

### A type from scratch: percentages

When no built-in type is close, extend `AbstractFieldType` and write the four abstract methods plus whatever else
the type needs. A percentage between 0 and 100 kept as a two-decimal string, typed as `12.5`, `12,5` or `12,5 %`:

```php
namespace MyApp\Type;

use Laf\Database\Field\AbstractFieldType;
use Laf\Database\Field\Field;
use Laf\Database\Field\TypeDecimal;
use Laf\UI\Form\Input\Number;

class TypePercent extends AbstractFieldType
{
    public function getTypeName(): string
    {
        return 'percent';
    }

    protected function convert(mixed $value): mixed
    {
        $text = is_string($value) ? str_replace(['%', ' ', ','], ['', '', '.'], $value) : $value;
        if (!is_numeric($text)) {
            throw $this->invalid($value, 'not a percentage');
        }
        return TypeDecimal::round((string)$text, 2);
    }

    public function getPdoType()
    {
        return \PDO::PARAM_STR;
    }

    /** '7' and '7.00' are the same percentage */
    protected function compareCanonical(mixed $a, mixed $b): bool
    {
        return (float)$a === (float)$b;
    }

    public function validate(mixed $value, ?Field $field = null): array
    {
        return ((float)$value < 0 || (float)$value > 100) ? ['Percentage must be between 0 and 100'] : [];
    }

    public function getInputAttributes(?Field $field = null): array
    {
        return ['min' => '0', 'max' => '100', 'step' => '0.01'];
    }

    protected function createFormElement(Field $field)
    {
        $element = new Number();
        $element->setField($field);
        return $element;
    }
}
```

```php
$type = new TypePercent();
$type->fromInput('12,5 %');          // '12.50'
$type->normalize(7);                 // '7.00'
$type->equals('7', '7.00');          // true
$type->validate('120.00');           // ['Percentage must be between 0 and 100']
$type->toDb('12.50');                // ['12.50', PDO::PARAM_STR]
$type->fromInput('abc');             // InvalidValueException: Invalid value 'abc' for type percent (not a percentage)
$type->isValid('abc');               // false

$field = (new \MyApp\Project())->getBudgetFld()->setType(new TypePercent());
$field->setValue('33,333');
$field->getValue();                  // '33.33'
get_class($field->getFormElement()); // Laf\UI\Form\Input\Number
```

### Using a custom type: leaf class, column comment, FieldTypeFactory

| Way | When | How |
|---|---|---|
| `Field::setType(FieldType $type)` in the leaf class | **custom classes** (yours) | in the constructor as shown above, or in a `Field` you build yourself; `hasType()` / `getType()` read it back |
| Column comment `{"fields":{"setType":"..."}}` | a **built-in** type other than the schema's: a MySQL `char(36)` holding UUIDs (`"uuid"`), a `varchar` you want as `"text"`, `"decimal(12,2)"` | the generator writes `->setType(new Database\Field\TypeUuid())` into the Base class ([02-code-generation.md](02-code-generation.md#fields-field-setters)) |
| `FieldTypeFactory` | code that maps a database type to a type object itself (inspectors, tools, tests) | `resolve()`, `getClass()`, `getClassLiteral()`, below |

The column comment only knows the names in the factory's map; it cannot name your class, and an unknown name
silently becomes `TypeText` (`"setType":"email"` gives `new Database\Field\TypeText()`). Custom classes therefore
always go in the leaf class.

`Laf\Database\Field\FieldTypeFactory` is the one table behind both the generator and the runtime:

| Method | Returns | Example (real output) |
|---|---|---|
| `static resolve(string $dataType, array $column = []): array` | `['class' => ..., 'args' => [...]]` | `resolve('enum', ['COLUMN_TYPE' => "enum('draft','done')"])` gives `TypeEnum` with args `[['draft', 'done'], false]` |
| `static getClass(string $type, array $column = []): FieldType` | a type instance | `getClass('timestamptz')` is a `TypeDateTime` with `isWithTimezone()` true |
| `static getClassLiteral(string $type, array $column = []): string` | PHP source for generated classes | `getClassLiteral('decimal', ['NUMERIC_PRECISION' => 10, 'NUMERIC_SCALE' => 2])` gives `new Database\Field\TypeDecimal(10, 2)`; `getClassLiteral('int', ['COLUMN_TYPE' => 'int(10) unsigned'])` gives `new Database\Field\TypeInteger('int', true)` |

`$column` is the information_schema row (upper-case keys: `COLUMN_TYPE`, `NUMERIC_PRECISION`, `NUMERIC_SCALE`,
`ENUM_OPTIONS`); without it, sizes and options are read from a type string such as `"numeric(12,2)"`.

## Custom form elements and UI components

These are covered where the classes live; this is the map:

- **Form elements.** Extend `Text` (or a subclass), override `drawUpdateMode()` / `getDisplayValue()`, and return
  `$this->renderWrapper($control)` so label, hint, addons and errors stay consistent. A worked money input is in
  [07-form-inputs.md](07-form-inputs.md#writing-a-custom-element). Choose it per field with
  `Field::setFormElementClass()`, per form with `FormLayout::element()`, or in the column comment with
  `"form":{"element":"App\\Ui\\Money"}` ([07-form-inputs.md](07-form-inputs.md#choosing-the-element-of-a-field)).
  A type's `createFormElement()` (above) is the third way: every field of that type gets the element.
- **Relation pickers.** A many-to-many picker implements `Laf\UI\Form\RelationElementInterface`; start from one of
  the three LAF pickers ([08-relations.md](08-relations.md#the-three-pickers)).
- **UI components.** Implement `Laf\UI\ComponentInterface` with `Laf\UI\Traits\ComponentTrait`, write `draw()` and
  `getComponentCssControlClass()`, escape with `Util::e()` / `Util::attr()`
  ([11-pages-and-ui.md](11-pages-and-ui.md#writing-your-own-component)). A component that only arranges others is
  shorter as a subclass of `Div`. How a component can draw the wrapper settings is shown
  [below](#wrapper-settings-in-your-own-component).
- **Autocomplete sources and "create new".** `setLookupCallback()`, `setLabelCallback()` and `setCreateCallback()`
  on `Autocomplete` replace the default table search and insert
  ([08-relations.md](08-relations.md#searching-columns-custom-sources-labels),
  [08-relations.md](08-relations.md#create-new-from-the-list)).

## Logging what a model does

Every model can carry a PSR-3 logger (`Psr\Log\LoggerInterface`, for example Monolog, which LAF already depends
on). Without one, nothing is logged and nothing costs anything. With one, the model reports its own work and you
can add your own entries:

| Method | What it does |
|---|---|
| `setLogger(?LoggerInterface $logger): static` | attach a logger (writes a `debug` entry "Loading Logger: *class*"); `null` detaches it |
| `getLogger(): ?LoggerInterface` | the attached logger, `null` by default |
| `addLoggerDebug(string $message, $context = [])` | an entry at `debug` |
| `addLoggerInfo()`, `addLoggerNotice()`, `addLoggerWarning()`, `addLoggerError()`, `addLoggerCritical()`, `addLoggerAlert()`, `addLoggerEmergency()` | the same at the other seven PSR-3 levels |

All `addLogger*()` methods return nothing and do nothing without a logger. The context may be an array or a single
value (a scalar becomes `[value]`). They all go through the private `log($level, $message, $context)`, which is
not part of the API.

What the model logs by itself:

- `debug`: every SELECT (loading by key), INSERT, UPDATE and DELETE statement with its bindings, "INSERT done" / "UPDATE done" / "DELETE done"
  with the key and affected rows, an update with nothing to write ("No fields to update, returning"), a SELECT that
  found no row, `reset()`;
- `warning`: a hard delete that found the row already gone;
- `error`: a failed statement (SQL, bindings, driver message), `getField()` / `getFieldValue()` with an unknown
  column name, a soft delete on a table without a status column, a failed audit-log write.

A real run with Monolog writing to stdout:

```php
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use MyApp\Company;

$handler = new StreamHandler('php://stdout', Logger::DEBUG);
$handler->setFormatter(new LineFormatter("%level_name% %message% %context%\n"));
$logger = new Logger('model', [$handler]);

$company = (new Company())->setLogger($logger);
$company->setNameVal('Ch19 Logging Ltd');
$company->store();
$company->setPhoneVal('+383 44 000 000');
$company->store();
$company->store();                                        // nothing changed
$company->addLoggerInfo('Company onboarded', ['id' => $company->getIdVal()]);
$company->addLoggerWarning('Scalar context', 42);
$company->hardDelete();
```

```text
DEBUG Loading Logger: Monolog\Logger []
DEBUG Laf\Database\BaseObject::insert []
DEBUG INSERT SQL ["INSERT INTO `company` (\n\t  `name`\n\t, `created_on`\n\t, `created_by`\n)\nVALUES (\n\t  :name\n\t, :created_on\n\t, :created_by\n)",{":name":"Ch19 Logging Ltd",":created_on":"2026-10-14 19:30:13",":created_by":1}]
DEBUG INSERT done {"id":17,"affected":1}
DEBUG Laf\Database\BaseObject::update [17]
DEBUG UPDATE SQL ["UPDATE `company`\nSET\n\t  `phone` = :phone\n\t, `updated_on` = :updated_on\n\t, `updated_by` = :updated_by\nWHERE\n\t`id` = :pk_id",{":phone":"+383 44 000 000",":updated_on":"2026-10-14 19:30:13",":updated_by":1,":pk_id":17}]
DEBUG UPDATE done {"affected":1}
DEBUG Laf\Database\BaseObject::update [17]
DEBUG No fields to update, returning []
INFO Company onboarded {"id":17}
WARNING Scalar context [42]
DEBUG Laf\Database\BaseObject::hardDelete [17]
DEBUG DELETE SQL ["DELETE FROM `company`\nWHERE `id` = :pk_id",{":pk_id":17}]
DEBUG DELETE done {"affected":1}
```

Any PSR-3 implementation works; a minimal one:

```php
use Psr\Log\AbstractLogger;

class EchoLogger extends AbstractLogger
{
    public function log($level, $message, array $context = []): void
    {
        echo strtoupper((string)$level), ' ', $message, "\n";
    }
}

$company = (new Company())->setLogger(new EchoLogger());   // DEBUG Loading Logger: EchoLogger
$company->addLoggerNotice('hello');                         // NOTICE hello
$company->setLogger(null);
$company->addLoggerError('dropped silently');               // nothing
```

The logger belongs to one object, and there is no global default. To log every `Employee`, attach it in the leaf
constructor, the same place as a custom type (the logger then also sees the load, because `select()` runs after
it):

```php
use Psr\Log\LoggerInterface;

class Employee extends Base\BaseEmployee
{
    private static ?LoggerInterface $modelLogger = null;

    public static function useLogger(?LoggerInterface $logger): void
    {
        self::$modelLogger = $logger;
    }

    public function __construct($id = null)
    {
        parent::__construct();
        if (self::$modelLogger !== null) {
            $this->setLogger(self::$modelLogger);
        }
        if ($id !== null && $id !== '') {
            $this->select($id);
        }
    }
}
```

> **Note:** the debug entries contain the bound values, so personal data (names, e-mail addresses) ends up in the
> log. Log at `info` or above in production, or add a Monolog processor that masks values. The connection has its
> own logger for every statement it runs, including queries that do not go through a model (`Db::setLogger()`,
> [13-database.md](13-database.md#psr-3-logger-query-log-and-timing)); use that one for query tracing and the
> model logger for what a model did.

## Wrapper styling and attributes

Every component has two sets of classes, styles and attributes: the **element's own** (`addCssClass()`,
`addCssStyleItem()`, `addAttribute()`, see [11-pages-and-ui.md](11-pages-and-ui.md#componentinterface-componenttrait-and-draw-modes))
and the **wrapper's**. Form inputs draw a wrapper: the `<div id='..._container'>` around the label, the control,
the hint and the error text. That div is where layout belongs (grid columns, spacing, a border marking a group),
while the element's own classes go on the `<input>` itself.

### Wrapper classes and style

| Method | What it does |
|---|---|
| `addWrapperCssClass(string $class)` | add a class once (a class already present is not repeated) |
| `setWrapperCssClasses(array $classes)` | replace all wrapper classes |
| `getWrapperCssClasses(): array` | the wrapper classes |
| `hasWrapperCssClass(string $class): bool` | one class present |
| `removeWrapperCssClass(string $class)` | remove one class |
| `addWrapperCssStyleItem(string $property, ?string $value)` / alias `setWrapperCssStyleItem()` | set one style property |
| `setWrapperCssStyles(array $style)` | replace all wrapper style items (property => value) |
| `getWrapperCssStyles(): array` | the style items, including empty ones |
| `getWrapperCssStyleItem(string $property): ?string` | one value; `null` when unset or empty |
| `hasWrapperCssStyleItem(string $property): bool` | set and not empty |
| `removeWrapperCssStyle(string $property)` | remove one property |

The input adds `mb-3` and the row display mode (`row` by default, `''` for `FormRowDisplayMode::INLINE`) before
your classes, and `d-none` when the element is hidden. Style items with a `null` or `''` value are kept in the
array but not drawn. All setters return the component, so they chain.

```php
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormRowDisplayMode;
use MyApp\Employee;

$employee = new Employee();
$el = $employee->getFirstNameFormElement();
$el->setDrawMode(DrawMode::UPDATE);
$el->setFormRowDisplayMode(FormRowDisplayMode::INLINE);         // label above the control, no col-sm-2/10
$el->setWrapperCssClasses(['col-md-6', 'first-name']);
$el->addWrapperCssClass('border-start');
$el->addWrapperCssClass('col-md-6');                            // already there: not repeated
$el->setWrapperCssStyles(['padding-left' => '.5rem', 'background' => null]);
$el->addWrapperCssStyleItem('border-color', 'var(--bs-primary)');
$el->setAutofocus(true)->setAutocomplete('given-name')->setInputMode('text')->setDataAttribute('track', 'name');

$el->getWrapperCssClasses();                // ['col-md-6', 'first-name', 'border-start']
$el->hasWrapperCssClass('first-name');      // true
$el->getWrapperCssStyles();                 // ['padding-left' => '.5rem', 'background' => null, 'border-color' => 'var(--bs-primary)']
$el->getWrapperCssStyleItem('background');  // null
$el->hasWrapperCssStyleItem('background');  // false
echo $el->draw();
```

```html
<div id='svefg_anzr_container' style='padding-left:.5rem;border-color:var(--bs-primary)' class='mb-3  col-md-6 first-name border-start'>
    <label id='svefg_anzr_label' for='svefg_anzr' class='col-form-label'>First Name* :</label>
        <input class='Laf-UI-Form-Input-Text form-control' style=''
            type="text" name="svefg_anzr" id="svefg_anzr" maxlength="50" required="required"
            placeholder="First Name" autofocus="autofocus" autocomplete="given-name"
            inputmode="text" data-track="name" />
</div>
```

```php
$el->removeWrapperCssClass('border-start')->removeWrapperCssStyle('border-color');
$el->getWrapperCssClasses();                // ['col-md-6', 'first-name']
```

The same wrapper is drawn in VIEW mode around the read-only value box. For a whole form, put the layout in
`FormLayout` ([06-forms.md](06-forms.md#laying-out-the-form-formlayout)); the wrapper methods are for the odd field
that needs something the layout does not give, or for elements you place by hand.

### Wrapper attributes

| Method | What it does |
|---|---|
| `addWrapperAttribute(string $key, ?string $value)` / alias `setWrapperAttribute()` | set one entry |
| `setWrapperAttributes(array $attributes)` | replace the whole bag |
| `getWrapperAttributes(): array` | the bag, including empty entries |
| `getWrapperAttribute(string $key): ?string` | one value; `null` when unset or empty |
| `hasWrapperAttribute(string $key): bool` | set and not empty |
| `removeWrapperAttribute(string $attribute)` | remove one entry |

For form inputs the wrapper attribute bag is **storage, not markup**: `setLabel()` / `getLabel()` and `setHint()` /
`getHint()` keep the label and the hint there, and the input does not draw the other entries on the `_container`
div:

```php
$el->addWrapperAttribute('data-section', 'personal');
$el->getWrapperAttributes();                // ['label' => 'First Name', 'hint' => null, 'data-section' => 'personal']
$el->getWrapperAttribute('label');          // 'First Name'
$el->hasWrapperAttribute('data-section');   // true, but no data-section attribute in the drawn HTML

$last = $employee->getLastNameFormElement();
$last->setWrapperAttributes(['data-x' => '1']);
$last->getLabel();                          // null: the label was in the bag that was replaced
// drawn: <label id='ynfg_anzr_label' for='ynfg_anzr' class='col-sm-2 col-form-label'></label>
```

> **Note:** never call `setWrapperAttributes()` on a form element: it replaces the label and the hint. Use
> `addWrapperAttribute()` / `removeWrapperAttribute()` for your own entries, and put real attributes for the
> element on the element (`addAttribute()`, `setDataAttribute()`). Containers and the components of
> [11-pages-and-ui.md](11-pages-and-ui.md) ignore all wrapper settings; your own component may draw them (below).

### Input attribute getters

Form elements keep their HTML attributes in the attribute bag, so each setter has a getter that reads the bag
([07-form-inputs.md](07-form-inputs.md#options-every-input-has) lists the setters):

| Getter | Returns | Notes |
|---|---|---|
| `isAutofocus(): bool` | `autofocus` is on | `setAutofocus(bool)` |
| `isAutocomplete(): bool` | `false` only when `autocomplete` is explicitly `'off'` | browsers default to on; `getAutocomplete()` returns the token (`'given-name'`) |
| `getInputMode(): ?string` | the `inputmode` attribute | set by the field type (`decimal`, `numeric`) or `setInputMode()` |
| `getDataAttribute(string $name): ?string` | `data-{name}` | `setDataAttribute('track', 'name')` then `getDataAttribute('track')` gives `'name'` |
| `getMin()`, `getMax()`, `getStep()` | `int`, `float` or string (`'any'`, a 20-digit bound), `null` when unset | the type's attributes are copied in when the element is bound to its field: `getSalaryFormElement()->getStep()` is `0.01` |
| `getHeight(): ?int`, `getWidth(): ?int` | the `height` / `width` attributes | drawn as inline px style |
| `getRows(): ?int`, `getCols(): ?int` | `rows`, `cols` (TextArea) | `getNotesFormElement()->setRows(4)->setCols(40)` |

The type-level `getInputAttributes()` (see the [contract](#the-fieldtype-contract)) is where those defaults come
from; an attribute you set on the element yourself always wins.

### Label text: suffix and required marker

`Text::getLabelSuffix()` and `Text::getRequiredMarker()` return the text drawn after every label (`' :'`) and after
the label of a required field (`'*'`); `Text::setLabelSuffix()` and `Text::setRequiredMarker()` change them for
**every** input in the process (they are static on `Text`, so `Checkbox::setLabelSuffix()` changes the same value).
Set them once in your bootstrap:

```php
use Laf\UI\Form\Input\Text;

Text::setLabelSuffix('');
Text::setRequiredMarker(' *');
// before: <label ...>E-mail* :</label>     after: <label ...>E-mail *</label>
// an optional field: <label ...>Birth Date</label>
```

Both values are escaped when drawn, so they cannot carry markup; style the marker with CSS if you want colour.

### Wrapper settings in your own component

`ComponentTrait` stores the wrapper settings for any component and offers the protected helpers
`getWrapperCssClassesForHtml()` and `getWrapperCssStyleForHtml()` (plus `getCssClassesForHtml()` for the element's
own classes); drawing them is up to the component. A panel that wraps its children in a `<section>` and uses the
wrapper settings for it:

```php
use Laf\UI\ComponentInterface;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Util;

final class Panel implements ComponentInterface
{
    use ComponentTrait;

    public function __construct(private string $title)
    {
    }

    public function draw(): ?string
    {
        $this->addCssClass($this->getComponentCssControlClass());
        $wrapperAttributes = '';
        foreach ($this->getWrapperAttributes() as $key => $value) {
            if ($this->hasWrapperAttribute($key)) {               // skips null and '' entries
                $wrapperAttributes .= ' ' . $key . '="' . Util::attr($value) . '"';
            }
        }
        $body = '';
        foreach ($this->getComponents() as $child) {
            $body .= $child->draw();
        }
        return '<section class="' . Util::attr($this->getWrapperCssClassesForHtml()) . '" style="'
            . Util::attr($this->getWrapperCssStyleForHtml()) . '"' . $wrapperAttributes . '>'
            . '<h2 class="' . Util::attr($this->getCssClassesForHtml()) . '">' . Util::e($this->title) . '</h2>'
            . $body . '</section>';
    }

    public function getComponentCssControlClass(): string
    {
        return 'app-panel';
    }
}

$panel = (new Panel('Team <A>'))
    ->addCssClass('h5')
    ->addWrapperCssClass('card')->addWrapperCssClass('p-3')
    ->addWrapperCssStyleItem('max-width', '40rem')
    ->addWrapperAttribute('aria-label', 'Team "A"')
    ->addWrapperAttribute('data-empty', '');
$panel->addComponent(new HtmlContainer('<p>Members</p>'));
echo $panel->draw();
```

```html
<section class="card p-3" style="max-width:40rem" aria-label="Team &quot;A&quot;"><h2 class="h5 app-panel">Team &lt;A&gt;</h2><p>Members</p></section>
```

> **Note:** attribute *names* are not escaped by this loop; only let your own code choose them.

## Exceptions you can build yourself

The ORM wraps driver errors in two exception classes. Both have a static factory you can use in your own data code
(a repository that runs hand-written SQL through PDO, an import script) so that callers catch the same types as
for model writes:

| Factory | Gives | Carries |
|---|---|---|
| `DatabaseException::fromPdo(\PDOException $ex, string $sql = '', array $bindings = []): DatabaseException` | a failed statement; the message is the driver's | `getSql()`, `getBindings()` (PDO type pairs `[value, PDO::PARAM_*]` reduced to the value), `getSqlState()`, `getDriverCode()`, `isUniqueViolation()`, `isForeignKeyViolation()`, `getPrevious()` = `$ex` |
| `DbConnectException::fromPdo(\PDOException $ex, string $dsn = ''): DbConnectException` | a connection that could not be opened; extends `PDOException` | message `Database connection failed [dsn]: ...`, `getCode()` and `errorInfo` copied from `$ex`, `getPrevious()` = `$ex` |

```php
use Laf\Database\Db;
use Laf\Exception\DatabaseException;
use Laf\Exception\DbConnectException;

$sql = 'INSERT INTO company (name) VALUES (:name)';
try {
    Db::getInstance()->getConnection()->prepare($sql)->execute(['name' => null]);
} catch (\PDOException $ex) {
    $error = DatabaseException::fromPdo($ex, $sql, ['name' => [null, \PDO::PARAM_NULL]]);
    // getMessage(): SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'name' cannot be null
    // getSql(): INSERT INTO company (name) VALUES (:name)
    // getBindings(): ['name' => null]; getSqlState(): '23000'; getDriverCode(): 1048; isUniqueViolation(): false
    throw $error;
}

try {
    new \PDO('mysql:host=127.0.0.1;port=1;dbname=x', 'root', '');
} catch (\PDOException $ex) {
    throw DbConnectException::fromPdo($ex, 'mysql:host=127.0.0.1;port=1');
    // Database connection failed [mysql:host=127.0.0.1;port=1]: SQLSTATE[HY000] [2002] No connection could be
    // made because the target machine actively refused it   (getCode() 2002, still an instanceof \PDOException)
}
```

> **Note:** the DSN goes into the message, and messages end up in logs and sometimes on error pages. A DSN normally
> holds no password (PDO takes it separately); do not pass one that does. `Db` itself throws these for you
> ([13-database.md](13-database.md#errors)); the full exception list is in
> [16-settings-locale-utilities.md](16-settings-locale-utilities.md#exceptions).

## Extension points at a glance

| Extension point | What you plug in | Covered in |
|---|---|---|
| Model hooks `beforeInsert()`, `afterInsert()`, `beforeUpdate()`, `afterUpdate()`, `beforeDelete($mode)`, `afterDelete($mode)`, `afterLoad()` | protected methods in the leaf class around every write and load | [03-models.md](03-models.md#hooks) |
| `validateRecord(): array` | rules across fields, returned as field => message(s) | [05-validation.md](05-validation.md#record-rules-validaterecord) |
| `Field::addValidator()`, `setPattern()`, `setMinValue()` / `setMaxValue()` | per-field rules | [05-validation.md](05-validation.md#field-rules) |
| Custom `FieldType` via `Field::setType()` | conversion, binding, comparison, validation, input of a kind of value | [this chapter](#custom-field-types) |
| `getFormLayout(): ?FormLayout` / `configureForm(Form $form)` | the model's form layout, last-minute form changes | [06-forms.md](06-forms.md#layout-and-hooks-on-the-model) |
| Custom form element (`setFormElementClass()`, `FormLayout::element()`, comment `"element"`) | your own input class | [07-form-inputs.md](07-form-inputs.md#writing-a-custom-element) |
| Custom component (`ComponentInterface` + `ComponentTrait`) | your own markup in pages | [11-pages-and-ui.md](11-pages-and-ui.md#writing-your-own-component) |
| Autocomplete `setLookupCallback()`, `setLabelCallback()`, `setCreateCallback()` | your own search source, labels and "create new" | [08-relations.md](08-relations.md#searching-columns-custom-sources-labels) |
| Generator config `page_factory` / `page_factory_method` | the class that builds the page shell instead of `{namespace}\Factory::GeneralPage()` | [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md#page_factory-and-page_factory_method) |
| Generator config `module_for_table` (array or callable) | which module a related-records tab links to | [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md#module_for_table) |
| Label translations (`--labels=FILE`, `setLabelTranslations()`) | the words on generated pages | [02-code-generation.md](02-code-generation.md#page-labels) |
| JSON column comments (`fields`, `form`) | setters and form hints written into the Base class | [02-code-generation.md](02-code-generation.md#json-column-comments) |
| `Document::setModelClass()`, `Document::setNotFoundUrl()` | another model for stored files, the not-found page | [15-files-and-documents.md](15-files-and-documents.md#the-document-table) |
| `File::setDocumentUrlBuilder()` | the links of the file input | [15-files-and-documents.md](15-files-and-documents.md#files-on-forms-the-file-input) |
| `SqlErrorLoggerInterface` + `Db::setSqlErrorLogger()` | where failed statements are recorded | [13-database.md](13-database.md#sql-error-log) |
| `Db::setLogger()`, `enableQueryLog()` | PSR-3 logging and timing of every statement | [13-database.md](13-database.md#psr-3-logger-query-log-and-timing) |
| `BaseObject::setLogger()` | PSR-3 logging of one model's work | [this chapter](#logging-what-a-model-does) |
| `BaseObject::setActiveUserId()`, `<package>\Person::getLoggedUserId()` | who writes are attributed to | [14-audit-log.md](14-audit-log.md#who-made-the-change) |
| `logAuditEntry()` (protected) | write your own audit entries | [14-audit-log.md](14-audit-log.md#writing-entries-yourself) |
| `Text::setLabelSuffix()`, `Text::setRequiredMarker()` | label text of every input | [this chapter](#label-text-suffix-and-required-marker) |
| PhpGrid `setJsCallback()` / `setRowLevelJsCallback()`, column inner/outer styling | how grid cells and rows are drawn | [10-grids.md](10-grids.md#javascript-callbacks) |
| `Settings` (`load()`, `loadFromFile()`, `loadFromEnvironment()`) | configuration from your own sources | [16-settings-locale-utilities.md](16-settings-locale-utilities.md#loading-configuration) |

## See also

- [03-models.md](03-models.md) — fields, the built-in field types, hooks, the BaseObject reference
- [05-validation.md](05-validation.md) — how type messages combine with field and record rules
- [07-form-inputs.md](07-form-inputs.md) — every input, its options, a custom element
- [11-pages-and-ui.md](11-pages-and-ui.md) — `ComponentInterface`, `ComponentTrait`, writing a component
- [13-database.md](13-database.md) — the connection's logger, SQL error log, error types
- [02-code-generation.md](02-code-generation.md) — the type mapping and the `setType` column comment
