Review and rework of the form inputs and controls in src/Laf/UI/Form (Form, Input/*, Control/*): 26 bugs fixed, one shared renderer, validation feedback, CSRF per session, native date inputs, PHP 8.5-clean, verified on MariaDB and PostgreSQL (uncommitted on improvements1).

# Form inputs and controls — review notes

Branch `improvements1`, working tree. Follows the AuditLog rework (`2026-10-11-auditlog-notes.md`).

## 1. How the pieces fit

- `Field::getFormElement()` asks the field's `FieldType` for an input (`AbstractFieldType::getFormElement()`): a foreign key is a `Select` (a `File` when it points at the `document` table), an auto-increment primary key is `Hidden`, otherwise the type's natural input (Varchar/Char/Decimal/Float/Blob/Uuid → `Text`, Integer → `Integer`, Text/Json → `TextArea`, Bool → `Checkbox`, Enum → `Select`, SET → `CheckboxCollection`, Date/DateTime/Time → their inputs). `FormElementFactory::create()` is the documented entry point and now exists.
- `Text::copyAttributesFromField()` is the only bridge from Field metadata (name, value, label, placeholder, hint, required, lengths, type attributes such as `step`/`min`/`max`/`inputmode`) to the element.
- `BaseObject::getForm()` builds a `Form` with every field element; generated pages call `isSubmitted()`, `processForm()`, `setDrawMode()`, `setComponents([])` + `addComponent()`, `setFormRowDisplayMode(INLINE)`, `setSubmittedFieldValue()` and `getSubmitButton()->setValue()`; forms are drawn through `$page->addComponent($form)`.
- Request contract (unchanged): hidden `form_submit` = table name, hidden input named after the table marks the request, `{form}_draw_mode`, every field posted under its rot13 name (routerarena JS reads `#gvgyr`, asm JS `[name="…"]`), `_csrf_token_{form}` on POST. Picker classes `date-picker` / `date-time-picker` / `time-picker` for asm's flatpickr.

## 2. Bugs found and fixed

| # | Where | Bug | Fix |
|---|---|---|---|
| 1 | `Form::addFieldsFromObject()` | Called `FormElementFactory::create()`, a class that did not exist (CLAUDE.md listed it) → fatal; grouped mode pre-rendered HTML before the draw mode was known | `FormElementFactory` added; groups become a `Fieldset` container that propagates the draw mode |
| 2 | `Form::processForm()` | Gated on `filter_input()`, which ignores `$_POST` set by code and made the method untestable | `detectSubmission()` reads the superglobals, like `isSubmitted()` already did |
| 3 | `Form::processForm()` + `File` | The file input always posted `value=1`, so `Document::upload()` ran and returned null when no file was chosen: the stored document id was wiped on every update | Upload only when `$_FILES` carries a file; otherwise the id is kept; upload failures become field errors |
| 4 | `Form::processForm()` | Every boolean field was forced to false when absent from POST, also when its checkbox was not on the form (`setComponents()` subset) | `Checkbox` posts a hidden `0`; the absent→false fallback applies only to rendered fields (`getRenderedFieldNames()`) |
| 5 | `Form::drawUpdateMode()`, every input | `action` (defaults to `REQUEST_URI`), ids, labels, hints, wrapper styles interpolated unescaped into single-quoted attributes → reflected XSS through the URL | `Util::e()` on every attribute and text |
| 6 | `Form::drawUpdateMode()` | Overwrote each top-level component's draw mode, so the apps' `getXFormElement()->setDrawMode(VIEW)` (22 sites) only worked inside a `Div` | Same rule as the containers: set only when the component has no mode |
| 7 | CSRF | New token on every draw, deleted after validation: two tabs, the back button or a second draw of the same table's form failed with "CSRF token validation failed" | One token per session and form name, reused and kept; `getCsrfToken()`, `regenerateCsrfToken()`, `CsrfTokenException` |
| 8 | `Form::drawUpdateMode()` | Previous/Next tab buttons used jQuery | Vanilla JS |
| 9 | `Form` getters | `getSubmitButton(): Button` / `getResetButton(): Button` vs `setSubmitButton(FormElementInterface)`; the reset button was never drawn; `hideSubmitButton()` did not hide | `?Button` / `Button`; reset button drawn when set; hidden submit button not drawn |
| 10 | `Text::copyAttributesFromField()` | `setAttributes($field->getAttributes())` replaced the bag and wiped `type=text`; `getType(): string` then threw | Field attributes merged over the defaults; `getType(): ?string` |
| 11 | `Text::drawViewMode()` | Printed input-only attributes (`type`, `step`, `pattern`, `disabled`…) on the view `<div>` | Whitelist: `title`, `dir`, `lang`, `data-*`, `aria-*` |
| 12 | View modes of Text/Select/Checkbox/Date… | Drawing mutated the element (`setValue(label)`, `setValue('Yes')`, classes added, attributes removed): a second draw in another mode rendered wrong values | Draws compute local attribute/class lists; `getDisplayValue()` hook for VIEW |
| 13 | `Text::getValueForHtml()` | Fatal for an element without a field | Null-safe |
| 14 | `Radio` | `class` emitted twice, `id` duplicated, current value never checked, no group label, Bootstrap 4 `custom-control`, `mb_strlen(null)`, view mode drew the radios | Rewritten on `Select::renderChoiceList()`: `form-check` markup, `checked`, `{id}_{n}` ids, group label, view shows the label, `setInline()` |
| 15 | `Url::draw()` | Always rendered view mode | `type=url` in update mode like Email |
| 16 | `Control\Button` | `' *'` appended to every label, hint class `form-Button Button-muted`, BS4 `form-group`, junk `classl` attribute | Fixed; `mb-3`, `form-text text-muted` |
| 17 | `Button::getField()` | Threw, so any walk over form components died on the submit button | Returns null (`setField()` still throws) |
| 18 | `CheckboxCollection` | `@TODO` stub calling `getField()->getFormElement()` on itself | Real implementation: one checkbox per option, posts `name[]`, comma-joined storage (SET columns use it) |
| 19 | `FormElementTrait::setAutocomplete()` | Wrote the attribute key `'attribute'`; boolean instead of the HTML token | `setAutocomplete(bool|string|null)` → `on`/`off`/token; `isAutocomplete()` false only for `off` |
| 20 | `FormElementTrait` getters | `getType()/getName(): string` threw when unset; `getMin/getMax/getStep(): ?int` threw for `any`, `0.01`, the bigint maximum | Nullable; `int|float|string|null` keeping the type of the value |
| 21 | `Select::getOptions()` | Own engine check (`=== 'mysql'`), unquoted criteria keys, options re-queried on every draw | `Identifier::quote()` everywhere (MySQL backticks, PostgreSQL double quotes), options cached per element |
| 22 | `Select` selected test | `$ok == $value && mb_strlen…`; `multiple` unsupported (array POST → `trim()` TypeError) | Strict string compare; `multiple` posts `name[]`, Form joins arrays with commas |
| 23 | `DateTime`/`Time` display | Locale formats without seconds turned `…:45` into `…:00` on every round trip (spurious audit UPDATE); default time format `H:i A` mixed 24h with AM/PM | Seconds appended when not zero (`Locale::formatWithSeconds()`, accepted back by the types); default `g:i A` |
| 24 | `BaseObject::getAllFieldsAsFormElements()` | `return static::getAllFieldsAsFormElements()` — infinite recursion unless the generated class overrides it | Builds the list from the table's fields |
| 25 | `BaseObject::checkFieldsForMissingRequiredValues()` | `(string)false == ''` → a required boolean set to false counted as missing | `=== null || === ''` |
| 26 | `ComponentTrait::getCssStyleItem()` / `getWrapperCssStyleItem()` | Declared `: array`, returned a string → TypeError | `?string` (interface too) |

Also: `Image::setHeight/setWidth/getHeight/getWidth` declared an undefined `Text` return type and `getClasses()` did not exist (would TypeError / fatal); the trait versions are used now. `UrlParser.php:87` passed null to `str_replace()` (deprecation printed in every test run).

## 3. Design evaluation

The design — a Form bound to an Active Record object, elements as attribute bags with a draw-mode switch, Field metadata copied once into the element — fits LAF and is kept. What was wrong with it and is now changed:

- Six classes each carried a copy of the wrapper markup and drifted (BS4 vs BS5, different hint classes). `Text::renderWrapper()` / `renderAttributes()` / `controlAttributes()` / `controlClasses()` / `controlStyle()` render the container, label, column, hint and validation feedback once; subclasses only produce their control.
- Draws mutated the element. Now every draw is a pure function of the element's state; `getDisplayValue()` is the VIEW-mode hook (Select → label, Checkbox → Yes/No, temporal → locale format, Password → mask).
- No server-side validation feedback. `Form::applySubmittedValues()` collects conversion errors per field, `Form::validate()` runs `Field::validate()` (required, length, range, precision) on the rendered fields, `processForm()` throws `FormValidationException` with every message before touching the database, and the elements render `is-invalid` + `invalid-feedback` on the next draw. `setValidateOnSubmit(false)` restores "let the database complain".
- Bootstrap 5 throughout: `form-check` for radios/checkboxes, `form-select` on selects, `mb-3` wrappers, `text-end` footer.
- `FormElementInterface` now extends `ComponentInterface` (every implementer already implemented both) and declares `setErrors/getErrors/hasErrors`.

## 4. New API (all additive)

- `Form`: `detectSubmission()`, `applySubmittedValues()`, `validate()`, `getErrors()/getErrorMessages()/hasErrors()/addError()/setErrors()/clearErrors()`, `setValidateOnSubmit()/isValidateOnSubmit()`, `getFieldElement($name)`, `getRenderedFieldNames()`, `getCsrfToken()/regenerateCsrfToken()/getCsrfSessionKey()/isCsrfProtection()`, `setResetButton()` drawn. `setSubmittedFieldValue()` accepts int/float/bool/null. `addTab()`/`getTabs()` marked deprecated (never drawn).
- `Text`: `getDisplayValue()`, `getDisplayValueForHtml()`, `__toString()`, static `setLabelSuffix()/setRequiredMarker()`, protected renderer helpers for subclasses.
- `Select`: public `getOptions()`, `getStaticOptions()`, `clearOptionsCache()`, `setEmptyOption(bool)`, `setEmptyOptionLabel()`, `setInline()`, `getSelectedLabel()`; `Radio` and `CheckboxCollection` extend `Select`.
- `Checkbox`: `isChecked()` public, `Checkbox::setLabels($no, $yes)`.
- `TemporalInput` (base of Date/DateTime/Time): `setNativeInput(?bool)`, `isNativeInput()`, `getEditValue()`; Settings `form.native_date_inputs`.
- `File`: `accept` from Settings `upload.allowed_mime_types`.
- `FormElementTrait`: `getAutocomplete()`, `setInputMode()/getInputMode()`, `setTitle()/getTitle()`, `setDataAttribute()/getDataAttribute()`, `setAriaLabel()`, `setErrors()/addError()/getErrors()/hasErrors()/clearErrors()`.
- New classes: `FormElementFactory`, `Container\Fieldset`, `Exception\FormValidationException`, `Exception\CsrfTokenException`. `Locale::formatWithSeconds()`.

## 5. Compatibility

- Kept: every public method and constant; ids `{id}_container/_label/_hint`, `name` = `id` = rot13(column), `form-control`, `form-control-plaintext`, `mb-3`, `col-form-label`, `col-sm-2/10`, `form-text text-muted`, picker classes, `form_submit`, `{form}_draw_mode`, `_csrf_token_{form}`, `d-none`; `Radio`/`CheckboxCollection` are still `Text` instances; generated `Base/` classes and the ASM / routerarena pages need no change (grep: no `setSubmitButton`, no `getField()` on buttons, no subclassing of form classes).
- Changed markup: checkbox is `form-check-input` (was `form-control`), radio uses `form-check` (was BS4 `custom-control`), button wrapper `mb-3` (was `form-group`), the File marker input is gone, password values are never rendered, the view box no longer carries input attributes.
- Behaviour changes to know: validation on submit (`FormValidationException`, opt-out); CSRF token per session; unrendered booleans untouched; a document is kept when no file is uploaded; an element's own draw mode is respected inside a form; seconds preserved in date-times; default time format `g:i A`; `SET` columns get a `CheckboxCollection` instead of a text input; the hidden submit button is really hidden.
- Signature changes, all widening or unused: `getType()/getName(): ?string`, `getMin/getMax/getStep(): int|float|string|null`, `getSubmitButton(): ?Button`, `setSubmitButton(Button)`, `setResetButton(?Button)`, `getCssStyleItem(): ?string`.
- PHP: runs clean under `error_reporting=E_ALL` on PHP 8.5.4 (no deprecations, including the old `UrlParser` one); syntax stays within the composer floor 8.0 (union types, `mixed`, no enums/readonly); `declare(strict_types=1)` in the new files only.
- Engines: option queries quote identifiers per engine; booleans post `0`/`1` which both engines accept; the integration round trip runs on MariaDB and PostgreSQL.

## 6. Not done

- `Document::upload()` still trusts the client MIME type and resizes every upload as an image (Filesystem, outside this review).
- `Modal` renders its own `<form>` without a CSRF token.
- `Carousel::draw()` has a code path without a return (PHPStan), unrelated to forms.
- GET forms that write data are not CSRF-protected by design (filters use GET); documented in `Form`.

## 7. Verification

| Check | Result |
|---|---|
| `phpunit --testsuite Unit` (`-d error_reporting=E_ALL`) on PHP 8.5.4 | 475 tests, 1,556 assertions, no notices (24 new in `FormInputSelectionTest`, 19 new in `FormTest`) |
| Integration MariaDB 12.2 | 108 tests, 354 assertions (form round trip insert → update with audit check, validation stop) |
| Integration PostgreSQL 18.3 | 108 tests, 354 assertions |
| Render check (scratch script: every input, VIEW/INSERT/UPDATE, row and inline, radio, collection, fieldset, buttons, native datetime) | DOMDocument reports no mismatched tags, no duplicated attributes |
| PHPStan level 5 on `UI/Form`, `UI/Traits`, `Fieldset`, `Exception`, `Locale`, `Image/Link/Dropdown` | clean |
| App greps (asm, routerarena, sample) | no call to a changed signature |
