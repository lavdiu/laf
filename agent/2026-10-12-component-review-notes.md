Review of `src/Laf/UI/Component` (Link, Dropdown, Image, Alert, Carousel, Rel, Target) on `improvements1`, 2026-10-12: a Carousel that returned null, getters that threw for unset attributes, a confirmation message broken by an apostrophe, leftAlign() aligning right and more fixed; typed, escaped, Bootstrap 5 data attributes; dropdown dividers/headers, disabled links, lazy images, dismissible alerts added; verified under E_ALL on PHP 8.5, MariaDB integration green.

# Defects fixed

| # | Where | Defect | Fix |
|---|---|---|---|
| C1 | Carousel | `draw(): string` had no return statement when the controls were hidden → TypeError (PHPStan reported it) | always returns the markup |
| C2 | Carousel | indicators were drawn per document, not per slide: images given directly had none, and the `addImage()` calls inside `draw()` appended the documents' images to the list on every draw (a second draw doubled the slides) | `getSlides()` computes images + documents each time; one indicator per slide; drawing never changes the component |
| C3 | Carousel | Bootstrap 4 attributes only (`data-ride`, `data-slide-to`, `data-slide`, `sr-only`); id and URLs unescaped; `class = '…'` with spaces; `Util::coalesce()` for the id | both `data-*` and `data-bs-*`, `visually-hidden` next to `sr-only`, escaped, `getId()` null when none was set |
| L1 | Link | `getHref()/getTarget()/getRel()/getId()/getOnClick()/getTitle(): string` returned `getAttribute()`'s null for an unset attribute → TypeError | `?? ''` |
| L2 | Link | `setConfirmationMessage()` put the text into `confirm('…')` verbatim: an apostrophe ("t'i") or a line break broke the JavaScript | the text is JavaScript-escaped (`\'`, `\n`, `\\`, `<\/`); the `confirm('…')` form stays |
| L3 | Link | `draw()` called `mb_strlen()` on every attribute value, null included (deprecated under 8.1+, and the trait now allows nulls); icon, class and style unescaped; untyped constructor | null/empty skipped, escaped, typed (nullable strings, arrays) |
| D1 | Dropdown | `leftAlign()` set right alignment | fixed; `isRightAligned()` |
| D2 | Dropdown | the links got the css class `'dropdown-item '` (trailing space); an id set on the dropdown was rendered twice (attribute loop + generated id); text and icon unescaped; generated id without prefix; Bootstrap 4 `dropdown-menu-right` only | `dropdown-item`; the id attribute is the toggle id when set, else `dd{uniqid}`; text escaped (icon as a css class); `dropdown-menu-end` added next to `-right` |
| I1 | Image | `getSrc()/getId()/getAlt()/getOnClick()/getTitle(): string` → TypeError for an unset attribute; `draw()` wrote height/width into the component's style map on every draw; class and style unescaped | `?? ''`; sizes computed locally; escaped |
| A1 | Alert | the type went into the class attribute unescaped; braces-less ifs | escaped (title and message stay raw HTML: the generated pages pass messages with markup) |
| X1 | Link, Dropdown, Image | `getField()` threw `\Exception` although the interface documents null for unbound elements (Button returns null) | return null; `setField()` throws `LogicException` |
| X2 | FormElementInterface | `setHint()` had no return type because Dropdown's implementation had none | `: ComponentInterface` on the interface and every implementation |

# Modernisation

Typed properties and parameters, `static` returns, strict comparisons, `Util::e()` inside single-quoted and
`Util::attr()` inside double-quoted attributes, class docblocks on every file (Rel and Target included), LF
endings. PHPStan level 5 on Component, Form, Container, Page, Traits and Generator: clean (was 2 errors).

# New API (additive)

- `Link::setDisabled(bool)` (Bootstrap: class `disabled`, `aria-disabled`, `tabindex=-1`; `isDisabled()`),
  nullable constructor arguments.
- `Dropdown::addDivider()`, `addHeader($text)`, `addHtml($raw)`, `getItems()`, `hasLinks()`,
  `addMenuCssClass()`, `isRightAligned()`; `setId()` (from the trait) names the toggle.
- `Image::__construct($src, $alt)`, `setLazy(bool)` / `isLazy()`.
- `Alert::setDismissible(bool)` (close button), `setIcon($class)`.
- `Carousel::setDocumentUrlBuilder(fn(int $id): string)` / `documentUrl()`, `getSlides()`,
  `setUseBgImages()`, constructor arguments optional.

# Compatibility

Kept: every public method; `setConfirmationMessage()` still renders `return confirm('…')` (the existing test
pins it); Link captions and Alert title/message stay raw HTML (the apps pass icons and markup); `Link`'s
constructor order `($text, $href, $icon, $attributes, $class)`; `Dropdown('text', 'class', 'icon', $right)`
(257 calls in the apps, plus the generator template and `BaseObject`); `rightAlign()`. Rendered differences
for existing calls (before/after render of every component, scratchpad `component_render.php`, old classes
from `git show HEAD:`): `dropdown-menu-end` next to `dropdown-menu-right`; Carousel: Bootstrap 5 attributes,
`visually-hidden`, one indicator per slide, double-quoted `src`, quoted `url('…')`, no `class = ` spaces.
Link, Dropdown items, Image and Alert output is otherwise byte-identical.

# Verification

- `php -l` on every file; PHPStan level 5: no errors.
- Unit suite: 637 tests, 2,435 assertions (7 new in `tests/ComponentReviewTest.php`; the existing
  `UIComponentTest` unchanged and green).
- MariaDB integration: 151 tests, 685 assertions.

# Open items

- `Image` always adds `bg-light rounded p-2` (kept: the apps' look depends on it).
- `PhpGrid` carries the remaining PHPStan findings in `src/Laf/UI` (undefined `BaseObject` getters, a getter
  returning the wrong type, unreachable code) and is the last unreviewed UI folder with `Grid` and `Table`.
