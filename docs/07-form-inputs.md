# Form inputs

Every field of a model has one input element: a class under `Laf\UI\Form\Input` that draws the Bootstrap 5 control
in INSERT and UPDATE mode and a read-only value box in VIEW mode. LAF picks the element from the column's type and
role (a varchar is a text box, a boolean a checkbox, a foreign key a select, an auto-increment key a hidden input),
and you can choose another one per field from the column comment, the form layout or code. This chapter lists every
input and control class, what each draws and which options it has, how to choose an element, and how to write your
own. Forms as a whole are in [06-forms.md](06-forms.md); the foreign-key `Autocomplete` and the many-to-many pickers
are in [08-relations.md](08-relations.md).

On this page:

- [How an element is created](#how-an-element-is-created)
- [Choosing the element of a field](#choosing-the-element-of-a-field)
- [Markup shared by every input](#markup-shared-by-every-input)
- [Options every input has](#options-every-input-has)
- [Text-like inputs: Text, Email, Url, Search, Tel, Color, Password](#text-like-inputs-text-email-url-search-tel-color-password)
- [Numbers: Integer, Number, Range](#numbers-integer-number-range)
- [TextArea](#textarea)
- [Hidden](#hidden)
- [Checkbox and switch](#checkbox-and-switch)
- [Choices: Select, Radio, CheckboxCollection](#choices-select-radio-checkboxcollection)
- [Dates and times: Date, DateTime, Time](#dates-and-times-date-datetime-time)
- [File](#file)
- [Relation inputs (see 08-relations.md)](#relation-inputs-see-08-relationsmd)
- [Buttons](#buttons)
- [Writing a custom element](#writing-a-custom-element)
- [Reference](#reference)
- [See also](#see-also)

The examples run against the shared schema; the HTML is real output with whitespace collapsed. Each element is drawn on
its own with `setDrawMode()` (inside a form, the form passes its draw mode down; an element drawn alone without a mode
draws as VIEW).

## How an element is created

`Field::getFormElement()` creates the element on first use and **caches it on the field**; the generated getters
(`$employee->getFirstNameFormElement()`), `getForm()` and `FormElementFactory::create($field)` all return that cached
instance. When the element is bound (`setField()`), it copies the field's metadata into its attribute bag: name and id
(the rot13 column name), value, label, placeholder, hint, required, `maxlength`/`minlength` (character columns),
`min`/`max`, the field's own attributes (`Field::setAttribute()`), and the type's input attributes (`step`,
`inputmode`, `min`, `max`) unless already set.

The default element, decided in `AbstractFieldType::getFormElement()`:

| Column | Element | Notes |
|---|---|---|
| foreign key to the document table | `File` | see [15-files-and-documents.md](15-files-and-documents.md) |
| foreign key, referenced table larger than `form.select_autocomplete_threshold` | `Autocomplete` | threshold 0 (default) = off; see 08-relations.md |
| any other foreign key | `Select` | options = referenced table, key => display field |
| auto-increment primary key | `Hidden` | |
| `TypeVarchar`, `TypeChar`, `TypeUuid`, `TypeBlob` | `Text` | |
| `TypeDecimal`, `TypeFloat` | `Text` with `inputmode="decimal"` and a `step` | text so locale numbers like `1.234,56` are accepted; `Number` is opt-in |
| `TypeText`, `TypeJson` | `TextArea` | |
| `TypeInteger` | `Integer` (`type="number"`, `step="1"`, min/max of the column width) | |
| `TypeBool` | `Checkbox` | |
| `TypeDate` / `TypeDateTime` / `TypeTime` | `Date` / `DateTime` / `Time` | |
| `TypeEnum` | `Select` with the enum values (`CheckboxCollection` for a multiple / SET column) | |

Because the element copies the metadata **when it is bound**, change field metadata (`setLabel()`, `setHint()`,
`setRequired()`) before the element is created, or change the element itself afterwards.

> **Note:** the element is shared. A change made to `$employee->getSalaryFormElement()` shows up in every form of that
> `$employee` object, and a `FormLayout` re-applies the draw mode and hidden state it owns each time it is applied.

## Choosing the element of a field

Four ways, from most to least permanent:

1. **Column comment** (survives regeneration, applies everywhere):

   ```sql
   ALTER TABLE `employee` MODIFY `email` varchar(150) NOT NULL
       COMMENT '{"fields":{"setLabel":"E-mail"},"form":{"element":"Email"}}';
   ```

   The generator writes `$field->setFormOptions(['element' => 'Email'])` into the Base class (see
   [02-code-generation.md](02-code-generation.md)).

2. **Form layout** (per model or per page):

   ```php
   FormLayout::auto($this->getTable())->element('salary', 'Number');
   ```

3. **On the field, in code**:

   ```php
   $employee->getEmailFld()->setFormElementClass('Email');             // short name under Laf\UI\Form\Input
   $employee->getSalaryFld()->setFormElementClass(\App\Ui\Money::class); // or a fully qualified class
   $employee->getEmailFld()->setFormElementClass(null);                // back to the type's choice
   ```

   A cached element of another class is dropped, so the next `getFormElement()` builds the new one.

4. **A ready element**, bound to the field (replaces the cached one):

   ```php
   use Laf\UI\Form\FormElementFactory;
   use Laf\UI\Form\Input\Radio;

   $radio = FormElementFactory::createOfType(Radio::class, $employee->getCompanyIdFld());
   // same as: $employee->getCompanyIdFormElement(new Radio());
   ```

Short names are resolved by `FormLayout::resolveElementClass()`: the name as a class, then
`Laf\UI\Form\Input\{name}`, then `Laf\UI\Form\Input\ManyToMany{name}`. Anything that is not a
`FormElementInterface` throws `\InvalidArgumentException`.

A **foreign key** keeps one value and needs its options, so only `Select` and its subclasses (`Radio`,
`CheckboxCollection`, `Autocomplete`) are accepted:

```php
$employee->getCompanyIdFld()->setFormElementClass('Text');
// InvalidArgumentException: Field company_id is a foreign key: its element must be a Select, "Laf\UI\Form\Input\Text" given
```

## Markup shared by every input

`Text` is the base class of every input and draws the wrapper the others reuse (`renderWrapper()`):

```html
<div id='{id}_container' style='' class='mb-3 row'>                 <!-- 'row' in ROW display mode -->
    <label id='{id}_label' for='{id}' class='col-sm-2 col-form-label'>Label* :</label>
    <div class='col-sm-10'>                                          <!-- only in ROW display mode -->
        <input ...>                                                  <!-- the control (input group with addons) -->
        <datalist id='{id}_list'>...</datalist>                      <!-- setSuggestions() -->
        <div id='{id}_feedback' class='invalid-feedback d-block'>message</div>   <!-- errors, edit modes -->
        <small id='{id}_hint' class='form-text text-muted'>hint</small>          <!-- hint, edit modes -->
    </div>
</div>
```

- `{id}` is the rot13 column name (`svefg_anzr` for `first_name`).
- The label is the escaped label, then the required marker (`*`, `Text::setRequiredMarker()`), then the suffix (` :`,
  `Text::setLabelSuffix()`). Both are process-wide statics.
- `FormRowDisplayMode::ROW` (`'row'`, the default of an element) puts the label in `col-sm-2` and the control in
  `col-sm-10`; `FormRowDisplayMode::INLINE` (`''`) stacks them. Inside a form, the form sets the mode on its
  components (a multi-column layout uses INLINE).
- With errors, the control gets `is-invalid`, `aria-invalid='true'` and an `aria-describedby` pointing at the feedback
  (and the hint).
- Every element adds a css class naming itself (`Laf-UI-Form-Input-Text`, `Laf-UI-Form-Input-Date`, ...), handy for
  JavaScript and styling.
- VIEW mode replaces the control by a value box
  (`form-control-plaintext border border-secondary border-1 bg-light rounded p-2`) with the **display value**, escaped,
  line breaks as `<br />`, and no hint, errors, addons or suggestions. Only `title`, `dir`, `lang`, `data-*` and `aria-*`
  attributes are copied to the box.
- Drawing never changes the element: drawing in one mode and then another gives the same result as drawing the second
  mode alone.

Attribute values are escaped at output (`Util::attr()`); attribute names that are not valid HTML names are dropped,
and empty or null attributes are not drawn.

## Options every input has

From `FormElementTrait` and `ComponentTrait` (all return the element, so they chain at runtime; their declared return
type is `ComponentInterface`):

| Method | HTML / effect |
|---|---|
| `setLabel(?string)` / `getLabel()` | the label text (escaped) |
| `setHint(?string)` / `getHint()` | small muted text under the control (escaped), edit modes only |
| `setPlaceholder(?string)` | `placeholder` |
| `setRequired(bool)` / `isRequired()` | `required` + the marker in the label |
| `setDisabled(bool)` / `isDisabled()` | `disabled`: posts nothing, not in the signed field list |
| `setReadonly(bool)` / `isReadonly()` | HTML `readonly` attribute (the browser still posts the value) |
| `setHidden(bool)` / `isHidden()` | `hidden` attribute and wrapper `d-none` (still posts) |
| `setExcluded(bool)` / `isExcluded()` | not processed, not in the signed field list |
| `setValue(?string)` / `getValue()` | `value`; normally mirrored from the field |
| `setMaxLength(?int)`, `setMinLength(?int)` | `maxlength`, `minlength` |
| `setMin()`, `setMax()`, `setStep()` (int, float, numeric string or null) | `min`, `max`, `step` |
| `setPattern(?string)` | `pattern` (browser-side; server rules are `Field::setPattern()`, see 05-validation.md) |
| `setInputMode(?string)` | `inputmode` (`numeric`, `decimal`, `email`, `tel`, `url`, `search`) |
| `setAutocomplete(bool\|string\|null)` | `autocomplete`; true/false give `on`/`off`, a string is the token (`email`, `new-password`) |
| `setAutofocus(bool)` | `autofocus` |
| `setTitle(?string)` | `title` (tooltip) |
| `setDataAttribute(string $name, ?string)` | `data-{name}` |
| `setAriaLabel(?string)` | `aria-label` |
| `setSize(?int)`, `setRows(?int)`, `setCols(?int)` | `size`, `rows`, `cols` |
| `setMultiple(bool)` | `multiple` (Select) |
| `setHeight(?int)`, `setWidth(?int)` | inline `height` / `width` in px |
| `setType(?string)`, `setName(?string)`, `setId(?string)` | `type`, `name`, `id` (set from the field; change with care) |
| `setErrors(array)`, `addError(string)`, `getErrors()`, `hasErrors()`, `clearErrors()` | validation messages (the form manages them) |
| `addAttribute()`, `setAttribute()`, `removeAttribute()`, `getAttribute()`, `getAttributes()` | the raw attribute bag |
| `addCssClass()`, `addCssClasses(...)`, `removeCssClass()`, `setCssStyleItem()` | classes / style of the control |
| `addWrapperCssClass()`, `setWrapperCssStyleItem()`, `setWrapperAttribute()` | classes / style / attributes of the `_container` div |
| `setDrawMode(?string)`, `setFormRowDisplayMode(string)` | see above |

`Text` adds, for every input:

| Method | What it does | Notes |
|---|---|---|
| `setPrefix(?string)` / `setSuffix(?string)` | a text addon before / after the control (input group) | escaped; null or `''` removes it |
| `setPrefixHtml(?string)` / `setSuffixHtml(?string)` | the same with raw HTML (an icon, a button) | **not escaped** |
| `getPrefix()` / `getSuffix()` / `hasAddons()` | the addon HTML | |
| `setSuggestions(array)` / `getSuggestions()` / `hasSuggestions()` | a `<datalist>` the browser offers while typing | duplicates and empties removed |
| `getSuggestionListId()` | `{id}_list` | |
| `setInvalidValueErrorMessage(?string)` / `getInvalidValueErrorMessage()` | copied from the field | |
| `getDisplayValue()` / `getDisplayValueForHtml()` / `getValueForHtml()` | what VIEW mode shows | raw only when the field allows HTML |
| `drawViewMode()` / `drawUpdateMode()` | draw one mode | INSERT draws like UPDATE |
| `Text::setLabelSuffix()`, `setRequiredMarker()` and getters | process-wide label text | static |
| `Text::valueToString(mixed)` | a field value as an attribute string | bools `1`/`0`, arrays JSON |

Prefix, suffix, suggestions and hint together:

```php
$el = $employee->getFirstNameFormElement();
$el->setPrefix('Mr/Ms')
   ->setSuffixHtml('<i class="fa fa-user"></i>')
   ->setSuggestions(['Ada', 'Grace', 'Ada'])
   ->setHint('As on the passport');
echo $el->setDrawMode(DrawMode::INSERT)->draw();
```

```html
<div id='svefg_anzr_container' style='' class='mb-3 row'>
<label id='svefg_anzr_label' for='svefg_anzr' class='col-sm-2 col-form-label'>First Name* :</label>
<div class='col-sm-10'>
<div class='input-group'><span class='input-group-text'>Mr/Ms</span><input class='Laf-UI-Form-Input-Text form-control' style=''
type="text" name="svefg_anzr" id="svefg_anzr" value="Ada" maxlength="50" required="required" placeholder="First Name"
list="svefg_anzr_list" aria-describedby='svefg_anzr_hint' /><span class='input-group-text'><i class="fa fa-user"></i></span></div>
<datalist id='svefg_anzr_list'><option value='Ada'></option><option value='Grace'></option></datalist>
<small id='svefg_anzr_hint' class='form-text text-muted'>As on the passport</small>
</div>
</div>
```

> **Note:** `setReadonly(true)` only adds the HTML attribute: the browser still posts the value and the form applies
> it. To make a field truly read-only in an edit form use `FormLayout::readOnly()` (the element is drawn in VIEW mode
> and is left out of the signed field list) or `setDisabled(true)`.

## Text-like inputs: Text, Email, Url, Search, Tel, Color, Password

All extend `Text` and differ only in the `type` they draw.

| Class | Draws | Notes |
|---|---|---|
| `Text` | `<input type="text">` | default for character columns |
| `Email` | `type="email"` | browser checks the format; add `Field::setPattern()` for a server rule |
| `Url` | `type="url"` | browser checks the format |
| `Search` | `type="search"` | browser search styling |
| `Tel` | `type="tel"` + `inputmode="tel"` (unless set) | no format enforced |
| `Color` | `type="color"` | value `#rrggbb` |
| `Password` | `type="password"`, **never prints the stored value** | VIEW shows `••••••` when a value exists |

```php
$employee->getEmailFld()->setFormElementClass('Email');
$company->getPhoneFld()->setFormElementClass('Tel');
```

```html
<input class='Laf-UI-Form-Input-Email form-control' style='' type="email" name="rznvy" id="rznvy"
       value="ada.forms@example.com" maxlength="150" required="required" placeholder="Email" />

<input class='Laf-UI-Form-Input-Tel form-control' style='' type="tel" name="cubar" id="cubar"
       maxlength="30" placeholder="Phone" inputmode="tel" />

<input class='Laf-UI-Form-Input-Color form-control' style='' type="color" name="pbybe_k" id="pbybe_k" value="#ff8800" />

<input class='Laf-UI-Form-Input-Password form-control' style='' type="password" name="cnffjbeq_k" id="cnffjbeq_k" />
```

> **Note:** an empty password field posts `''`, which the form applies like any other value. For a "change password"
> field, take the value out of the request yourself and hash it, or exclude the column from the form and handle it in
> your own code; never store the raw value.

## Numbers: Integer, Number, Range

| Class | Draws | Notes |
|---|---|---|
| `Integer` | `type="number"` with `step="1"`, `inputmode="numeric"` and the column's `min`/`max` | default for integer columns |
| `Number` | `type="number"`; `step` from the type (`0.01` for `decimal(10,2)`) or `any` | opt-in for decimal/float; the browser only accepts `.` as decimal point |
| `Range` | `type="range"` with class `form-range` (no `form-control`), between `min` and `max` | VIEW shows the number; no suggestions |

```php
$employee->getSalaryFld()->setFormElementClass('Number');
echo $employee->getSalaryFormElement()->setDrawMode(DrawMode::INSERT)->draw();
```

```html
<input class='Laf-UI-Form-Input-Number form-control' style='' type="number" name="fnynel" id="fnynel"
       value="4200.50" placeholder="Salary" inputmode="decimal" step="0.01" />
```

The default `Integer` for `employee.created_by` (an `int`):

```html
<input class='Laf-UI-Form-Input-Integer form-control' style='' type="number" name="perngrq_ol" id="perngrq_ol"
       value="1" placeholder="Created By" step="1" inputmode="numeric" min="-2147483648" max="2147483647" />
```

A `Range` on a `tinyint` field with explicit bounds:

```php
use Laf\Database\Field\Field;
use Laf\Database\Field\TypeInteger;
use Laf\UI\Form\FormElementFactory;
use Laf\UI\Form\Input\Range;

$level = (new Field())->setName('level')->setLabel('Level')->setType(new TypeInteger('tinyint'));
$level->setValue(3);
$range = FormElementFactory::createOfType(Range::class, $level);
$range->setMin(0)->setMax(10);
```

```html
<input class='Laf-UI-Form-Input-Range form-range' style='' type="range" name="yriry" id="yriry"
       value="3" step="1" inputmode="numeric" min="0" max="10" />
```

## TextArea

`<textarea>` for `text` and `json` columns, 5 rows unless `setRows()` says otherwise. The value is escaped inside the
tag; `placeholder`, `maxlength`, `minlength` and `autocomplete` are kept, other input-only attributes are dropped.

```php
echo $employee->getNotesFormElement()->setRows(3)->setDrawMode(DrawMode::INSERT)->draw();
```

```html
<textarea class='Laf-UI-Form-Input-TextArea form-control' style='' name="abgrf" id="abgrf" placeholder="Notes" rows="3">Likes &lt;b&gt;maths&lt;/b&gt;
and engines</textarea>
```

## Hidden

`<input type="hidden">` inside a `d-none` div, used for auto-increment primary keys; never visible, in VIEW mode the
value box is drawn with `d-none`. `required` and the input-only attributes are not drawn.

```html
<div class='d-none'>
<input class='Laf-UI-Form-Input-Hidden' style='' type="hidden" name="vq" id="vq" value="2" />
</div>
```

## Checkbox and switch

A single checkbox for a boolean column. A hidden `0` precedes the box, so the field is always posted (`0` unchecked,
`1` checked) and the form can tell "unchecked" from "not on the form". The hidden input is left out for a disabled box.
`required` is never drawn: for a boolean column it means NOT NULL, which the hidden `0` satisfies, and on the box it
would stop the browser from submitting "false".

```php
$active = $employee->getIsActiveFormElement();
$active->setSwitch(true);                           // Bootstrap toggle switch
echo $active->setDrawMode(DrawMode::INSERT)->draw();
```

```html
<div class='form-check form-switch'>
<input type='hidden' name='vf_npgvir' value='0' />
<input class='Laf-UI-Form-Input-Checkbox form-check-input' style='' type="checkbox" name="vf_npgvir" id="vf_npgvir"
       value="1" role="switch" checked="checked" />
</div>
```

VIEW mode shows `Yes` / `No`; `Checkbox::setLabels('Nein', 'Ja')` changes them process-wide (note the order: no, yes).
`isChecked()` reads the value as a bool (`1`/`0`, `true`/`false`, `t`/`f`, `yes`/`no`, `on`/`off`; null and `''` are
false). `setSwitch(bool)` / `isSwitch()`.

## Choices: Select, Radio, CheckboxCollection

### Select

A `<select>` whose options come from a static list (`setOptions()`, enum columns) or from the table the foreign key
references: `key => display field`, filtered by the field's db selection criteria (`Field::setDbSelectionCriteria()`),
ordered by the display field, loaded once per element.

```php
use Laf\Database\Field\Field;
use Laf\Database\Field\TypeVarchar;
use Laf\UI\Form\FormElementFactory;
use Laf\UI\Form\Input\Select;

$size = (new Field())->setName('size')->setLabel('Size')->setType(new TypeVarchar());
$select = FormElementFactory::createOfType(Select::class, $size);
$select->setOptions(['s' => 'Small', 'm' => 'Medium', 'l' => 'Large'])
       ->setEmptyOptionLabel('-- choose --');
$size->setValue('m');
```

```html
<select class='Laf-UI-Form-Input-Select form-control form-select' style='' name="fvmr" id="fvmr"><option value=''>-- choose --</option>
<option value='s'>Small</option>
<option value='m' selected='selected'>Medium</option>
<option value='l'>Large</option>
</select>
```

VIEW mode shows the label of the selected value (`Medium`); for a foreign key it is the referenced row's display value
(`Forms Chapter Ltd` for `employee.company_id`). Option keys and labels are escaped.

`setMultiple(true)` posts `name[]` with an empty hidden value first (so clearing every selection is submitted too);
the form joins the values with commas (for SET or comma-separated varchar columns):

```html
<input type='hidden' name='pbybef[]' value='' />
<select class='Laf-UI-Form-Input-Select form-control form-select' style='' name="pbybef[]" id="pbybef" multiple="multiple">
<option value='r' selected='selected'>Red</option>
<option value='g' selected='selected'>Green</option>
</select>
```

| Method | What it does |
|---|---|
| `setOptions(array $valueToLabel)` | a static list; skips the foreign-key lookup |
| `getOptions()` | the static list, or the referenced table's rows (cached) |
| `hasStaticOptions()` / `getStaticOptions()` | the static list, if any |
| `clearOptionsCache()` | reload the referenced rows on the next draw |
| `setEmptyOption(bool)` / `hasEmptyOption()` | draw the empty first option (default true; not on multiple) |
| `setEmptyOptionLabel(?string)` / `getEmptyOptionLabel()` | its text (escaped); null = blank |
| `getSelectedLabel()` | label(s) of the current value, joined with `, ` |
| `isMultiple()` | multiple and **not** a foreign key |
| `setInline(bool)` / `isInline()` | Radio / CheckboxCollection: choices side by side |

> **Note:** a foreign key always holds one value, so `setMultiple()` is ignored on a foreign-key select. Several
> related rows come from a linker table and its pickers, see [08-relations.md](08-relations.md).

### Radio

One radio per option (`form-check` markup), the current value checked; `setInline(true)` puts them side by side. The
label's `for` points at the first radio. Works on foreign keys:

```php
$employee->getCompanyIdFld()->setFormElementClass('Radio');
$radio = $employee->getCompanyIdFormElement();
$radio->setInline(true);
```

```html
<label id='pbzcnal_vq_label' for='pbzcnal_vq_1' class='col-sm-2 col-form-label'>Company :</label>
<div class='col-sm-10'>
<div id='pbzcnal_vq' class='laf-choice-list'>
<div class='form-check form-check-inline'>
<input type='radio' id='pbzcnal_vq_1' name='pbzcnal_vq' value='4' class='Laf-UI-Form-Input-Radio form-check-input' style='' />
<label class='form-check-label' for='pbzcnal_vq_1'>Ch10 Acme Corp</label>
</div>
...
<div class='form-check form-check-inline'>
<input type='radio' id='pbzcnal_vq_3' name='pbzcnal_vq' value='2' class='Laf-UI-Form-Input-Radio form-check-input' style='' checked="checked" />
<label class='form-check-label' for='pbzcnal_vq_3'>Forms Chapter Ltd</label>
</div>
...
```

`required` stays on radios (one checked satisfies it).

### CheckboxCollection

One checkbox per option for a column that stores several selections as a comma-separated list (MySQL `SET`, varchar).
It posts `name[]` after an empty hidden value; `required` is dropped (the browser would demand every box). On a foreign
key it draws radios, since the column holds one key.

```php
$tags = (new Field())->setName('tags')->setLabel('Tags')->setType(new TypeVarchar());
$boxes = FormElementFactory::createOfType(CheckboxCollection::class, $tags);
$boxes->setOptions(['a' => 'Alpha', 'b' => 'Beta & Co']);
$tags->setValue('a,b');
```

```html
<div id='gntf' class='laf-choice-list'>
<input type='hidden' name='gntf[]' value='' />
<div class='form-check'>
<input type='checkbox' id='gntf_1' name='gntf[]' value='a' class='Laf-UI-Form-Input-CheckboxCollection form-check-input' style='' checked="checked" />
<label class='form-check-label' for='gntf_1'>Alpha</label>
</div>
<div class='form-check'>
<input type='checkbox' id='gntf_2' name='gntf[]' value='b' class='Laf-UI-Form-Input-CheckboxCollection form-check-input' style='' checked="checked" />
<label class='form-check-label' for='gntf_2'>Beta &amp; Co</label>
</div>
</div>
```

VIEW mode: `Alpha, Beta &amp; Co`.

## Dates and times: Date, DateTime, Time

The three extend `TemporalInput`. The stored value is canonical (`Y-m-d`, `Y-m-d H:i:s`, `H:i:s`); the control shows it
in the locale display format as a text input carrying a picker class for your JavaScript date picker, or, with native
inputs on, as the browser's own control with ISO values. Both forms are parsed back by the field types' `fromInput()`.

| Class | Picker class | Display format (Settings key, default) | Native control |
|---|---|---|---|
| `Date` | `date-picker` | `locale.date.format`, `F d, Y` ("December 10, 1985") | `type="date"`, `1985-12-10` |
| `DateTime` | `date-time-picker` | `locale.datetime.format`, `F d, Y H:i` | `type="datetime-local"`, `2026-10-14T18:54:47` |
| `Time` | `time-picker` | `locale.time.format`, `g:i A` ("2:05 PM") | `type="time"`, `14:05` |

Seconds are added to the display when the value has non-zero seconds (`October 14, 2026 18:54:47`), so a value
survives a round trip; a native datetime/time input then gets `step="1"`.

```php
$birth = $employee->getBirthDateFormElement();
echo $birth->setDrawMode(DrawMode::INSERT)->draw();   // text + date-picker
$birth->setNativeInput(true);
echo $birth->draw();                                  // native
```

```html
<input class='Laf-UI-Form-Input-Date form-control date-picker' style='' type="text" name="ovegu_qngr" id="ovegu_qngr"
       value="December 10, 1985" placeholder="Birth Date" />

<input class='Laf-UI-Form-Input-Date form-control' style='' type="date" name="ovegu_qngr" id="ovegu_qngr"
       value="1985-12-10" placeholder="Birth Date" />
```

| Method | What it does |
|---|---|
| `setNativeInput(?bool)` | true = native control, false = text + picker, null = follow Settings `form.native_date_inputs` (default false) |
| `isNativeInput()` | the effective choice |
| `getEditValue()` | the value as the control shows it |
| `getDisplayValue()` | the VIEW-mode text (the raw value when it does not parse) |

> **Note:** the default display formats use English month names (`F`). Set `locale.date.format` (for example
> `d.m.Y`) and friends to what your users type, or switch to native inputs; the same formats are used to parse the
> submission. See [16-settings-locale-utilities.md](16-settings-locale-utilities.md).

LAF ships no picker JavaScript: bind your picker to `.date-picker`, `.date-time-picker` and `.time-picker`, or use
native inputs.

## File

The upload input for a foreign key to the document table (chosen automatically for such a column). The file travels in
`$_FILES` under the field's rot13 name; `processForm()` stores it through the document handler only when a file was
sent, otherwise the stored document id is kept. The form switches to `enctype='multipart/form-data'` by itself when a
`File` input is on it.

```html
<input class='Laf-UI-Form-Input-File form-control' style='' type="file" name="svyr_k" id="svyr_k" />
```

- `accept` comes from Settings `upload.allowed_mime_types` when it is set (or set it with `addAttribute('accept', ...)`).
- VIEW mode links the stored document: a thumbnail for images, "Download File" otherwise, "(no file attached)" when
  empty. Edit modes show the same link under the input; `setShowCurrentFile(false)` hides it.
- Links default to `?module=document&submodule={view|thumbnail|download}&id={id}`; an app with pretty URLs sets a
  builder once:

```php
use Laf\UI\Form\Input\File;

echo File::documentUrl(File::ACTION_DOWNLOAD, 12);   // ?module=document&submodule=download&id=12
File::setDocumentUrlBuilder(fn (string $action, int $id): string => "/documents/$id/$action");
echo File::documentUrl(File::ACTION_VIEW, 12);       // /documents/12/view
```

Storage, allowed types and serving are in [15-files-and-documents.md](15-files-and-documents.md).

## Relation inputs (see 08-relations.md)

These are covered in [08-relations.md](08-relations.md):

| Class | What it is |
|---|---|
| `Autocomplete` | a `Select` for foreign keys to big tables: a search box plus a hidden key input, answered by `Form::handleLookupRequest()`, optional "create new" |
| `ManyToManyCheckboxes` | default picker for a linker table (e.g. "Projects" on the employee form) |
| `ManyToManyAutocomplete` | chips with search, for big other tables |
| `ManyToManyDualList` | "Available" / "Selected" lists (`"element":"DualList"`) |

The many-to-many pickers implement `Laf\UI\Form\RelationElementInterface` instead of being bound to a Field.

## Buttons

`Laf\UI\Form\Control\Button` draws a `<button>`; it is not bound to a field (`getField()` is null, `setField()` throws
`\LogicException`). The caption is the value (escaped); `setIcon('fa fa-save')` puts an `<i>` before it. By default
it is wrapped like an input (container and label column); `setWrapInDiv(false)` draws the bare button, which is what
`Form::addButton()` does.

```php
use Laf\UI\Form\Control\Button;
use Laf\UI\Form\Control\ButtonType;

$preview = (new Button())->setValue('Preview')->setIcon('fa fa-eye');
$preview->setWrapInDiv(false);
$preview->addCssClasses('btn', 'btn-secondary');

$reset = (new Button())->setType(ButtonType::Reset)->setValue('Reset');
$form->setResetButton($reset);
```

```html
<button type="button" class='btn btn-secondary Laf-UI-Form-Control-Button' style=''><i class='fa fa-eye'></i> Preview</button>
```

| Class / constant | What it is |
|---|---|
| `Button` | `type="button"`; `setWrapInDiv(bool)` / `getWrapInDiv()`, `setIcon(string)` / `getIcon()` |
| `SubmitButton` | `type="submit"`; the form's default submit button is one (`btn btn-md btn-success`, "Submit") |
| `ButtonType::Button`, `::Reset`, `::Submit` | values for `setType()` |

Give a second submit button its own `setName()` and check `isset($_POST['that_name'])` after `processForm()` to tell
which one was pressed (see [06-forms.md](06-forms.md#footer-buttons)).

## Writing a custom element

An element is any `FormElementInterface`. The simple way is to extend `Text` (or one of its subclasses) and override:

- `drawUpdateMode()`: build the control and return `$this->renderWrapper($control)` (label, addons, hint, errors);
- `getDisplayValue()`: what VIEW mode shows (escaped for you).

Protected helpers: `controlAttributes(array $without = [])` (the attribute bag minus `class`, `style`, `height`, `width` and the names you pass),
`controlClasses(...$defaults)` (adds `is-invalid` on errors), `controlStyle()`, `renderAttributes(array)` (escaped
`key="value"` pairs), `describedBy()` (the ARIA attributes), `renderWrapper(string $control, array $options)` with
options `hint`, `errors`, `addons`, `suggestions` (bools), `labelFor` (string), `wrapperStyle`, `hidden`.

A money input that shows `€ 4,200.50` in VIEW mode and a `€` addon when editing:

```php
namespace App\Ui;

use Laf\UI\Form\Input\Number;
use Laf\Util\Locale;

class Money extends Number
{
    public function getDisplayValue(): ?string
    {
        $value = $this->getValue();
        return ($value === null || $value === '') ? '' : '€ ' . Locale::formatNumber($value, 2);
    }

    public function drawUpdateMode(): string
    {
        $this->setPrefix('€');
        return parent::drawUpdateMode();
    }
}
```

```php
$employee->getSalaryFld()->setFormElementClass(\App\Ui\Money::class);
// or FormLayout::...->element('salary', \App\Ui\Money::class), or "element":"App\\Ui\\Money" in the column comment
```

VIEW and UPDATE output:

```html
<div id='fnynel' class='Money form-control-plaintext border border-secondary border-1 bg-light rounded p-2' style='...'>€ 4,200.50</div>

<div class='input-group'><span class='input-group-text'>€</span><input class='Money form-control' style=''
type="number" name="fnynel" id="fnynel" value="4200.50" placeholder="Salary" inputmode="decimal" step="0.01" /></div>
```

Keep the element stateless while drawing (do not change the value or options in `draw()`), post the value under
`getName()`, and keep the id conventions, so the form's error handling and the signed field list keep working. The
posted string goes through the field type's `fromInput()`, so the control must post something the type understands.

> **Note:** the css class that names the element comes from `getComponentCssControlClass()`; for a namespaced custom
> class it is the class name with `\` replaced by `-` (`App-Ui-Money`). The example above was run as a class without
> namespace, hence `Money`.

## Reference

`FormElementFactory` (final, static):

| Method | What it does |
|---|---|
| `create(Field $field, ?FormElementInterface $override = null)` | the field's (cached) element, or the override bound to the field |
| `createOfType(string $class, Field $field)` | a new element of `$class` bound to the field; throws `\InvalidArgumentException` for a non-element class |

`FormElementInterface` (extends `ComponentInterface`): `setField(Field)`, `getField(): ?Field`,
`getComponentCssControlClass()`, `getHint()`, `setHint(?string)`, `setErrors(array)`, `getErrors()`, `hasErrors()`.

Field methods related to elements (see [03-models.md](03-models.md)):

| Method | What it does |
|---|---|
| `getFormElement(?FormElementInterface $override = null)` | the cached element (created on first use) |
| `setFormElementClass(?string)` / `getFormElementClass()` | the class to create instead of the type's choice |
| `hasFormElement()` / `clearFormElementCache()` | whether one is cached / drop it |
| `setFormOptions(array)` / `getFormOptions()` / `getFormOption(string $key, $default = null)` | the `form` hints from the column comment |

Other classes in `Laf\UI\Form`: `InputType` (constants for `type`: `Button`, `Color`, `Checkbox`, `Date`,
`DatetimeLocal`, `Datetime` (deprecated), `Email`, `File`, `Hidden`, `Number`, `Password`, `Range`, `Search`, `Tel`,
`Text`, `Time`, `Url`, `Submit`), `FormRowDisplayMode` (`ROW` = `'row'`, `INLINE` = `''`), `DrawMode` (see
[06-forms.md](06-forms.md#draw-modes)).

Input classes at a glance:

| Class | Parent | Default for |
|---|---|---|
| `Text` | — | varchar, char, uuid, blob, decimal, float |
| `Email`, `Url`, `Search`, `Tel`, `Color`, `Password` | `Text` | opt-in |
| `Integer` | `Text` | integer |
| `Number`, `Range` | `Text` | opt-in |
| `TextArea` | `Text` | text, json |
| `Hidden` | `Text` | auto-increment primary key |
| `Checkbox` | `Text` | bool |
| `Select` | `Text` | foreign key, enum |
| `Radio` | `Select` | opt-in |
| `CheckboxCollection` | `Select` | multiple enum / SET |
| `Autocomplete` | `Select` | foreign key above the threshold (08-relations.md) |
| `TemporalInput` (abstract) | `Text` | |
| `Date`, `DateTime`, `Time` | `TemporalInput` | date, datetime/timestamp, time |
| `File` | `Text` | foreign key to the document table |
| `ManyToManyCheckboxes`, `ManyToManyAutocomplete`, `ManyToManyDualList` | (relation pickers) | linker tables (08-relations.md) |
| `Control\Button`, `Control\SubmitButton` | — | form footer |

## See also

- [06-forms.md](06-forms.md): drawing, layout, processing, CSRF, the mass-assignment guard
- [08-relations.md](08-relations.md): `Autocomplete`, `handleLookupRequest()`, many-to-many pickers
- [02-code-generation.md](02-code-generation.md): column comments (`form.element`, `fields.setHint`, ...)
- [05-validation.md](05-validation.md): server-side rules behind `required`, `pattern`, min/max
- [15-files-and-documents.md](15-files-and-documents.md): the document table behind `File`
- [16-settings-locale-utilities.md](16-settings-locale-utilities.md): `locale.*` formats, `form.*` settings, `Util::e()`
- [17-security.md](17-security.md): escaping rules, raw-HTML APIs (`setPrefixHtml()`, `setSuffixHtml()`, `FormLayout::html()`)
