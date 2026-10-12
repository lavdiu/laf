Second review of `src/Laf/UI/Form/Form.php` and its companions after the 03bfda8 rework: a CSRF bypass through GET, stale element values after a failed submission, booleans drawn disabled or read-only saved as false, generated pages crashing on validation errors and jQuery tab buttons fixed; signed field list with an opt-in mass-assignment guard, request injection, error summary, write refusals as field errors; verified on MariaDB and PostgreSQL under E_ALL on PHP 8.5.

# Form review 2 — 2026-10-12

Branch `improvements1`, working tree (not committed). Follows `agent/2026-10-11-form-inputs-notes.md` (first rework) and the
Db reviews. Files: `src/Laf/UI/Form/Form.php` (rewritten in place, 1,376 lines), `Database/Field/Field.php`,
`Database/BaseObject.php` (two throw sites), `Exception/MissingFieldValueException.php`,
`Exception/UniqueFieldDuplicateValueException.php`, `UI/Form/Input/Text.php` (one visibility), `UI/Form/Input/Select.php`,
`UI/Container/TabContainer.php`, `UI/Container/Modal.php`, `Generator/PageGenerator.php`, tests `FormTest` (+11),
`BaseObjectTest` (+1 per engine), `ContainerTest` (+1), `CLAUDE.md`.

## 1. How the form is used (what the fixes had to respect)

- Generated pages and asm/routerarena (about 130 pages each): `$form = $obj->getForm()` (every field as a component), then
  `if ($form->isSubmitted()) { $id = $form->processForm(); redirect }`, and only afterwards the layout for the current action
  (`setComponents([])` + `addComponent(...)`, 369/391 call sites). So at processing time the form's components are not the
  ones the page drew: either all fields (generated flow) or none (asm `person.page` clears them right after `getForm()`).
- asm `person.page` relies on "fields absent from POST stay untouched" (password reset posts one field) and on code-set values
  winning (`setSubmittedFieldValue()` for `filialet`, `password`, time windows). routerarena sets element values on INSERT
  forms (`getRecordStatusIdFormElement()->setValue(1)`). No app calls `setMethod()`, subclasses `Form`, or catches
  `MissingFieldValueException` / `UniqueFieldDuplicateValueException` around `processForm()`.
- No LAF form is submitted with GET anywhere (the one GET form in asm, `cashbalance.page`, is hand-written and separate).

## 2. Defects found and fixed

