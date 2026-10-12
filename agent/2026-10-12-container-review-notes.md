Review of `src/Laf/UI/Container` (GenericContainer, Div, Row, Column, Card*, Fieldset, HtmlContainer, TabContainer, TabItem, TabContent, Modal, ContainerType) on `improvements1`, 2026-10-12: a crash on HtmlContainer::addComponent(), a broken Modal title id, Row/Column overriding children's draw modes and an unescaped Modal fixed; one shared container renderer; attributes, tags, Modal options and tab helpers added; verified under E_ALL on PHP 8.5, MariaDB integration green.

# Defects fixed

| # | Where | Defect | Fix |
|---|---|---|---|
| H1 | HtmlContainer | `addComponent()` threw `mysql_xdevapi\Exception`, a class that does not exist → "Class not found" error instead of the intended message, which named a method (`addContent()`) that did not exist either | `\LogicException`; `addContent()` added; constructor argument optional |
| M1 | Modal | `<h5 class='modal-title' id='{id}Label}'>` (stray brace) → `aria-labelledby` pointed at a missing element | fixed |
| M2 | Modal | id, size, form action, form method and cancel label unescaped; `setFormId()` stored but never drawn | escaped; the form gets its id; the title stays raw HTML (the apps pass icons, same rule as the page titles) |
| M3 | Modal | `getModalOpenButton($label)` ignored the label (always "Open Modal"; the apps call it without a label and set the caption afterwards, so unaffected) | uses the label; css classes split on any run of spaces |
| M4 | Modal | `setFormMethod()` accepted any string | normalised to GET / POST |
| R1 | Row, Column | forced their own draw mode onto every child, even one drawn read-only on purpose (FormLayout warned about it) | the container rule: only children without a mode inherit |
| G1 | GenericContainer, Div, Card*, Row | an empty container type was added as an empty css class → `class='… '` with a trailing space; Row appended the container type raw; class and style unescaped | skipped when empty, escaped |
| T1 | TabContainer | `__construct(?string $id)` passed null to `strlen()` (deprecated under 8.1+); a generated id was a bare uuid, which may start with a digit and then breaks the `querySelector('#…')` of the Previous/Next buttons | `setId('')` generates `tabs-{uuid}` |
| T2 | TabContainer | the first tab was always forced active, so `TabItem::setActive(true)` on another tab produced two active tabs | the explicitly active tab wins; the first is active only when none is; several active → the first of them |
| T3 | TabContainer, TabItem | ids, hrefs and titles unescaped (the title was escaped, the ids not) | escaped |
| D1 | Card, CardHeader, CardBody, CardFooter | redundant `use ComponentTrait` on top of Div's, no-op constructors setting the container type to '' | plain subclasses adding their class |

# Modernisation

- One renderer: `GenericContainer::draw()` = `openTag()` + `drawComponents()` + `closeTag()`, with `drawAttributes()`;
  `Div` now extends `GenericContainer` (identical class before, so `instanceof GenericContainer` is simply true for
  more objects), `Row`/`Column`/`Card*` add their class and call the parent, `Fieldset` and `TabItem` use the
  helpers. Six copies of the child-drawing loop are gone.
- Typed properties and `static` returns everywhere (`TabItem::$active` stays public for compatibility), strict
  comparisons, LF endings, spaces in `Column.php`, class docblocks on every file; `TabContent` (deprecated) typed.
- PHPStan level 5 on the folder: clean.

# New API (additive)

- `GenericContainer` (so every container): the attribute bag is drawn on the element (`setAttribute('id', …)`,
  `data-*`, `aria-*`; class/style excluded, empty skipped), `setTag()` / `getTag()` (section, span, nav, ul …;
  validated), protected `openTag()` / `closeTag()` / `drawAttributes()` / `drawComponents()`.
- `HtmlContainer::addContent()`; `new HtmlContainer()` without content.
- `TabContainer::getTabs()`, `setActiveTab(int|string)`.
- `Modal`: `SIZE_NORMAL` (the misspelt `SISZE_NORMAL` kept, deprecated), `SIZE_FULLSCREEN`, `setCentered()`,
  `setStaticBackdrop()`, `setShowCancelButton()`, `addHiddenInput($name, $value)` / `getHiddenInputs()` (drawn
  inside the form: the way to give the modal form a CSRF token, `addHiddenInput($form->getCsrfSessionKey(),
  $form->getCsrfToken())`), `setFormId()` drawn.

# Compatibility

Kept: every public method and signature (returns widened to `static`), the markup the app css and JavaScript hang
on (`{id}_tab_links`, `{id}_tab_content`, `{title}-tab` / `{title}-content`, `tab-pane fade show`, `nav-link`,
modal ids and `{id}Label`, Bootstrap 4 and 5 data attributes). Rendered differences for existing calls, checked with
a before/after render of every container (scratchpad `container_render.php`, old classes from `git show HEAD:`):
the opening tag is on one line, no trailing empty class, `Row`/`Column` list their own class before the control
class, the modal title id is correct, the form action is attribute-escaped, modal `role='form'` form gets no
trailing space. Row/Column children that had their own draw mode keep it now (R1).

# Verification

- `php -l` on every file; PHPStan level 5 on Container, Form, Page, Traits: no errors.
- Unit suite: 630 tests, 2,367 assertions (7 new in `tests/ContainerReviewTest.php`; the 29 existing container
  tests unchanged and green).
- MariaDB integration: 151 tests, 685 assertions.

# Open items

- `Modal` draws its own `<form>`; CSRF is now possible through `addHiddenInput()` but the generated pages and the
  apps' modals do not use it yet.
- `TabContainer` ignores children that are not `TabItem`s (unchanged); `Column` always adds `col-sm` next to the
  width classes given (unchanged, valid Bootstrap).
- Carousel and PhpGrid still carry the PHPStan findings listed in the traits follow-up (next candidates).
