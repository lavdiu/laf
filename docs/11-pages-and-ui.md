# Pages and UI components

LAF builds HTML on the server from small PHP objects. An `Html` object is the whole document (head, scripts, body);
inside it a `GenericPage` / `AdminPage` draws the Bootstrap card that holds a page's content; containers (`Div`,
`Row` / `Column`, `Card`, `Fieldset`, `TabContainer`, `Modal`) arrange components; and components (`Link`,
`Dropdown`, `Image`, `Alert`, `Carousel`, forms, grids, tables) draw the actual markup. Every piece except the
document implements `Laf\UI\ComponentInterface`, so anything can go into anything with `addComponent()`, and nothing
is printed until you call `draw()` on the document. This chapter shows each class with the HTML it really renders
(Bootstrap 5 markup, Bootstrap 4 data attributes kept for old pages), the rules for what is escaped and what is raw
HTML, one-shot `Flash` messages, and a complete page assembled from the parts.

**On this page**

- [The model: document, page card, containers, components](#the-model-document-page-card-containers-components)
- [The document: `Html`](#the-document-html)
- [Head tags: `HeaderComponent`](#head-tags-headercomponent)
- [The page card: `GenericPage`, `AdminPage`, `Page`](#the-page-card-genericpage-adminpage-page)
- [Containers](#containers)
- [Components](#components)
- [Flash messages](#flash-messages)
- [`ComponentInterface`, `ComponentTrait` and draw modes](#componentinterface-componenttrait-and-draw-modes)
- [Writing your own component](#writing-your-own-component)
- [Raw HTML vs escaped text](#raw-html-vs-escaped-text)
- [A full page assembled](#a-full-page-assembled)
- [Reference](#reference)
- [See also](#see-also)

## The model: document, page card, containers, components

```
Html                                  the document: <!DOCTYPE>, <head>, <body>, menu, footer, scripts
└── AdminPage / GenericPage           a Bootstrap card: title, action links, body, footer
    ├── Alert                         components ...
    ├── Row
    │   ├── Column → Card → CardBody → HtmlContainer
    │   └── Column → Form
    └── TabContainer
        ├── TabItem → Table
        └── TabItem → PhpGrid (wrapped in an HtmlContainer)
```

You build the tree, then print `$html->draw()` once at the end. Because nothing is sent before that, a page can still
redirect, answer an AJAX request (grids, autocomplete lookups) or change the title after it has started building
components. The generated CRUD pages ([12-routing-and-generated-pages.md](12-routing-and-generated-pages.md)) follow
exactly this shape: `Factory::GeneralPage()` returns the `Html`, the page adds an `AdminPage` to it.

All classes in this chapter live in `Laf\UI\Page`, `Laf\UI\Container`, `Laf\UI\Component` and `Laf\UI`. The examples
use the shared `MyApp` schema (company, employee, project); the output shown is what LAF printed when the example was
run (whitespace trimmed in places).

## The document: `Html`

`Laf\UI\Page\Html` draws doctype, `<html lang>`, the head (charset, X-UA-Compatible and viewport metas, `<title>`,
your head tags, optional raw head HTML), then the body: menu HTML, components, footer components, footer HTML and the
scripts placed at the end of the body.

```php
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Page\Html;

$doc = new Html();
$doc->setPageTitle('Staff <list> & more')          // escaped
    ->setLanguage('de')                             // else Settings locale.language, else "en"
    ->addStylesheet('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')
    ->addScript('assets/grid.js')                   // in the head
    ->addScript('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], true) // body end
    ->addInlineScript('console.log("ready")', true)
    ->addMeta('robots', 'noindex')
    ->setFavicon('icon.png')                        // icon + shortcut icon + apple-touch-icon
    ->setBodyAttributes(['data-bs-theme' => 'light'])   // replaces the default style attribute
    ->addBodyCssClass('bg-body-tertiary')
    ->setMenu("<nav class='navbar bg-dark'>menu</nav>")  // raw HTML right after <body>
    ->addComponent(new HtmlContainer('<main>components</main>'))
    ->addFooterComponent(new HtmlContainer('<p>footer component</p>'))
    ->setFooterHtml('<footer>&copy; MyApp</footer>');  // raw HTML

echo $doc->draw();
```

```html
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset='utf-8'>
	<meta http-equiv='X-UA-Compatible' content='IE=edge'>
	<meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>
	<title>Staff &lt;list&gt; &amp; more</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
<script src="assets/grid.js"></script>
<meta name="robots" content="noindex" />
<link rel="icon" href="icon.png" />
<link rel="shortcut icon" href="icon.png" />
<link rel="apple-touch-icon" href="icon.png" />

</head>

<body data-bs-theme="light" class="bg-body-tertiary"><nav class='navbar bg-dark'>menu</nav><main>components</main><p>footer component</p><footer>&copy; MyApp</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>console.log("ready")</script>
</body>
</html>
```

Things worth knowing:

- **Order in the body:** menu, components, footer components, footer HTML, body-end tags, `</body></html>`.
- **`draw(true)`** stops right after the components: no footer, no body-end scripts, no closing tags. Use it when a
  legacy page appends its own raw HTML after LAF's output and closes the document itself.
- **The default body attribute** is `style="background-color:#EEEEEE"`. `setBodyAttributes()` replaces the whole
  set; `addBodyAttribute()` and `addBodyCssClass()` add to it. A `null` or `''` value draws a bare attribute.
- **Where scripts go:** a grid prints a small inline script that starts it when `grid.js` is already loaded and
  otherwise does nothing; `grid.js` itself starts every grid on the page when it loads. So `grid.js` works in the
  head or at the body end. Bootstrap's bundle usually goes to the body end.
- `Html` is not a `ComponentInterface`: it is the root and cannot be nested.

In an application you build the document once, in a factory, and every page asks for it. That factory is what the
generated pages call (`MyApp\Factory::GeneralPage()`); chapter 12 shows the
[minimal one](12-routing-and-generated-pages.md#the-page-factory-factorygeneralpage).

## Head tags: `HeaderComponent`

`Laf\UI\Page\HeaderComponent` is one tag of the head (or of the body end). The `Html` helpers above create them for
you; use the class directly for anything else (`<base>`, `preload` links, `module` scripts). Attribute values are
escaped; `null` or `true` draws a bare attribute, `false` leaves it out; the content of inline scripts and styles is
written as is; `meta`, `link` and `base` are always void.

```php
use Laf\UI\Page\HeaderComponent;

echo HeaderComponent::script('js/app.js', ['defer' => null])->draw();
echo HeaderComponent::script('js/mod.js', ['type' => 'module', 'async' => true, 'nomodule' => false])->draw();
echo HeaderComponent::stylesheet('css/app.css', ['media' => 'print'])->draw();
echo HeaderComponent::meta('description', 'Staff & "projects"')->draw();
echo HeaderComponent::link('canonical', 'https://example.com/?a=1&b=2')->draw();
echo HeaderComponent::inlineScript('window.APP = {debug: true};')->draw();
echo HeaderComponent::inlineStyle('body { padding-top: 56px; }')->draw();
echo (new HeaderComponent('base', ['href' => '/myapp/']))->draw();

$doc->addHeaderComponent(HeaderComponent::link('preload', 'fonts/inter.woff2', ['as' => 'font', 'crossorigin' => null]));
```

```html
<script src="js/app.js" defer></script>
<script src="js/mod.js" type="module" async></script>
<link rel="stylesheet" href="css/app.css" media="print" />
<meta name="description" content="Staff &amp; &quot;projects&quot;" />
<link rel="canonical" href="https://example.com/?a=1&amp;b=2" />
<script>window.APP = {debug: true};</script>
<style>body { padding-top: 56px; }</style>
<base href="/myapp/" />
```

> **Note:** the content of `inlineScript()` / `inlineStyle()` (and `Html::addInlineScript()` / `addInlineStyle()`)
> is not escaped. Never put request data in it; pass data to scripts with `json_encode()` (with `JSON_HEX_TAG`) or a
> `data-*` attribute.

The constructor is `new HeaderComponent(string $tagName, array $attributes = [], bool $selfClosingTag = false,
?string $content = null)`; a tag name that is not a plain name throws `InvalidArgumentException`.

## The page card: `GenericPage`, `AdminPage`, `Page`

`Laf\UI\Page\GenericPage` is the Bootstrap card every page puts its content into: a header row (icon, title,
subtitle on the left, action links on the right), the body with your components, an optional card footer, and raw
HTML before and after the card. `AdminPage` renders exactly the same; it only carries a different control class
(`Laf-UI-Page-AdminPage`) so admin cards can be styled apart. The generated pages use `AdminPage`.

```php
use Laf\UI\Component\Link;
use Laf\UI\Container\ContainerType;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Page\AdminPage;
use Laf\Util\Util;

$page = new AdminPage();
$page->setTitle("<a href='?module=employee&action=list'>Employees</a>")   // raw HTML
    ->setSubtitle(Util::e('of "ch12 Acme" & partners'))                     // raw HTML: escape data yourself
    ->addLink(new Link('Add new', '?module=employee&action=new', 'fa fa-plus-square', [], ['btn', 'btn-sm', 'btn-outline-success']))
    ->addComponent(new HtmlContainer('<p>body</p>'))
    ->addFooterComponent(new HtmlContainer('<small>3 rows</small>'))
    ->setHeader('<nav aria-label="breadcrumb">crumbs</nav>')                 // raw, before the card
    ->setFooter('<!-- after the card -->');                                  // raw, after the card
$page->setTitleIcon('fa fa-users');          // returns void (kept for BC): call it on its own line
$page->setContainerType(ContainerType::TYPE_FLUID);
$page->setAttribute('id', 'employee-card');  // lands on the wrapper div

echo $page->draw();
```

```html
<nav aria-label="breadcrumb">crumbs</nav>
<div class='p-2 container-fluid Laf-UI-Page-AdminPage' id="employee-card">
    <div class='card border-dark shadow' style=''>
        <div class='card-header bg-light border-dark'>
            <div class='row'>
                <div class='col-lg-8 col-sm-12'>&nbsp; <span class='fw-bold text-uppercase text-decoration-none fs-5'><i class='fa fa-users'></i> <a href='?module=employee&action=list'>Employees</a></span> <small class='text-muted fw-normal fs-6'>of &quot;ch12 Acme&quot; &amp; partners</small></div>
                <div class='col-lg-4 col-sm-12 text-end'>
<a href="?module=employee&amp;action=new" style="" class="btn btn-sm btn-outline-success Laf-UI-Component-Link"><i class='fa fa-plus-square'></i> Add new</a>
</div>
            </div>
        </div>
        <div class='card-body'><p>body</p></div>
        <div class='card-footer'><small>3 rows</small></div>
    </div>
</div>
<!-- after the card -->
```

> **Note:** `setTitle()` and `setSubtitle()` take **HTML**, because the generated pages pass an anchor as the title.
> A title built from a record (`$employee->getFirstNameVal()`) must go through `Util::e()` first, or a name like
> `<script>` runs in every visitor's browser.

Other switches:

- `setShowCardHeader(false)` draws the card without its header row (no title, icon or links).
- `setCardCssClasses(['card', 'border-0'])` / `addCardCssClass()` change the card element (default
  `card border-dark shadow`); `setCardHeaderCssClasses()` / `addCardHeaderCssClass()` the header (default
  `card-header bg-light border-dark`). The component's own css classes and container type go on the outer `p-2`
  wrapper; its inline style (`addCssStyleItem()`) goes on the card.
- `disable()` makes `draw()` return `''` (handy for a section that a permission check hides); `enable()` undoes it.

```php
use Laf\UI\Page\GenericPage;

$plain = (new GenericPage())->setShowCardHeader(false)->setCardCssClasses(['card', 'border-0'])
    ->addComponent(new HtmlContainer('<p>no header</p>'));
```

```html
<div class='p-2 Laf-UI-Page-GenericPage'>
    <div class='card border-0' style=''>
        <div class='card-body'><p>no header</p></div>
    </div>
</div>
```

### `Page`: the public-site variant

`Laf\UI\Page\Page` extends `GenericPage` with a full-width banner section (background image, the title as `<h1>`)
and a centred column around the card. It is meant for public pages, not the admin area. When no banner image is set
and the application defines a global `getBannerImage()` function, its return value is used.

```php
use Laf\UI\Page\Page;

$about = (new Page())->setTitle('About us')->setBannerImage('img/banner.jpg')
    ->addComponent(new HtmlContainer('<p>text</p>'));
```

```html
<section class="innerbanner" style="background-image:url('img/banner.jpg');">
    <div class='container'><div class='row'><div class='col-12 text-center innerbanner--wrapper'>
        <h1 class='innerbanner-heading'>About us</h1>
    </div></div></div>
</section>
<section class='contact-us'>
    <div class='container animated fadeIn'>
        <div class='row'>
            <div class='col-sm-10 offset-sm-1 col-md-8 offset-md-2'>
                <div class='p-2 Laf-UI-Page-Page'>
                    <div class='card border-dark shadow' style=''> ... the card, title "About us" again ... </div>
                </div>
            </div>
        </div>
    </div>
</section>
```

The title appears twice (banner and card header); use `setShowCardHeader(false)` when you only want the banner. The
`innerbanner` / `contact-us` classes come from the original site template; style them in your CSS.

## Containers

Containers hold other components and draw them in order. `GenericContainer` is the base of all of them (`Div` is the
same class under the name pages use). Common rules:

- The constructor takes css classes and an inline style map: `new Div(['p-3', 'bg-white'], ['border' => '1px solid #ccc'])`.
- The attribute bag (`setAttribute('id', ...)`, `data-*`, `aria-*`) is drawn on the element, escaped. `class` and
  `style` in the bag are ignored (use `addCssClass()` / `addCssStyleItem()`); a `null` / `''` value is skipped.
- Every container adds its control class (`Laf-UI-Container-Div`, `Laf-UI-Container-Row`, ...) for styling and tests.
- `setContainerType(ContainerType::TYPE_DEFAULT | TYPE_FLUID)` adds `container` / `container-fluid`.
- Children without a draw mode of their own get the container's (see [draw modes](#componentinterface-componenttrait-and-draw-modes)).

### `Div` and `GenericContainer`

```php
use Laf\UI\Container\Div;
use Laf\UI\Container\GenericContainer;
use Laf\UI\Container\HtmlContainer;

$div = new Div(['p-3', 'bg-white'], ['border' => '1px solid #ccc']);
$div->setAttribute('id', 'box')->addAttribute('data-role', 'panel')->addAttribute('class', 'ignored');
$div->addComponent(new HtmlContainer('<p>inside</p>'));

$section = (new GenericContainer())->setTag('section');   // any tag name: section, nav, aside, ul, li ...
$section->setAttribute('aria-label', 'Team');
```

```html
<div style='border:1px solid #ccc' class='p-3 bg-white Laf-UI-Container-Div' id="box" data-role="panel">
    <p>inside</p>
</div>

<section style='' class='Laf-UI-Container-GenericContainer' aria-label="Team">
</section>
```

`setTag()` throws `InvalidArgumentException` for anything that is not a tag name.

### `Row` and `Column`

`Row` adds `row`; `Column` adds `col-sm`. Pass the width classes in the constructor:

```php
use Laf\UI\Container\Column;
use Laf\UI\Container\Row;

$row = new Row();
$row->addComponent((new Column(['col-lg-8']))->addComponent(new HtmlContainer('main')))
    ->addComponent((new Column(['col-lg-4']))->addComponent(new HtmlContainer('side')));
```

```html
<div style='' class='row Laf-UI-Container-Row'>
    <div style='' class='col-lg-8 col-sm Laf-UI-Container-Column'>main</div>
    <div style='' class='col-lg-4 col-sm Laf-UI-Container-Column'>side</div>
</div>
```

> **Note:** `Column` always adds `col-sm`, so below the `lg` breakpoint the two columns above are equal-width
> `col-sm` columns (side by side from 576 px), and stack only on phones. When you need "stacked until lg", use
> `new Div(['col-12', 'col-lg-8'])` instead of `Column`.

### `Card`, `CardHeader`, `CardBody`, `CardFooter`

Plain Bootstrap cards (each a `Div` that adds `card`, `card-header`, `card-body` or `card-footer`). Use them for
boxes inside a page; the page itself is already a card (`GenericPage`).

```php
use Laf\UI\Component\Link;
use Laf\UI\Container\Card;
use Laf\UI\Container\CardBody;
use Laf\UI\Container\CardFooter;
use Laf\UI\Container\CardHeader;

$card = new Card();
$card->addComponent((new CardHeader(['fw-bold']))->addComponent(new HtmlContainer('Ada Ch12')))
    ->addComponent((new CardBody())->addComponent(new HtmlContainer('<p>Engineer</p>')))
    ->addComponent((new CardFooter())->addComponent(new Link('Open', '?module=employee&action=view&id=20')));
```

```html
<div style='' class='card Laf-UI-Container-Card'>
    <div style='' class='fw-bold card-header Laf-UI-Container-CardHeader'>Ada Ch12</div>
    <div style='' class='card-body Laf-UI-Container-CardBody'><p>Engineer</p></div>
    <div style='' class='card-footer Laf-UI-Container-CardFooter'>
<a href="?module=employee&amp;action=view&amp;id=20" style="" class="Laf-UI-Component-Link">Open</a>
    </div>
</div>
```

### `Fieldset`

`new Fieldset(string $legend = '', array $classes = [], array $style = [])`; the legend is escaped and left out when
empty. Forms use it for `FormLayout::fieldset()` ([06-forms.md](06-forms.md)).

```php
use Laf\UI\Container\Fieldset;

$fs = new Fieldset('Contact <details>');
$fs->addComponent(new HtmlContainer('<p>fields</p>'));
```

```html
<fieldset style='' class='Laf-UI-Container-Fieldset'>
    <legend>Contact &lt;details&gt;</legend>
    <p>fields</p>
</fieldset>
```

### `HtmlContainer`

Raw HTML as a component: `draw()` returns the content exactly as given. It is how you mix hand-written markup (and
the string output of `PhpGrid::draw()` or `TabContainer::draw()`) into a component tree. It holds no children:
`addComponent()` throws `LogicException`.

```php
$hc = new HtmlContainer('<b>raw</b>');
$hc->addContent(' <i>more</i>');      // appends
echo $hc->draw();                      // <b>raw</b> <i>more</i>

$hc->addComponent(new Div());          // LogicException: HtmlContainer cannot hold other components; use setContent()/addContent() for raw HTML
```

> **Note:** an `HtmlContainer` is the most direct way to create an XSS hole. Escape every piece of data you put into
> it with `Util::e()` (text, single-quoted attributes) or `Util::attr()` (double-quoted attributes).

### `TabContainer` and `TabItem`

`new TabContainer(?string $id = '', bool $showNavButtons = false)` draws Bootstrap tabs: a nav (`{id}_tab_links`)
with one link per `TabItem` child and the panes (`{id}_tab_content`). An empty id is generated (`tabs-` + a UUID).
`new TabItem(string $title = '', string $icon = '', bool $active = false)`: the title is escaped, the icon is a css
class. The first tab is active unless one was made active (`setActiveTab($titleOrIndex)` on the container, or
`setActive(true)` on the item); when several are active only the first stays active. Children that are not
`TabItem`s are not drawn. `$showNavButtons = true` adds "Previous" / "Next" buttons (plain JavaScript).

```php
use Laf\UI\Container\TabContainer;
use Laf\UI\Container\TabItem;

$tabs = new TabContainer('employee_tabs', true);
$tabs->addComponent((new TabItem('Details', 'fa fa-user'))->addComponent(new HtmlContainer('<p>details</p>')))
    ->addComponent((new TabItem('Projects & <roles>'))->addComponent(new HtmlContainer('<p>projects</p>')));
$tabs->setActiveTab('Details');
```

```html
<div style='' class='Laf-UI-Container-TabContainer'>
    <ul class='nav nav-tabs' id='employee_tabs_tab_links' role='tablist'>
        <li class='nav-item'><a class='nav-link active' id='Details-tab' data-toggle='tab' data-bs-toggle='tab'
            href='#Details-content' role='tab' style='text-decoration:none;color:#000000'
            aria-controls='Details-content'><i class='fa fa-user'></i> Details</a></li>
        <li class='nav-item'><a class='nav-link' id='Projectsroles-tab' data-toggle='tab' data-bs-toggle='tab'
            href='#Projectsroles-content' role='tab' style='text-decoration:none;color:#000000'
            aria-controls='Projectsroles-content'>Projects &amp; &lt;roles&gt;</a></li>
    </ul>
    <div class='tab-content' id='employee_tabs_tab_content'>
        <div class='tab-pane fade show active' id='Details-content' role='tabpanel' aria-labelledby='Details-tab'>
            <div class='container-fluid py-4'><p>details</p></div>
        </div>
        <div class='tab-pane fade show' id='Projectsroles-content' role='tabpanel' aria-labelledby='Projectsroles-tab'>
            <div class='container-fluid py-4'><p>projects</p></div>
        </div>
    </div>
    <div class='text-end'>
        <a href='javascript:;' class='btn btn-outline-success' onclick="...previousElementSibling..."><i class='fa fa-arrow-alt-circle-left'> </i> Previous</a>
        <a href='javascript:;' class='btn btn-outline-success' onclick="...nextElementSibling..."><i class='fa fa-arrow-alt-circle-right'> </i> Next</a>
    </div>
</div>
```

> **Note:** the link and pane ids come from the title alone (letters and digits: `Details-tab`,
> `Details-content`), not from the container id. Two tab sets on one page with a tab of the same title (a form's
> "Details" tab and yours) clash. Give each item a prefix: `(new TabItem('Details'))->setIdPrefix('employee_tabs')`
> gives `employee_tabs_Details-tab`. The generated pages do this for their related-records tabs.

`TabItem` draws only its pane and its nav link; css classes added to a `TabItem` are not rendered (put a `Div`
inside the tab instead). `TabContainer::navigationScript($id, 'next'|'previous')` returns the JavaScript the
buttons use, if you want your own buttons.

### `Modal`

`new Modal(string $id)` draws a Bootstrap modal: title (raw HTML), the components as the body, a cancel button and
your footer buttons. With `setIncludeForm(true)` the header, body and footer are wrapped in a `<form>`
(`setFormAction()`, `setFormMethod()` = `GET` (default) or `POST`, `setFormId()`, `setFormHasFiles()`), and
`addHiddenInput()` puts hidden fields into it, which is how a modal posts a CSRF token.

```php
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Container\Modal;
use Laf\UI\Form\Control\Button;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\InputType;
use MyApp\Employee;

$employee = new Employee(20);
$form = $employee->getForm(DrawMode::VIEW);   // here only to borrow its CSRF token

$delete = new Button();
$delete->setType(InputType::Submit);
$delete->setValue('Delete');
$delete->setIcon('fa fa-trash');
$delete->setWrapInDiv(false);                 // a bare <button>, not a form row
$delete->addCssClass('btn')->addCssClass('btn-danger');

$modal = new Modal('deleteEmployee');
$modal->setTitle("<i class='fa fa-trash'></i> Delete employee?")
    ->setSize(Modal::SIZE_SMALL)
    ->setCentered(true)
    ->setIncludeForm(true)
    ->setFormMethod('post')
    ->setFormAction('?module=employee_tools&action=delete&id=20')
    ->addHiddenInput($form->getCsrfSessionKey(), $form->getCsrfToken())
    ->addFooterButton($delete)
    ->addComponent(new HtmlContainer('<p>This cannot be undone.</p>'));

$page->addComponent($modal);
$page->addLink($modal->getModalOpenButton('Delete…', 'btn btn-outline-danger btn-sm'));
```

```html
<div class='modal fade' id='deleteEmployee' tabindex='-1' role='dialog' aria-labelledby='deleteEmployeeLabel' aria-hidden='true' data-bs-keyboard='true'>
    <div class='modal-dialog modal-sm modal-dialog-scrollable modal-dialog-centered' role='document'>
        <div class='modal-content'>
            <form role='form' action='?module=employee_tools&amp;action=delete&amp;id=20' method='POST'>
            <input type='hidden' name='_csrf_token_employee' value='b115850626f8…e198' />
            <div class='modal-header'>
                <h5 class='modal-title' id='deleteEmployeeLabel'><i class='fa fa-trash'></i> Delete employee?</h5>
                <button type='button' class='btn-close' data-dismiss='modal' data-bs-dismiss='modal' aria-label='Close'></button>
            </div>
            <div class='modal-body'><p>This cannot be undone.</p></div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-outline-secondary' data-dismiss='modal' data-bs-dismiss='modal'>Cancel</button>
                <button type="submit" class='btn btn-danger Laf-UI-Form-Control-Button' style=''><i class='fa fa-trash'></i> Delete</button>
            </div>
            </form>
        </div>
    </div>
</div>

<a href="javascript:;" data-toggle="modal" data-bs-toggle="modal" data-target="#deleteEmployee"
   data-bs-target="#deleteEmployee" style="" class="btn btn-outline-danger btn-sm Laf-UI-Component-Link">Delete…</a>
```

The handler behind `employee_tools` checks the token with `$form->isValidCsrfToken($_POST[$form->getCsrfSessionKey()] ?? null)`
(the same check the generated delete action does, see [17-security.md](17-security.md)).

Options: `setSize(Modal::SIZE_NORMAL | SIZE_SMALL | SIZE_LARGE | SIZE_XLARGE | SIZE_FULLSCREEN)`
(`SISZE_NORMAL` is a deprecated misspelling), `setScrollable(bool)` (default true), `setCentered(bool)`,
`setStaticBackdrop(bool)` (a click outside does not close it), `setCancelLabel()` (escaped),
`setShowCancelButton(false)`. `addFooterButton()` takes a `FormElementInterface` (a `Button`, a `Link`).

> **Note:** a `Button` added to a modal footer draws a whole form row (label column and all) unless you call
> `setWrapInDiv(false)`. Its default `type` is `button`; set `InputType::Submit` when it should submit the modal form.

## Components

### `Link`

`new Link(?string $text = '', ?string $href = '', ?string $icon = '', array $attributes = [], array $class = [])`
draws an `<a>`. The caption is **raw HTML** (pages pass icons and badges); every attribute of the bag (`href`,
`target`, `rel`, `title`, `onclick`, `data-*`) is drawn, escaped.

```php
use Laf\UI\Component\Link;
use Laf\UI\Component\Rel;
use Laf\UI\Component\Target;

$link = new Link('<b>Ada</b>', '?module=employee&action=view&id=20', 'fa fa-user', ['data-id' => '20'], ['btn', 'btn-link']);
$link->setTarget(Target::Blank)->setRel(Rel::Noopener . ' ' . Rel::Noreferrer)->setTitle('Open "Ada"');

$delete = new Link('Delete', '?module=employee&action=delete&id=20');
$delete->setConfirmationMessage("Delete Ada's record?\nThis cannot be undone.");

$disabled = new Link('Disabled', '#');
$disabled->setDisabled(true);
```

```html
<a data-id="20" href="?module=employee&amp;action=view&amp;id=20" target="_blank" rel="noopener noreferrer"
   title="Open &quot;Ada&quot;" style="" class="btn btn-link Laf-UI-Component-Link"><i class='fa fa-user'></i> <b>Ada</b></a>

<a href="?module=employee&amp;action=delete&amp;id=20"
   onclick="return confirm('Delete Ada\'s record?\nThis cannot be undone.')" style="" class="Laf-UI-Component-Link">Delete</a>

<a href="#" aria-disabled="true" tabindex="-1" style="" class="disabled Laf-UI-Component-Link">Disabled</a>
```

- `setConfirmationMessage()` escapes the text for JavaScript (quotes, backslashes, line breaks, `</`), so any text
  is safe there; it replaces an existing `onclick`.
- `setDisabled(true)` adds the `disabled` class, `aria-disabled` and `tabindex="-1"`; the `href` stays (Bootstrap
  blocks the click with CSS).
- `class`, `style` and `value` in the attribute array are not drawn as attributes: classes come from the fifth
  constructor argument or `addCssClass()`, style from `addCssStyleItem()`, the caption from `setValue()`.
- A link is not bound to a field: `getField()` is `null` and `setField()` throws `LogicException`.
- `Target` constants: `Blank`, `Parent`, `Self`, `Top`. `Rel` constants: `Alternate`, `Author`, `Bookmark`,
  `External`, `Help`, `License`, `Next`, `Nofollow`, `Noreferrer`, `Noopener`, `Prev`, `Search`, `Tag`.

> **Note:** a link that changes data (delete, approve) must not be a plain GET link anyone can forge. Append the
> form's CSRF token with `$form->withCsrfToken($url)` and check it in the handler, as the generated pages do.

### `Dropdown`

`new Dropdown(string $text = '', string $class = '', string $icon = '', bool $rightAlign = false)`: a toggle button
(text escaped) with a menu. `addLink()` adds `Link`s (drawn as `dropdown-item`, their `btn` / `btn-sm` classes
removed), `addDivider()`, `addHeader($text)` (escaped) and `addHtml($html)` (raw) add the rest, in order.
`setAttribute('id', ...)` names the toggle (otherwise an id is generated per draw).

```php
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;

$dd = new Dropdown('Options <x>', 'btn-outline-success', 'fa fa-cogs', true);
$dd->setAttribute('id', 'employeeOptions');
$dd->addHeader('Record')
    ->addLink(new Link('Update', '?module=employee&action=update&id=20', 'fa fa-edit', [], ['btn', 'btn-sm']))
    ->addDivider()
    ->addHtml("<button type='submit' form='archiveForm' class='dropdown-item'>Archive</button>");
$dd->addMenuCssClass('dropdown-menu-dark');
```

```html
<a href="#" style='' class='btn-outline-success Laf-UI-Component-Dropdown btn dropdown-toggle'
   role='button' data-toggle='dropdown' data-bs-toggle='dropdown' id='employeeOptions' aria-haspopup='true' aria-expanded='false'>
    <i class='fa fa-cogs'></i> Options &lt;x&gt;
</a>
<div class='dropdown-menu dropdown-menu-right dropdown-menu-end dropdown-menu-dark' aria-labelledby='employeeOptions'>
    <h6 class='dropdown-header'>Record</h6>
<a href="?module=employee&amp;action=update&amp;id=20" style="" class="dropdown-item Laf-UI-Component-Link"><i class='fa fa-edit'></i> Update</a>
    <div class='dropdown-divider'></div>
    <button type='submit' form='archiveForm' class='dropdown-item'>Archive</button>
</div>
```

The toggle and the menu are drawn as siblings, without a `.dropdown` wrapper; the generated pages put it straight
into the page header with `addLink()`. When you place one in your own layout and want Bootstrap's positioning
context, wrap it: `(new Div(['dropdown', 'd-inline-block']))->addComponent($dd)`. `rightAlign()` / `leftAlign()`
switch the menu edge after construction.

### `Image`

`new Image(string $src = '', string $alt = '')` draws an `<img>` with the classes `bg-light rounded p-2`;
`setWidth()` / `setHeight()` (pixels) become inline style, `setLazy(true)` adds `loading="lazy"`, other attributes
come from the bag.

```php
use Laf\UI\Component\Image;

$img = new Image('uploads/ada.jpg', 'Ada "Ch12"');
$img->setWidth(120);
$img->setLazy(true);
```

```html
<img src="uploads/ada.jpg" alt="Ada &quot;Ch12&quot;" loading="lazy"
     class='Laf-UI-Component-Image bg-light rounded p-2' style='width:120px' />
```

For stored uploads build the `src` with the document URLs of [15-files-and-documents.md](15-files-and-documents.md).

### `Alert`

`new Alert(string $title = '', string $message = '', string $type = Alert::Type_Primary)`. Title and message are
**raw HTML**. An empty message draws nothing. `setDismissible(true)` adds the close button, `setIcon()` an icon
before the message, `draw(true)` centres it in a narrow column.

```php
use Laf\UI\Component\Alert;

$alert = new Alert('Saved', 'Employee <b>Ada</b> was saved.', Alert::Type_Success);
$alert->setDismissible(true)->setIcon('fa fa-check');
echo $alert->draw();
echo (new Alert('', 'Centered', Alert::Type_Info))->draw(true);
var_export((new Alert('Title only'))->draw());   // ''
```

```html
<div class='alert alert-success alert-dismissible fade show' role='alert'>
  <h4 class='alert-heading'>Saved</h4>
  <p><i class='fa fa-check'></i> Employee <b>Ada</b> was saved.</p>
  <button type='button' class='btn-close' data-dismiss='alert' data-bs-dismiss='alert' aria-label='Close'></button>
</div>

<div class='col-sm-12 offset-md-4 col-md-4'>
<div class='alert alert-info' role='alert'>
  <p>Centered</p>
</div>
</div>
```

Types: `Type_Primary`, `Type_Secondary`, `Type_Success`, `Type_Danger`, `Type_Warning`, `Type_Info`, `Type_Light`,
`Type_Dark`. CSS classes added with `addCssClass()` are not drawn on an alert; wrap it in a `Div` for spacing.

> **Note:** for a message that contains user input (`"Employee $name saved"`), escape the input with `Util::e()`, or
> use `Flash`, which escapes for you.

### `Carousel`

`new Carousel(array $documents = [], ?string $id = null, array $images = [], bool $indicators = true, bool $controls = true)`:
image URLs (`setImages()`, `addImage()`) and stored document ids (`setDocuments()`, `addDocument()`), shown images
first. A document id becomes a URL through a process-wide builder, default `/document/view/{id}/1.jpg`; set your
own with the static `Carousel::setDocumentUrlBuilder(fn (int $id) => ...)` (null restores the default). No slides =
nothing drawn.

```php
use Laf\UI\Component\Carousel;

$carousel = new Carousel([12, 13], 'teamPhotos', ['img/office.jpg']);
echo $carousel->draw();

Carousel::setDocumentUrlBuilder(fn (int $id) => '?module=document&action=view&id=' . $id);
print_r((new Carousel([12]))->getSlides());   // ['?module=document&action=view&id=12']
```

```html
<div id='teamPhotos' class='carousel slide' data-ride='carousel' data-bs-ride='carousel'>
	<ol class='carousel-indicators'>
		<li data-target='#teamPhotos' data-bs-target='#teamPhotos' data-slide-to='0' data-bs-slide-to='0' class='active' aria-current='true'></li>
		<li ... data-bs-slide-to='1' class=''></li>
		<li ... data-bs-slide-to='2' class=''></li>
	</ol>
	<div class='carousel-inner'>
		<div class='carousel-item active'><img class='d-block w-100' src="img/office.jpg" alt='image'></div>
		<div class='carousel-item'><img class='d-block w-100' src="/document/view/12/1.jpg" alt='image'></div>
		<div class='carousel-item'><img class='d-block w-100' src="/document/view/13/1.jpg" alt='image'></div>
	</div>
	<a class='carousel-control-prev' href='#teamPhotos' role='button' data-slide='prev' data-bs-slide='prev'>...</a>
	<a class='carousel-control-next' href='#teamPhotos' role='button' data-slide='next' data-bs-slide='next'>...</a>
</div>
```

`useBgImages()` / `setUseBgImages(true)` draws each slide as a `div` with a background image (give
`.carousel-item` a height in your CSS). `setTopContent($html)` adds raw HTML after the slides (captions, overlays).
`setShowIndicators(false)` / `setShowControls(false)` drop the dots and arrows.

## Flash messages

`Laf\UI\Flash` keeps one-shot messages for the **next** page: queue one before a redirect, drain them on the page that
draws. They live in the session (`$_SESSION['laf_flash']`) when a session is active, otherwise only for the current
request.

```php
use Laf\UI\Component\Alert;
use Laf\UI\Flash;

// after a write, before the redirect
Flash::success('Saved <Ada>');                                         // escaped when drawn
Flash::html('Open <a href="?module=employee&action=view&id=20">Ada</a>', Alert::Type_Info);   // markup, drawn raw

// on the next page
foreach (Flash::drain() as $alert) {      // Alert[], dismissible, and the queue is emptied
    $page->addComponent($alert);
}
var_export(Flash::has());                 // false
```

```html
<div class='alert alert-success alert-dismissible fade show' role='alert'>
  <p>Saved &lt;Ada&gt;</p>
  <button type='button' class='btn-close' data-dismiss='alert' data-bs-dismiss='alert' aria-label='Close'></button>
</div>

<div class='alert alert-info alert-dismissible fade show' role='alert'>
  <p>Open <a href="?module=employee&action=view&id=20">Ada</a></p>
  <button type='button' class='btn-close' data-dismiss='alert' data-bs-dismiss='alert' aria-label='Close'></button>
</div>
```

| Method | What it does |
|---|---|
| `success($m)`, `error($m)`, `warning($m)`, `info($m)` | queue an escaped message with the matching `Alert::Type_*` (`error` = `danger`) |
| `html($markup, $type = Alert::Type_Success)` | queue markup drawn unescaped (only for text the application builds itself) |
| `add($message, $type = Alert::Type_Success, $html = false)` | the general form |
| `has(): bool` | whether anything is queued |
| `peek(): array` | the queue (`[['message', 'type', 'html'], ...]`), oldest first, not removed |
| `pull(): array` | the queue, then emptied |
| `drain(): Alert[]` | the queue as dismissible alerts, then emptied |
| `clear()` | empty the queue |

`Flash::SESSION_KEY` is `'laf_flash'`. The legacy keys `$_SESSION['flash_message']` / `$_SESSION['flash_type']`
written by older pages are read and cleared too.

> **Note:** start the session (`session_start()`) in your front controller **before** the page runs. `drain()` only
> sees the session queue when a session is active at that moment; the generated pages drain before anything else
> would start one, so without an early `session_start()` the "Record saved" message after a redirect is lost.

## `ComponentInterface`, `ComponentTrait` and draw modes

Every component implements `Laf\UI\ComponentInterface`; `Laf\UI\Traits\ComponentTrait` implements nearly all of it,
so a component class only writes `draw()` and `getComponentCssControlClass()`. What the trait holds:

- **CSS classes** of the element (`addCssClass()`, `addCssClasses(...$c)`, `removeCssClass()`, `hasCssClass()`,
  `setCssClasses()`, `getCssClasses()`), kept unique.
- **Inline style** (`addCssStyleItem($property, $value)` / alias `setCssStyleItem()`, `getCssStyleItem()`,
  `removeCssStyle()`, `hasCssStyleItem()`, `setCssStyles()`, `getCssStyles()`).
- **An attribute bag** (`addAttribute($k, $v)` / alias `setAttribute()`, `addAttributes([...])`, `getAttribute()`,
  `hasAttribute()`, `removeAttribute()`, `setAttributes()`, `getAttributes()`). A `null` or `''` value counts as
  absent: `hasAttribute()` is false and nothing is drawn.
- **Wrapper** classes, style and attributes (`addWrapperCssClass()`, `addWrapperCssStyleItem()`,
  `addWrapperAttribute()` and their getters/setters/removers): used by form inputs, which draw a wrapper `div`
  around label and control ([07-form-inputs.md](07-form-inputs.md)). Containers and the components on this page
  ignore them.
- **Children** (`addComponent()`, `getComponents()`, `setComponents()`, `hasComponents()`).
- **Draw mode** (`setDrawMode()`, `getDrawMode()`) and **form row display mode** (`setFormRowDisplayMode()`,
  `getFormRowDisplayMode()`).
- **Container type** (`setContainerType(ContainerType::*)`, `getContainerType()`).

`getComponentCssControlClass()` returns the class name with `\` replaced by `-` (`Laf-UI-Container-Div`); every
LAF component adds it to its own classes when drawn.

### Draw modes

`Laf\UI\Form\DrawMode` has three values: `VIEW` (`'view'`), `INSERT` (`'insert'`) and `UPDATE` (`'update'`), plus
the lists `DrawMode::ALL` and `DrawMode::EDIT` (insert + update). Form elements draw a read-only value in `VIEW` and
an input otherwise. A component with no draw mode reports `''` (never `null`), and containers hand theirs down to
such children when they draw. So you can set the mode once, high up:

```php
use Laf\UI\Container\Div;
use Laf\UI\Form\DrawMode;
use MyApp\Employee;

$employee = new Employee(20);
$box = new Div();
$box->setDrawMode(DrawMode::VIEW);
$box->addComponent($employee->getFirstNameFormElement());   // no draw mode of its own: inherits VIEW
echo $box->draw();
```

```html
<div style='' class='Laf-UI-Container-Div'>
    <div id='svefg_anzr_container' style='min-width:0;' class='mb-3 row'>
        <label id='svefg_anzr_label' for='svefg_anzr' class='col-sm-2 col-form-label'>First Name* :</label>
        <div class='col-sm-10'>
            <div id='svefg_anzr' class='Laf-UI-Form-Input-Text form-control-plaintext border border-secondary border-1 bg-light rounded p-2' style='max-width:100%;overflow-wrap:break-word;word-break:break-all;'>Ada</div>
        </div>
    </div>
</div>
```

A child that already has a mode keeps it (a read-only field inside an edit form). The row display mode
(`FormRowDisplayMode::ROW` = label left / input right, `INLINE` = stacked) is always pushed down by containers.
Forms set modes for you through `getForm($drawMode)` and `FormLayout`; see [06-forms.md](06-forms.md).

## Writing your own component

Implement `ComponentInterface`, use `ComponentTrait`, write `draw()` and `getComponentCssControlClass()`. Escape
text with `Util::e()` and double-quoted attribute values with `Util::attr()`.

```php
use Laf\UI\ComponentInterface;
use Laf\UI\Container\Div;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Util;

final class StatusBadge implements ComponentInterface
{
    use ComponentTrait;

    public function __construct(private string $text, private string $colour = 'secondary')
    {
    }

    public function draw(): ?string
    {
        $this->addCssClass('badge')->addCssClass('text-bg-' . $this->colour);
        $attributes = '';
        foreach ($this->getAttributes() as $key => $value) {
            if ($value !== null && $value !== '') {
                $attributes .= ' ' . $key . '="' . Util::attr($value) . '"';
            }
        }
        return '<span class="' . Util::attr(join(' ', $this->getCssClasses())) . '"' . $attributes . '>'
            . Util::e($this->text) . '</span>';
    }

    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}

$box = new Div(['d-flex', 'gap-2']);
$box->addComponent((new StatusBadge('Active <1>', 'success'))->addAttribute('title', 'Since "2024"'))
    ->addComponent(new StatusBadge('Remote'));
echo $box->draw();
```

```html
<div style='' class='d-flex gap-2 Laf-UI-Container-Div'>
    <span class="badge text-bg-success" title="Since &quot;2024&quot;">Active &lt;1&gt;</span>
    <span class="badge text-bg-secondary">Remote</span>
</div>
```

For a component that only wraps others, extending `Div` (or `GenericContainer`) is shorter: override `draw()`, add
your classes, return `parent::draw()`, the way `Row` and `Card` do.

## Raw HTML vs escaped text

LAF stores values verbatim and escapes at output. Most component text is escaped for you; the places below take
**HTML** on purpose, so data you put there must be escaped first (`Util::e()` / `Util::attr()`, see
[16-settings-locale-utilities.md](16-settings-locale-utilities.md) and [17-security.md](17-security.md)).

| Raw HTML (escape data yourself) | Escaped by LAF |
|---|---|
| `GenericPage::setTitle()`, `setSubtitle()`, `setHeader()`, `setFooter()`; `Page` banner title | `Html::setPageTitle()` |
| `Html::setMenu()`, `setFooterHtml()`, `setHtmlHeader()`; inline script/style content | `HeaderComponent` attribute values, `Html` body attributes |
| `HtmlContainer` content | `Fieldset` legend, `TabItem` title |
| `Link` caption (`setValue()` / constructor text) | `Link` attributes (`href`, `title`, `data-*`), confirmation message (JS-escaped) |
| `Dropdown::addHtml()` | `Dropdown` text, `addHeader()` |
| `Alert` title and message | `Image` attributes, `Carousel` URLs |
| `Modal::setTitle()`, `Carousel::setTopContent()` | `Modal` cancel label, form action, hidden input names/values |
| `Flash::html()` | `Flash::success()` / `error()` / `warning()` / `info()` / `add()` |

## A full page assembled

An employee profile page: the shared shell from `Factory::GeneralPage()`, a card with an escaped title and an
options dropdown, flash messages, a warning for inactive employees, a summary card next to the record (drawn by its
own form in `VIEW` mode), and tabs with the employee's projects (through the `employee_project` linker table) and
notes. Routing is covered in [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md); this file is
what a front controller would include for `?module=profile&id=20`.

```php
<?php
// pages/profile.page
use Laf\UI\Component\Alert;
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;
use Laf\UI\Container\Card;
use Laf\UI\Container\CardBody;
use Laf\UI\Container\CardHeader;
use Laf\UI\Container\Column;
use Laf\UI\Container\ContainerType;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Container\Row;
use Laf\UI\Container\TabContainer;
use Laf\UI\Container\TabItem;
use Laf\UI\Flash;
use Laf\UI\Form\DrawMode;
use Laf\UI\Page\AdminPage;
use Laf\UI\Table\Table;
use Laf\Util\Locale;
use Laf\Util\UrlParser;
use Laf\Util\Util;
use MyApp\Employee;
use MyApp\Factory;

$employee = Employee::load(UrlParser::getId());
if ($employee === null) {
    Flash::error('Employee not found');
    header('Location: ?module=employee&action=list');
    exit;
}
$name = $employee->getFirstNameVal() . ' ' . $employee->getLastNameVal();

$html = Factory::GeneralPage();
$html->setPageTitle($name);                                   // escaped by Html

$page = new AdminPage();
$page->setTitle(Util::e($name))                               // raw HTML: escape the data
    ->setSubtitle(Util::e($employee->getCompanyIdObj()->getNameVal() ?? ''))
    ->setContainerType(ContainerType::TYPE_FLUID);
$page->setTitleIcon('fa fa-user');

$options = new Dropdown('Options', 'btn-outline-success', 'fa fa-cogs', true);
$options->addCssClass('btn-sm');
$options->addLink(new Link('Edit', '?module=employee&action=update&id=' . $employee->getIdVal(), 'fa fa-edit'))
    ->addDivider()
    ->addLink(new Link('All employees', '?module=employee&action=list', 'far fa-list-alt'));
$page->addLink($options);

foreach (Flash::drain() as $alert) {
    $page->addComponent($alert);
}
if (!$employee->getIsActiveVal()) {
    $page->addComponent(new Alert('', 'This employee is inactive.', Alert::Type_Warning));
}

// left: a summary card, right: the record drawn by its own form in VIEW mode
$summary = new Card(['h-100']);
$summary->addComponent((new CardHeader())->addComponent(new HtmlContainer('<b>Summary</b>')))
    ->addComponent((new CardBody())->addComponent(new HtmlContainer(
        '<p class="mb-1">Salary: ' . Util::e(Locale::formatNumber($employee->getSalaryVal(), 2, '-')) . '</p>'
        . '<p class="mb-0">Born: ' . Util::e(Locale::formatDate($employee->getBirthDateVal(), '-')) . '</p>'
    )));

$row = new Row();
$row->addComponent((new Column(['col-lg-4']))->addComponent($summary))
    ->addComponent((new Column(['col-lg-8']))->addComponent($employee->getForm(DrawMode::VIEW)));
$page->addComponent($row);

// tabs: the linked projects as a plain table, and the notes
$projects = [];
foreach ($employee->manyToMany('employee_project')->objects() as $project) {
    $projects[] = [$project->getNameVal(), Locale::formatDate($project->getStartsOnVal(), '-')];
}
$tabs = new TabContainer('profile_tabs');
$tabs->addComponent((new TabItem('Projects', 'fa fa-briefcase'))->setIdPrefix('profile_tabs')
    ->addComponent(Table::fromArray($projects, ['Project', 'Starts on'])->bootstrap()));
$tabs->addComponent((new TabItem('Notes'))->setIdPrefix('profile_tabs')
    ->addComponent(new HtmlContainer('<p>' . nl2br(Util::e((string)$employee->getNotesVal())) . '</p>')));
$page->addComponent($tabs);

$html->addComponent($page);
echo $html->draw();
```

Excerpt of the output for employee 20 (Ada Ch12 of "ch12 Acme", one project):

```html
<title>Ada Ch12</title>
...
<div class='col-lg-8 col-sm-12'>&nbsp; <span class='fw-bold text-uppercase text-decoration-none fs-5'><i class='fa fa-user'></i> Ada Ch12</span> <small class='text-muted fw-normal fs-6'>ch12 Acme</small></div>
...
<div style='' class='card-header Laf-UI-Container-CardHeader'><b>Summary</b></div>
<div style='' class='card-body Laf-UI-Container-CardBody'><p class="mb-1">Salary: -</p><p class="mb-0">Born: -</p></div>
...
<ul class='nav nav-tabs' id='employee_form_tabs_tab_links' role='tablist'>   <!-- the form's own tabs (FormLayout) -->
...
<ul class='nav nav-tabs' id='profile_tabs_tab_links' role='tablist'>
...
<div class='table-responsive'><table id='tbl_ee7dd008' class='table table-striped table-hover table-sm'><thead><tr id='tbl_ee7dd008_head_0'><th id='tbl_ee7dd008_head_0_0'>Project</th><th id='tbl_ee7dd008_head_0_1'>Starts on</th></tr></thead><tbody><tr id='tbl_ee7dd008_0'><td id='tbl_ee7dd008_0_0'>ch12 Apollo</td><td id='tbl_ee7dd008_0_1'>-</td></tr></tbody></table></div>
```

Why the pieces sit where they do: the redirect happens before any output; the title and subtitle are escaped
because `setTitle()` is raw HTML; the tab items get a prefix because the form already drew a tab set of its own; the
table cells are text, escaped by `Table` ([09-lists-and-tables.md](09-lists-and-tables.md)).

## Reference

### `Laf\UI\Page\Html`

| Method | What it does | Notes |
|---|---|---|
| `setPageTitle(string)`, `getPageTitle()` | `<title>` | escaped |
| `setLanguage(?string)`, `getLanguage()` | `<html lang>` | null/'' = Settings `locale.language`, else `en` |
| `addHeaderComponent(HeaderComponent)`, `setHeaderComponents(array)`, `getHeaderComponents()` | head tags after the title | |
| `addBodyEndComponent(HeaderComponent)`, `getBodyEndComponents()` | tags before `</body>` | |
| `addScript($src, $attributes = [], $atBodyEnd = false)` | `<script src>` | |
| `addInlineScript($code, $atBodyEnd = false)` | `<script>code</script>` | content raw |
| `addStylesheet($href, $attributes = [])`, `addInlineStyle($css)` | stylesheet link, `<style>` | style content raw |
| `addMeta($name, $content)`, `addLinkTag($rel, $href, $attributes = [])` | `<meta>`, `<link>` | |
| `setFavicon($href)` | icon, shortcut icon, apple-touch-icon links | |
| `setHtmlHeader(string)`, `getHtmlHeader()` | raw HTML at the end of the head | raw |
| `setBodyAttributes(array)`, `addBodyAttribute($k, ?$v)`, `addBodyCssClass($c)`, `getBodyAttributes()` | `<body>` attributes | default `style="background-color:#EEEEEE"`; values escaped |
| `setMenu(string)`, `getMenu()` | HTML right after `<body>` | raw |
| `addComponent(ComponentInterface)`, `setComponents(array)`, `getComponents()` | the body components | |
| `addFooterComponent(ComponentInterface)`, `getFooterComponents()` | after the components | |
| `setFooterHtml(string)`, `getFooterHtml()` | after the footer components | raw |
| `draw(bool $no_closing_tags = false): string` | the document | `true` stops after the components |

### `Laf\UI\Page\HeaderComponent`

| Method | What it does |
|---|---|
| `__construct($tagName, $attributes = [], $selfClosingTag = false, $content = null)` | any tag; invalid tag name throws `InvalidArgumentException` |
| `::script($src, $attributes = [])`, `::inlineScript($code, $attributes = [])` | script tags |
| `::stylesheet($href, $attributes = [])`, `::inlineStyle($css)` | stylesheet link, style tag |
| `::meta($name, $content)`, `::link($rel, $href, $attributes = [])` | meta and link tags |
| `draw(): string` | the tag; attribute `null`/`true` = bare, `false` = left out |
| `getTagName()`, `setTagName()`, `getAttributes()`, `setAttributes()`, `addAttribute($k, $v)`, `removeAttribute()` | tag and attributes |
| `getContent()`, `setContent()`, `isSelfClosingTag()`, `setSelfClosingTag()`, `isVoidElement()` | content (raw) and closing; `VOID_ELEMENTS` = meta, link, base |

### `Laf\UI\Page\GenericPage` (`AdminPage`, `Page`)

| Method | What it does | Notes |
|---|---|---|
| `setTitle(?string)`, `getTitle()` | card title | raw HTML |
| `setSubtitle(?string)`, `getSubtitle()` | small text after the title | raw HTML |
| `setTitleIcon(?string)`, `getTitleIcon()` | icon css class before the title | returns `void` |
| `addLink(ComponentInterface)`, `setLinks(array)`, `getLinks()`, `hasLinks()` | right side of the header row | Link, Dropdown, anything |
| `addComponent()` (trait) | card body | |
| `addFooterComponent()`, `setFooterComponents()`, `getFooterComponents()` | `card-footer` | drawn only when not empty |
| `setHeader(?string)`, `getHeader()`, `setFooter(?string)`, `getFooter()` | HTML before / after the card | raw |
| `setShowCardHeader(bool)`, `isCardHeaderShown()` | header row on/off | |
| `setCardCssClasses(array)`, `addCardCssClass()`, `getCardCssClasses()` | card classes | default `card border-dark shadow` |
| `setCardHeaderCssClasses(array)`, `addCardHeaderCssClass()`, `getCardHeaderCssClasses()` | header classes | default `card-header bg-light border-dark` |
| `enable()`, `disable()`, `isEnabled()` | disabled = draws `''` | |
| `draw(): string` | the card | |
| `Page::setBannerImage(?string)`, `getBannerImage()` | banner background | falls back to a global `getBannerImage()` |

### Containers (`Laf\UI\Container`)

| Class / method | What it does | Notes |
|---|---|---|
| `GenericContainer::__construct(array $classes = [], array $style = [])` | base container | |
| `setTag(string)`, `getTag()` | element name (default `div`) | invalid name throws |
| `draw()` | open tag, children, close tag | |
| `Div` | `GenericContainer` named for pages | |
| `Row`, `Column` | add `row` / `col-sm` | Column keeps `col-sm` |
| `Card`, `CardHeader`, `CardBody`, `CardFooter` | add `card`, `card-header`, `card-body`, `card-footer` | |
| `Fieldset::__construct($legend = '', $classes = [], $style = [])`, `getLegend()`, `setLegend()` | `<fieldset>` + `<legend>` | legend escaped |
| `HtmlContainer::__construct($content = '')`, `getContent()`, `setContent()`, `addContent()` | raw HTML | `addComponent()` throws `LogicException` |
| `TabContainer::__construct(?$id = '', $showNavButtons = false)` | tabs | empty id = `tabs-{uuid}` |
| `TabContainer::getTabs()`, `setActiveTab(int\|string)`, `getId()`, `setId()`, `isShowNavButtons()`, `setShowNavButtons()`, `::navigationScript($id, $direction)` | | |
| `TabItem::__construct($title = '', $icon = '', $active = false)` | one tab | title escaped |
| `TabItem::isActive()`, `setActive()`, `getTitle()`, `setTitle()`, `getIcon()`, `setIcon()`, `setIdPrefix()`, `getIdPrefix()`, `getId()`, `getContentId()`, `getTitleNoSpaces()`, `drawTitle()` | | ids `{prefix_}{Title}-tab` / `-content` |
| `Modal::__construct(string $id)`, `getId()`, `setId()` | dialog | |
| `Modal::setTitle()`, `getTitle()` | title | raw HTML |
| `Modal::addFooterButton(FormElementInterface)`, `setFooterButtons()`, `getFooterButtons()` | footer buttons | |
| `Modal::setCancelLabel()`, `getCancelLabel()`, `setShowCancelButton()`, `isShowCancelButton()` | cancel button | label escaped |
| `Modal::setSize()`, `getSize()`, `setScrollable()`, `isScrollable()`, `setCentered()`, `isCentered()`, `setStaticBackdrop()`, `isStaticBackdrop()` | dialog look | `SIZE_*` constants |
| `Modal::setIncludeForm()`, `isIncludeForm()`, `setFormAction()`, `getFormAction()`, `setFormMethod()`, `getFormMethod()`, `setFormId()`, `getFormId()`, `setFormHasFiles()`, `getFormHasFiles()`, `addHiddenInput($name, $value)`, `getHiddenInputs()` | the wrapping form | method `GET` or `POST` |
| `Modal::getModalOpenButton($label = 'Open Modal', $cssClasses = 'btn btn-outline-secondary btn-sm'): Link` | opener link | |
| `ContainerType::TYPE_DEFAULT`, `TYPE_FLUID` | `container`, `container-fluid` | |
| `TabContent` | deprecated tab description of the old `Form::addTab()`; never drawn | use `TabContainer` / `FormLayout::tab()` |
| `new TabContent($title, $content, $fields = [])`, `getTitle()`, `setTitle()`, `getTitleNoSpaces()`, `getContent()`, `setContent()`, `getFields()`, `setFields(array)`, `addField(string)` | plain holder of title, HTML and field names (deprecated) | kept for compatibility only |

### Components (`Laf\UI\Component`)

| Class / method | What it does | Notes |
|---|---|---|
| `Link::__construct($text = '', $href = '', $icon = '', $attributes = [], $class = [])` | `<a>` | caption raw |
| `Link::setValue()`, `setHref()`, `getHref()`, `setTarget()`, `getTarget()`, `setRel()`, `getRel()`, `setId()`, `getId()`, `setOnClick()`, `getOnClick()`, `setTitle()`, `getTitle()`, `setIcon()`, `getIcon()` | attributes | escaped |
| `Link::setConfirmationMessage($message = '')` | `onclick="return confirm('…')"` | JS-escaped |
| `Link::setDisabled(bool)`, `isDisabled()` | Bootstrap disabled link | |
| `Link::getName()` (`''`), `getField()` (`null`), `setField()` (throws), `getHint()` (`''`), `setHint()` (ignored), `__toString()` | form-element plumbing | |
| `Dropdown::__construct($text = '', $class = '', $icon = '', $rightAlign = false)` | dropdown | text escaped |
| `Dropdown::addLink(Link)`, `addDivider()`, `addHeader($text)`, `addHtml($html)`, `getItems()`, `getLinks()`, `setLinks(array)`, `hasLinks()` | menu entries | header escaped, html raw |
| `Dropdown::setText()`, `getText()`, `setIcon()`, `getIcon()`, `addMenuCssClass()`, `rightAlign()`, `leftAlign()`, `isRightAligned()`, `hasHrefParameter()` (false) | | |
| `Image::__construct($src = '', $alt = '')`, `setSrc()`, `getSrc()`, `setAlt()`, `getAlt()`, `setId()`, `getId()`, `setTitle()`, `getTitle()`, `setOnClick()`, `getOnClick()`, `setLazy()`, `isLazy()`, `setWidth()`, `setHeight()` | `<img>` | width/height in px |
| `Alert::__construct($title = '', $message = '', $type = Alert::Type_Primary)`, `draw($wrap_in_container = false)` | alert | raw HTML; empty message = `''` |
| `Alert::setMessage()`, `getMessage()`, `setTitle()`, `getTitle()`, `setType()`, `getType()`, `setDismissible()`, `isDismissible()`, `setIcon()`, `getIcon()` | | |
| `Carousel::__construct($documents = [], $id = null, $images = [], $indicators = true, $controls = true)` | carousel | |
| `Carousel::setImages()`, `getImages()`, `addImage()`, `getImagesCount()`, `setDocuments()`, `getDocuments()`, `addDocument(?int)`, `getDocumentCount()`, `getSlides()` | slides | images first |
| `Carousel::setId()`, `getId()`, `setShowIndicators()`, `getShowIndicators()`, `setShowControls()`, `getShowControls()`, `useBgImages()`, `setUseBgImages()`, `isUseBgImages()`, `setTopContent()`, `getTopContent()` | | top content raw |
| `Carousel::setDocumentUrlBuilder(?callable)` (static), `::documentUrl(int)` | document id to URL, process-wide | default `/document/view/{id}/1.jpg` |

`Link`, `Dropdown` and `Image` also carry `FormElementTrait` (label, hint, size, value ...) because they implement
`FormElementInterface`; only the methods above matter for them.

## See also

- [06-forms.md](06-forms.md): forms, `FormLayout`, the form's draw modes and CSRF token
- [07-form-inputs.md](07-form-inputs.md): input elements, wrapper attributes, `Button`
- [09-lists-and-tables.md](09-lists-and-tables.md): `Laf\UI\Table\Table`
- [10-grids.md](10-grids.md): `PhpGrid` (drawn into an `HtmlContainer`)
- [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md): the front controller, `Factory::GeneralPage()`, the generated pages
- [15-files-and-documents.md](15-files-and-documents.md): URLs of stored images for `Image` and `Carousel`
- [16-settings-locale-utilities.md](16-settings-locale-utilities.md): `Util::e()`, `Util::attr()`, `Locale`
- [17-security.md](17-security.md): escaping and CSRF rules