| # | Where | Defect | Fix |
|---|---|---|---|
| 1 | `detectSubmission()` / `isSubmitted()` | the request array was chosen by where the marker was found: a POST form accepted `?table=1&table_draw_mode=update&field=value` through GET, and the CSRF check runs for POST only, so any write form could be driven by a crafted link (CSRF bypass; also present on master) | a form reads the request of its own method only (`setMethod()`, POST by default); `isSubmitted()` likewise |
| 2 | redraw after `FormValidationException` | elements copied the field value once when bound, so the form drawn again after a failed submission showed the values from before the submission; the same for any `setXVal()` after `getForm()` | `Field::setValue()/setValueRaw()/setValueHTML()/loadValueFromDb()` mirror the value into the cached element (`Field::hasFormElement()`, `Text::valueToString()` public); a value that failed conversion is put on the element as typed |
| 3 | `applySubmittedValues()` booleans | "rendered but absent = false" counted every element among the components: a checkbox drawn disabled (posts nothing, hidden `0` omitted) or with its own `DrawMode::VIEW` (read-only in an update form) was saved as false; and in the generated flow every field is a component at processing time, so a boolean not drawn at all was reset to false on every update (the 03bfda8 fix #4 only held when the components were set before processing) | the drawn form posts `{form}_fields`, a signed list of its editable elements (not disabled, not excluded, not VIEW); processing uses that list, falling back to the editable elements among the components when no valid list is posted (hand-written forms, old HTML, tests) |
| 4 | `validate()` | ran on read-only / disabled elements too: a required field drawn read-only with an empty legacy value blocked the save with a message the user could not act on | validates the editable set (same list) plus code-set values |
| 5 | `PageGenerator` edit template | `processForm()` was called bare, so a validation failure (introduced in 03bfda8) or a duplicate unique value gave an uncaught exception page | `try { … } catch (FormValidationException) { Alert('record-not-saved') }` and the switch below redraws the form in the right mode with the submitted values and the messages; new label `record-not-saved` |
| 6 | `processForm()` | `MissingFieldValueException` / `UniqueFieldDuplicateValueException` from `insert()/update()` reached the page as plain exceptions, not as form errors | converted to `FormValidationException` with one message on the field (`getFieldNames()` on both exceptions, set by `BaseObject`), `getPrevious()` = the original; unknown field → `Form::FORM_ERROR_KEY` |
| 7 | `addError()` | a message added by application code after `validate()` never reached the element (push happened only in `validate()/setErrors()/clearErrors()`) | `addError()` updates the element at once; `drawComponents()` syncs messages before drawing without wiping element messages of other fields |
| 8 | `drawUpdateMode()` nav buttons | the Previous/Next script targeted `#{form id}_tab_links`, an id no TabContainer has (they use their own id) | targets the first `TabContainer` among the components; `TabContainer::navigationScript()` shared |
| 9 | `TabContainer` | nav buttons used jQuery (`$('…').trigger('click')`), Bootstrap 4 `text-right`, `$this->id ?? ''` on a non-nullable property | vanilla JS, `text-end`, PHPStan clean |
| 10 | `Modal::setFooterButtons()` | declared `: Modal`, returned nothing (TypeError when called) | returns `$this` |
| 11 | `Select::loadOptionsFromReferencedTable()` | ran `prepare()/execute()` on the raw statement, outside `Db::timed()` (not counted, not logged, no reconnect, untyped bindings) | `Db::getInstance()->fetchAllNum($sql, $params)` |

## 3. New functionality (all additive)

- **Field list + mass-assignment guard**: `getFieldListInputName()` (`{form}_fields`), `signedFieldList()`; the list is
  HMAC-SHA256 signed with a per-session secret (`$_SESSION['_laf_form_key']`, never sent to the browser), bound to the form
  name, so it cannot be forged or moved between forms. `setRestrictToRenderedFields(true)` / Settings
  `form.restrict_to_rendered_fields`: posted fields outside the list are ignored, a missing or tampered list fails with
  `FORM_ERROR_KEY` ("out of date, reload"); values from `setSubmittedFieldValue()` always apply. Default off (asm's
  hand-written parallel inputs and the generated "process before layout" flow keep working unchanged).
- `setRequest(?array $data, ?array $files = null)`: read the submission from arrays instead of the superglobals (tests,
  imports, API handlers); `null` goes back to `$_POST`/`$_GET`/`$_FILES`.
- Error summary: `setShowErrorSummary(bool)` (default on, draws only when there are errors), `drawErrorSummary()` for pages
  that place it elsewhere (`<div id="{id}_errors" class="alert alert-danger laf-form-errors">` with one `<li>` per message).
- `getEditableFieldNames()`, `getFieldElements()`, `Form::FORM_ERROR_KEY`, `__toString()`, typed `getObject(): BaseObject`
  and `processForm(): int|string|array|null`.
- `Field::hasFormElement()`; `MissingFieldValueException::getFieldNames()/setFieldNames()` and the same on
  `UniqueFieldDuplicateValueException`; `TabContainer::navigationScript($id, $direction)`.

## 4. Compatibility

Kept: every public method, constant and signature of 03bfda8 (new return types only on `getObject()`, `processForm()`,
`draw()` which were documented as such), the request contract (`form_submit`, table marker, `{form}_draw_mode`, rot13
names, `_csrf_token_{form}`), ids and classes of the markup, generated `Base/` classes and the existing generated pages
(they still work; regenerating adds the try/catch).

Behaviour changes to know:
- A POST form no longer accepts a GET submission (and the other way round). No app submits a LAF form with GET.
- Every drawn update/insert form carries one more hidden input, `{form}_fields`.
- A boolean whose checkbox was drawn disabled, read-only or not at all is left alone when absent from the request
  (before: set to false whenever its element was among the components at processing time).
- `validate()` skips read-only / disabled elements.
- Field values flow into their cached elements. A page that sets a value on the element for display and later changes the
  field value will see the field value (routerarena sets element values on the INSERT form only, which never changes the
  field afterwards).
- Required-missing and duplicate-unique refusals from the write are `FormValidationException` now (`getPrevious()` keeps
  the original class); apps catching `\Exception` are unaffected, none catches the specific classes around `processForm()`.
- PHP: clean under `error_reporting=E_ALL` on 8.5.4; syntax within the composer floor 8.0 (`static` returns, union types,
  no enums/readonly); no `declare(strict_types=1)` added to existing files.

## 5. Verification

| Check | Result |
|---|---|
| `php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit` (PHP 8.5.4) | 544 tests, 1,842 assertions (was 533): GET ignored by a POST form, `setRequest()`, disabled/read-only booleans, signed list honoured and tampered list ignored, restrict mode, submitted values shown after a failed validation, field→element mirror, `addError()` + summary + `__toString()`, write refusals on the field, nav buttons targeting the tab container, TabContainer plain JS |
| Integration MariaDB 12.2 | 147 tests, 607 assertions (duplicate unique value through the form: field error, no row written, redrawn form keeps the value and shows `is-invalid` + the summary) |
| Integration PostgreSQL 18.3 | 147 tests, 608 assertions |
| PHPStan level 5 | `UI/Form/**`, `TabContainer`, `Modal`, the two exceptions, `BaseObject`: clean; `Field.php` down from 7 findings to 3 pre-existing ones (unused `$loadedOnTs`, a `@param mixed` docblock, `getAttribute()` nullable) |
| App greps (asm, routerarena) | no `setMethod()`, no `extends Form`, no catch of the converted exceptions, no GET LAF forms |

## 6. Not done / next

- `PageGenerator` has no test; the template change was checked by `php -l` of the generator and by reading the emitted
  code. Regenerate one page in an app to see the try/catch in place.
- `Modal` still renders its own `<form>` without a CSRF token and without escaping its attributes (unchanged).
- `Document::upload()` still trusts the client MIME type (Filesystem, outside this review).
- With `form.restrict_to_rendered_fields` on, hand-written HTML forms that post to a LAF `Form` must include the signed list
  (`$form->signedFieldList([...rot13 names])`) or they are refused; asm `person.page`'s password reset form is such a case.
- The field list makes the editable set explicit; a later step could drop the "components at processing time" fallback once
  every app form is drawn by LAF.
