Third review of `src/Laf/UI/Form` (inputs, controls, Form, FormLayout) on `improvements1`, 2026-10-12: required checkboxes that could not be submitted, multi selects that could not be cleared, duplicate hidden-input ids and three smaller defects fixed; shared attribute renderer; input-group addons, suggestions, switches, Number/Tel/Range, File URL builder, extra form buttons, FormLayout html sections, Settings::getBool(); verified on PHP 8.5 under E_ALL, MariaDB integration green.

# Scope

The folder was reworked twice before (d10b72e inputs, 332d97d Form.php), so this pass covered what those left:
every `Input/*` class, `Control/Button`, the constant classes, `FormLayout` and the remaining rough edges of
`Form`. Every file in the folder was read, plus `FormElementTrait`, `ComponentTrait`, `Field`, the type classes,
`ClassGenerator`, the containers FormLayout builds, `Link`, `Settings`, the five existing Form test files and the
asm / routerarena call sites.

# Defects fixed

| # | Where | Defect | Fix |
|---|---|---|---|
| C1 | Input/Checkbox | the generator marks every NOT NULL column required, `Text::copyAttributesFromField()` renders `required`, the checkbox kept it → the browser refused the form while the box was unticked, so a generated form could never save "false" for a NOT NULL boolean | `required` dropped from the box (the hidden `0` always posts; `Field::validate()` accepts false); the `*` label marker stays |
| C2 | Input/Select (`renderChoiceList`) | `required` on every box of a `CheckboxCollection` (NOT NULL SET column) → only submits with every option ticked | dropped for checkbox lists, kept for radios (one checked satisfies it) |
| S1 | Input/Select | `setMultiple(true)` posted `name[]` with no hidden empty value: deselecting everything posted nothing and the field was left untouched | hidden `name[]` = `''` before the `<select>` (not when disabled); Form already filters the empty entry |
| F1 | Form | the hidden `form_submit` input had the fixed id `form_submit` and the marker input the id `{table}` → duplicate ids with several forms on a page (routerarena `router.page` has ten) | ids `{form id}_form_submit` and `{form id}_marker`; the names (request contract) are unchanged; no app JS referenced the ids |
| T1 | Input/Text | `aria-describedby` pointed at the hint only; errors were not announced | `aria-describedby` lists `{id}_hint` and `{id}_feedback`, `aria-invalid='true'` with errors; hint-only output unchanged |
| T2 | Input/Text, Control/Button | `<label> :</label>` for an element without a label; Button wrote a literal `*` and `for=''` without an id | empty label text without marker and suffix; Button uses `Text::getRequiredMarker()` and omits `for` without an id (a Button label keeps having no suffix: the earlier review's test pins that) |
| X1 | Checkbox, Hidden, TextArea, File, Password, Select | six hand-written lists of the attributes to drop from a non-`<input>` control, drifting from the unused `Text::INPUT_ONLY_ATTRIBUTES` | `Text::inputOnlyAttributesExcept(...$keep)`; every output stayed identical (render diff) |
| X2 | Form, TemporalInput, File | three copies of "read a Settings key and swallow the exception" | `Settings::getBool($key, $default)` and `Settings::has($key)` |
| M1 | Form | redeclared the trait property `$formRowDisplayMode`, `fieldIsSubmitted($fieldName)` untyped, 22 fluent setters returned `: Form` | removed, `string`, `: static` (also Select, TemporalInput, Button, Checkbox setters) |

# Modernisation

- `Laf\UI\Traits\HtmlAttributeRenderingTrait` (new, strict): `renderAttributes()`, `controlAttributes()`,
  `controlClasses()`, `controlStyle()` shared by `Text` (and every input) and `Button`, which had its own copy.
- LF endings for the whole folder (`Form.php`, `Text.php`, `Select.php` and the constant classes were CRLF),
  spaces in `Email.php`, class docblocks on every file that had none, `DrawMode::ALL` / `DrawMode::EDIT` /
  `DrawMode::isValid()` (FormLayout's `ALL_MODES` / `EDIT_MODES` alias them), `InputType::Datetime` marked
  deprecated. PHPStan level 5 on `src/Laf/UI/Form src/Laf/UI/Traits src/Laf/Util/Settings.php`: 1 error → 0.
- `FormElementInterface::setHint()` keeps no return type: `Dropdown` implements it without one.

# New API (additive; defaults reproduce the previous output)

- Every input (`Text` base): `setPrefix(?string)` / `setSuffix(?string)` (escaped text addons, Bootstrap
  `input-group` around the control in edit modes), `setPrefixHtml()` / `setSuffixHtml()` (raw), `getPrefix()` /
  `getSuffix()` / `hasAddons()`; `setSuggestions(array)` / `getSuggestions()` / `hasSuggestions()` (a `<datalist
  id='{id}_list'>` plus `list=` on the input; not on Password or Range); `renderWrapper()` options `addons` and
  `suggestions`; `inputOnlyAttributesExcept()`, `renderInputGroup()`, `renderSuggestionList()`,
  `withSuggestionList()` for subclasses.
- `Checkbox::setSwitch(bool)` / `isSwitch()`: `form-check form-switch` + `role="switch"`.
- `Input/Number` (type=number, step from the type else `any`; opt-in because the types create a plain `Text` so
  locale-formatted numbers pass), `Input/Tel` (inputmode tel), `Input/Range` (`form-range`, VIEW shows the value).
- `File::setDocumentUrlBuilder(?callable)` (`fn(string $action, int $id): string`, actions `view` /
  `thumbnail` / `download`, constants `ACTION_*`), `File::documentUrl()`, `setShowCurrentFile(bool)` (default
  true: edit mode draws `<div id='{id}_current' class='form-text'>` with the stored file; silently nothing when
  the document record cannot be loaded).
- `Button::setIcon(string)` / `getIcon()`.
- `Form::addButton(ComponentInterface)` / `getButtons()` / `setButtons()` (drawn between the reset and the submit
  button; a `Button` is unwrapped automatically), `setCancelLink($href, $label, $cssClasses)`,
  `setSubmitButtonLabel()`.
- `FormLayout::html(string $html, ?array $modes)` (`KIND_HTML`, serialised; drawn as an `HtmlContainer` at the
  top level or in the "General" tab).
- `Settings::getBool(string $key, bool $default = false)`, `Settings::has(string $key)`.

# Compatibility

Kept: every public method and signature (return types only widened to `static`), the request contract
(`form_submit`, `{table}`, `{name}_draw_mode`, `{name}_fields`, `_csrf_token_{name}`, rot13 field names), the
markup contract of the inputs (`{id}_container/_label/_hint/_feedback`, `form-control`, `col-sm-2/col-sm-10`,
picker classes, `laf-choice-list`, `form-check-input`), `Select::NON_SELECT_ATTRIBUTES` (deprecated, unused).
Rendered HTML differences for existing calls, checked with a before/after render of a DummyTable form in the
three modes, with errors, and hand-built (scratchpad `form_render.php`): `required` gone from checkboxes and
checkbox lists, a hidden empty `name[]` before a multiple select, the two hidden-input ids, `aria-describedby`
/ `aria-invalid` only on elements with errors, an empty `<label>` instead of `<label> :</label>` for label-less
elements, no `for=''` on a label without an id, a File edit control shows the stored file (`setShowCurrentFile
(false)` restores the old output). Nothing else differs. `Modal` keeps its own `<form>` (still open).

# Verification

- `php -l` on every touched file; PHPStan level 5 on the folder, the trait and Settings: no errors.
- `php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit`: 618 tests, 2,279 assertions (17 new in
  `tests/FormElementsTest.php`), no warnings; the existing Form tests pin the markup contract and stay green.
- MariaDB integration (`LAF_DB_ENGINE=mysql …`): 151 tests, 685 assertions.
- Render diff: only the differences listed above.

# Open items

- `Document::getFullPathFullSize()` concatenates `getFileNameFld()` (a Field object) instead of its value; the
  File input only calls `fileFullSizeExists()` / `isImage()` through it, so the current-file line hides the
  problem by catching it. Outside this folder.
- The form's default submit button is still drawn with a wrapper and an empty label column (`id='_container'`);
  layouts in the apps rely on that placement, so it was left alone.
- `Modal` has its own `<form>` without CSRF; `Document::upload()` trusts the client MIME type.

# Follow-up: `src/Laf/UI/Traits` (same day)

`ComponentTrait` (used by every UI component) and `FormElementTrait` (inputs, controls, Link, Image, Dropdown)
were modernised too: typed properties (no class in LAF or the apps redeclares them, checked by grep), strict
comparisons (`in_array`/`===` instead of loose), css classes unique on the wrapper as well, `removeAttribute()` /
`removeWrapperAttribute()` / `removeCssStyle()` now remove a key whose value is '' (before they silently kept it),
`getCssStyleForHtml()` / `getWrapperCssStyleForHtml()` leave out null and empty items (before: `color:`),
`getDrawMode()` returns `$drawMode ?? ''` instead of `Util::coalesce()`, `isAutofocus(): bool`,
`getValueForHtml(): string` (Text narrowed to match), protected `*ForHtml()` helpers typed, docblocks with the
rules; additive `addCssClasses(...)`, `addAttributes(array)`, `hasComponents()`. `ComponentInterface` docblocks
fixed (`@return mixed` / `ComponentTrait`). `tests/UITraitsTest.php` (5 tests). PHPStan level 5 clean on
ComponentInterface, Traits, Container, Form, Page and Link; Unit 623 tests / 2,316 assertions; MariaDB
integration 151 / 685. Rendered output unchanged except the dropped empty style items and duplicate wrapper classes.
