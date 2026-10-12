Review and rework of `src/Laf/UI/Page` (Html, GenericPage, AdminPage, Page, HeaderComponent) on `improvements1`, 2026-10-12: defects fixed, API additions, compatibility notes, verification.

# Scope

The five classes that build a page: `Html` (the document), `GenericPage`/`AdminPage` (the Bootstrap card every
generated `.page` puts its components into; 415 `new AdminPage()` calls in asm + routerarena), `Page` (banner variant,
unused by the apps), `HeaderComponent` (one head tag). Rewritten in place, LF endings, typed properties and
signatures (syntax within `php >=8.0`), PHPStan level 5 clean on the folder (was 5 errors), new `tests/PageTest.php`.

# Defects fixed

| # | Where | Defect | Fix |
|---|---|---|---|
| G1 | GenericPage | `enable()`/`disable()` declared `: GenericPage` and returned nothing → TypeError on every call | `return $this` (`: static`) |
| G2 | GenericPage | `draw()` appended into `$this->bodyHtml`/`$this->footerHtml`: a page drawn twice (e.g. `draw()` then `draw(true)`) showed its components twice (reproduced in the before/after render) | local strings; the two accumulator properties are gone |
| G3 | GenericPage | `setTitle(null)` / `setTitleIcon(null)` stored null while the getters are `: string` → TypeError | stored as `''` |
| G4 | GenericPage | title icon interpolated unescaped into `class='…'` | `Util::attr()`; the title stays raw HTML on purpose (pages pass anchors) |
| G5 | GenericPage | empty class added when no container type (`class='p-2  …'`), `get_class($this) == AdminPage` no-op, unused `use Settings`, `sizeof` | removed |
| G6 | GenericPage | `addLink($link)` untyped, `hasLinks()` untyped, `addFooterComponent(): ComponentInterface` broke fluent chains | `addLink(ComponentInterface): static`, `hasLinks(): bool`, `addFooterComponent(): static` |
| P1 | Page | `draw()` called `setHeader()`/`setFooter()` (did not exist) and a global `getBannerImage()` (defined nowhere) → fatal | `GenericPage::setHeader()/setFooter()`; `Page::setBannerImage()`, the getter falls back to a global `getBannerImage()` when the app defines one |
| H1 | Html | `<title>` unescaped | `Util::e()` |
| H2 | Html | `addComponent($component)` untyped | `ComponentInterface` (the apps pass pages, `Div`, `HtmlContainer`, `Modal` only) |
| H3 | HeaderComponent | a null attribute value drew `attr=""` (no `defer`/`async`); `link`/`meta` built without `$selfClosingTag = true` drew an invalid `</link>` | null/true = bare attribute, false = dropped; `meta`/`link`/`base` always void; existing `…, true)` calls draw the same ` />` |
| A1 | AdminPage | docblock `@throws MissingConfigParamException` (never thrown), redundant `draw()` override | empty subclass with a docblock (kept for its control css class and the 415 call sites) |

# New API (all additive; defaults reproduce the previous output)

- `GenericPage`: `setHeader()/getHeader()`, `setFooter()/getFooter()` (raw HTML around the card), `setSubtitle()`
  (HTML like the title, `<small class='text-muted fw-normal fs-6'>` after the title), `setShowCardHeader(bool)`,
  `setCardCssClasses()/addCardCssClass()` (default `card border-dark shadow`), `setCardHeaderCssClasses()/
  addCardHeaderCssClass()` (default `card-header bg-light border-dark`), `setFooterComponents()`, `isCardHeaderShown()`;
  the component's attributes (`setAttribute('id', …)`, `data-*`) are drawn on the wrapper `div.p-2` (they were ignored);
  `draw()` is split into protected `drawHeaderRow()`, `drawLinks()`, `drawBody()`, `drawFooter()`,
  `drawWrapperAttributes()` for subclasses.
- `Page`: `setBannerImage()/getBannerImage()`, `drawBanner()`; the caller's header/footer are kept around the banner
  markup and restored after drawing.
- `Html`: `setLanguage()/getLanguage()` (`<html lang>`: setter, else Settings `locale.language`, else `en`; new
  `Locale::language()`), `setBodyAttributes()/getBodyAttributes()/addBodyAttribute()/addBodyCssClass()` (default
  `['style' => 'background-color:#EEEEEE']`), `addFooterComponent()/getFooterComponents()`, `setFooterHtml()`,
  `addBodyEndComponent()/getBodyEndComponents()` (tags before `</body>`), `addScript($src, $attributes, $atBodyEnd)`,
  `addInlineScript($code, $atBodyEnd)`, `addStylesheet()`, `addInlineStyle()`, `addMeta()`, `addLinkTag()`,
  `setFavicon()` (icon + shortcut icon + apple-touch-icon); protected `drawHead()`, `drawBodyOpen()`, `drawBodyClose()`.
  `draw(true)` still stops right after the components (no footer, no body-end tags, no closing tags).
- `HeaderComponent`: `draw()`, `isVoidElement()`, `removeAttribute()`, `setContent(?string)`, factories `script()`,
  `inlineScript()`, `stylesheet()`, `inlineStyle()`, `meta()`, `link()`; `$attributes` constructor argument now
  optional; the tag name is validated (`InvalidArgumentException`).

# Compatibility

Kept: every public method and the constructor signature `HeaderComponent($tagName, $attributes, $selfClosingTag,
$content)`; `setTitleIcon(?string): void` stays void (code may rely on it); `setTitle(?string)`, `setLinks()`,
`getLinks()`, `addFooterComponent()`, `getComponentCssControlClass()`, `Html::draw(bool)`, `setMenu()`, `setHtmlHeader()`.
Narrowed: `addLink()` and `Html::addComponent()` now require a `ComponentInterface` (verified: the apps pass `Link`,
`Dropdown`, pages, `Div`, `HtmlContainer`, `Modal`). Return types widened to `static`.
Rendered HTML differences for existing calls (checked with a before/after render of the asm Factory recipe +
AdminPage with a Link and a Dropdown): `<body style="…">` uses double quotes, the `<!--<div class='col'>&nbsp;</div>-->`
comment is gone, no double space in the wrapper class list, links are separated by a space, the icon class is
attribute-escaped, `<title>` is escaped, component attributes appear on the wrapper, `</link>`/`</meta>` are no longer
emitted for non-self-closing void tags, a second `draw()` no longer repeats the body. `Page` renders instead of fataling.
Removed: protected `GenericPage::$bodyHtml`/`$footerHtml` (no app reads them). PageGenerator's template is unchanged.

# Verification

- `php -l` clean; `vendor/bin/phpstan analyse --level 5 src/Laf/UI/Page src/Laf/Util/Locale.php` → no errors.
- `php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit` → 601 tests, 2,174 assertions (26 new in
  `tests/PageTest.php`), no warnings.
- Integration on MariaDB (`LAF_DB_ENGINE=mysql …`) → 151 tests, 685 assertions (GeneratorTest still lints the generated
  page that uses `AdminPage`).
- Before/after render diff in the scratchpad: only the differences listed above.

# Open items

- `<meta http-equiv='X-UA-Compatible' content='IE=edge'>` is obsolete but kept so the head output stays the same;
  drop it when a head change is wanted anyway.
- The apps build their favicon trio and their script/stylesheet tags by hand in `Factory::noMenuPage()`; they can move
  to `setFavicon()` / `addScript()` / `addStylesheet()` when the Composer copy of LAF is updated.
- `setTitle()`/`setSubtitle()` take HTML; a text-only `setTitleText()` that escapes was not added to keep one rule.
