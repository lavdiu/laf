# Routing and generated pages

LAF has no router class and no dispatcher: a request names a **module**, a **submodule**, an **action** and an
**id**, `Laf\Util\UrlParser` reads those four parts from the URL, and your front controller (`index.php`) includes
the PHP file that handles the module. The generator writes one such file per table, a `.page` file with a complete
CRUD screen (list grid, view with related records, insert and update forms, CSRF-protected delete). This chapter
covers `UrlParser` (URL shapes, getters, link builders, redirects), a minimal front controller and the
`Factory::GeneralPage()` shell the generated pages expect, a section-by-section walk through `employee.page` and
`project.page`, the generator options that shape the pages (`module_for_table`, `page_factory`, label
translations), and how to customise pages without losing them on the next generation run.

**On this page**

- [How a request reaches a page](#how-a-request-reaches-a-page)
- [`UrlParser`](#urlparser)
- [A minimal front controller](#a-minimal-front-controller)
- [The page factory: `Factory::GeneralPage()`](#the-page-factory-factorygeneralpage)
- [What a generated page expects](#what-a-generated-page-expects)
- [Anatomy of `employee.page`](#anatomy-of-employeepage)
- [`project.page`, `company.page` and the junction page](#projectpage-companypage-and-the-junction-page)
- [A request walk-through](#a-request-walk-through)
- [Generator options for pages](#generator-options-for-pages)
- [Customising pages](#customising-pages)
- [Reference](#reference)
- [See also](#see-also)

## How a request reaches a page

```
browser ──► index.php?module=employee&action=view&id=20
              │  bootstrap: autoloader, Settings, session_start(), active user
              │  UrlParser::getModule() = 'employee'  ──►  app/lib/pages/employee.page
              ▼
            employee.page
              │  UrlParser::getAction() = 'view', UrlParser::getId() = 20
              │  $employee = new Employee(20); $form = $employee->getForm(DrawMode::VIEW)
              │  $html = Factory::GeneralPage(); $page = new AdminPage(); ...
              ▼
            echo $html->draw();   (or a redirect, or a JSON answer for a grid / autocomplete request)
```

The page file reads the action and id itself, so the front controller only has to pick the file. Grids and
autocomplete inputs call the **same URL** again with extra query parameters (`load_grid_by_name=...`,
`laf_lookup=...`); the page answers those with JSON before it draws anything, which is why the front controller must
not print anything before including the page.

## `UrlParser`

`Laf\Util\UrlParser` is a per-request singleton with static getters. It reads the route in one of two shapes,
chosen by the setting `settings.use_pretty_url` (default `true`, read with `Settings::getBool()` so `"false"` /
`"off"` / `"0"` from an ini or env file mean false):

| Setting | URL | Where the parts come from |
|---|---|---|
| `use_pretty_url = false` | `index.php?module=hr&submodule=employee&action=view&id=42` | `$_GET['module']` (or the legacy `$_GET['mod']`), `submodule`, `action`, `id` |
| `use_pretty_url = true` | `/hr/employee/view/42` | `$_GET['uriRewrite']` = `hr/employee/view/42`, split on `/` (a rewrite rule must put it there) |

### Reading the route

```php
use Laf\Util\Settings;
use Laf\Util\UrlParser;

Settings::set('settings.use_pretty_url', false);
$_GET = ['module' => 'hr', 'submodule' => 'employee', 'action' => 'view', 'id' => '42'];
UrlParser::reset();                       // forget the parsed request (only needed in scripts/tests)

UrlParser::getModule();      // 'hr'
UrlParser::getSubmodule();   // 'employee'
UrlParser::getAction();      // 'view'
UrlParser::getId();          // 42 (int)
UrlParser::getRawId();       // '42'
UrlParser::getFullUri();     // '?module=hr&submodule=employee&action=view&id=42'
```

The id is normalised: a numeric segment becomes an `int`, a UUID becomes the lower-cased UUID string, anything else
is `null`. `getRawId()` gives the segment exactly as written.

```php
Settings::set('settings.use_pretty_url', true);
$_GET = ['uriRewrite' => 'hr/employee/view/3F2504E0-4F89-11D3-9A0C-0305E82C3301'];
UrlParser::reset();
UrlParser::getId();          // '3f2504e0-4f89-11d3-9a0c-0305e82c3301'
UrlParser::getRawId();       // '3F2504E0-4F89-11D3-9A0C-0305E82C3301'
UrlParser::getFullUri();     // '/hr/employee/view/3f2504e0-4f89-11d3-9a0c-0305e82c3301/'

$_GET = ['uriRewrite' => 'dashboard'];  UrlParser::reset();
// module 'dashboard', submodule/action/id null

Settings::set('settings.use_pretty_url', false);
$_GET = ['module' => 'employee', 'action' => 'view', 'id' => 'x1'];  UrlParser::reset();
UrlParser::getId();          // null   (not a number, not a UUID)
UrlParser::getRawId();       // 'x1'
UrlParser::getSubmodule();   // ''     (absent query parameter = empty string)
```

Defaults when the URL names nothing: in pretty mode the module is `'home'`; in query-string mode it is `''` and the
action `''`. A front controller should treat both as "the start page". Array values (`?id[]=1`) count as absent.

### Building links

Every link builder keeps the current module and submodule and changes the action and id. Module and submodule are
URL-encoded; the id is used when it is a number, a UUID or `'all'`, otherwise the route's own id is used.

| Call (route: module `hr`, submodule `employee`, id 42) | query-string mode | pretty mode |
|---|---|---|
| `getListLink()` | `?module=hr&submodule=employee&action=list&id=all` | `/hr/employee/list/all` |
| `getNewLink()` (= `getInsertLink()`) | `?module=hr&submodule=employee&action=new` | `/hr/employee/new` |
| `getViewLink()` | `?module=hr&submodule=employee&action=view&id=42` | `/hr/employee/view/42` |
| `getUpdateLink(7)` | `?module=hr&submodule=employee&action=update&id=7` | `/hr/employee/update/7` |
| `getDeleteLink($uuid)` | `?module=hr&submodule=employee&action=delete&id=3F2504E0-…` | `/hr/employee/delete/3F2504E0-…` |
| `getViewLink('abc')` (not an id: the route's id is used) | `…&action=view&id=42` | `/hr/employee/view/42` |
| `getLink('export', null, false)` (no id part) | `?module=hr&submodule=employee&action=export` | `/hr/employee/export` |

```php
$_GET = ['module' => 'my reports', 'submodule' => 'a/b', 'action' => 'list'];
UrlParser::reset();
echo UrlParser::getListLink();   // ?module=my%20reports&submodule=a%2Fb&action=list&id=all
```

> **Note:** pretty links are root-absolute (`/hr/employee/list/all`). They only work when the application is served
> from the root of its host; under a sub-path such as `http://localhost/myapp/` use query-string mode.

### Redirects

```php
UrlParser::redirectToViewPage($id);   // 302 to getViewLink($id), then exit
UrlParser::redirectToListPage();      // 302 to getListLink(), then exit

UrlParser::redirectToViewPage(5, false);   // sends the Location header and returns (tests, embedded runtimes)
UrlParser::redirectToListPage(false);
```

The `Location` header is only sent when no output has gone out yet; with `$terminate = true` (the default) the
script ends either way.

### Pretty URLs

UrlParser does not rewrite anything itself; the web server must pass the path in `uriRewrite`. With Apache, a
typical rule in the folder of `index.php`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?uriRewrite=$1 [QSA,L]
```

`QSA` keeps the query string, which the CSRF-protected delete links and the grid / autocomplete requests need.

> **Note:** the **generated pages need query-string mode** (`settings.use_pretty_url = false`). Their grids build
> row links as `?module=employee&action=view&id=…` (see [the list action](#list-the-default-action)); in pretty mode
> UrlParser reads only `uriRewrite` and ignores `module` / `action` / `id` in the query string, so those links would
> show the list again. `sample_files/settings.php` ships with `use_pretty_url => true`; set it to `false` for an
> application that uses generated pages. Hand-written modules can use either shape as long as the whole application
> uses one.

## A minimal front controller

The layout below matches [01-getting-started.md](01-getting-started.md): `app/bootstrap.php` (copied from
`sample_files/bootstrap.php`) configures everything, the generator writes into `app/lib`
(`vendor/bin/laf-generate --bootstrap=app/bootstrap.php --lib=app/lib --install-pages`), and `public/` is the web
root.

```
app/
  bootstrap.php            autoloader, Settings, session_start(), BaseObject::setActiveUserId()
  settings.php             ... 'settings' => ['use_pretty_url' => false] ...
  lib/
    MyApp/                 models (Base/ regenerated, leaf classes yours) + Factory.php (yours)
    pages/                 the live .page files (installed once, then yours)
    generator_cache/pages/ fresh generator output on every run (compare against pages/)
public/
  index.php                the front controller below
  assets/grid.js           copied from vendor/lavdiu/laf/sample_files/grid.js
```

```php
<?php
// public/index.php
declare(strict_types=1);

use Laf\Util\UrlParser;

// 1. autoloader, Settings, session_start(), BaseObject::setActiveUserId() (see sample_files/bootstrap.php)
require __DIR__ . '/../app/bootstrap.php';

// 2. which page: the module names the .page file
$module = UrlParser::getModule();               // '' (query-string mode) or 'home' when the URL names none
if ($module === '' || $module === 'home') {
    $module = 'employee';                       // your start page
}
$pageFile = __DIR__ . '/../app/lib/pages/' . $module . '.page';
if (!preg_match('/^[a-z0-9_]+$/', $module) || !is_file($pageFile)) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

// 3. access control goes here (logged in? allowed to open this module?)

// 4. the page does the rest: it reads the action and the id from UrlParser itself
require $pageFile;
```

That is the whole dispatcher. What each step guards against:

- **The whitelist pattern** (`[a-z0-9_]+`) and `is_file()` keep `?module=../../etc/passwd` from being included.
  Use a lookup table instead (module => file, plus the permission it needs) once the application grows; that is what
  ASM, the production application built on LAF, does with a routing table in its database.
- **Nothing is printed before `require`**: the page may answer with a redirect, a JSON grid page or an autocomplete
  result, all of which need clean output.
- **The session is started in the bootstrap**, before the page: the page reads `Flash` messages first thing and the
  CSRF tokens of its forms and delete links live in the session.
- **`.page` files stay outside the web root.** They are PHP source with an extension the web server does not execute;
  served directly they would be shown as text.

Requests then look like `index.php?module=employee&action=list`, `?module=employee&action=view&id=20`,
`?module=project&action=new`. The submodule is optional for the generated pages: they route on the module, and
UrlParser's own links carry an empty `submodule=` along.

## The page factory: `Factory::GeneralPage()`

Every generated page starts its output with

```php
use MyApp\Factory;
...
$html = Factory::GeneralPage();
```

The generator does **not** create this class: it is the one piece of the shell you write yourself, in
`app/lib/MyApp/Factory.php` (namespace = `project.package_name`). It must return a `Laf\UI\Page\Html` (or an object
with the same `addComponent()` and `draw()`), with everything every page needs: CSS, the grid script, Bootstrap's
JavaScript, your menu.

```php
<?php
// app/lib/MyApp/Factory.php
namespace MyApp;

use Laf\UI\Page\Html;

class Factory
{
    public static function GeneralPage(): Html
    {
        $html = new Html();
        $html->setPageTitle('MyApp')
            ->addStylesheet('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')
            ->addStylesheet('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css')
            ->addScript('assets/grid.js')                                   // the PhpGrid client (head or body end)
            ->addScript('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], true)
            ->setMenu(self::menu());
        return $html;
    }

    private static function menu(): string
    {
        return "<nav class='navbar navbar-expand bg-dark navbar-dark px-3'>"
            . "<a class='navbar-brand' href='?module=home'>MyApp</a>"
            . "<div class='navbar-nav'>"
            . "<a class='nav-link' href='?module=company&action=list'>Companies</a>"
            . "<a class='nav-link' href='?module=employee&action=list'>Employees</a>"
            . "<a class='nav-link' href='?module=project&action=list'>Projects</a>"
            . "</div></nav>";
    }
}
```

Pass `['integrity' => 'sha384-…', 'crossorigin' => 'anonymous']` as the attribute array when you load from a CDN
and want Subresource Integrity, or serve the files yourself. A real application usually has more than one shell
(`GeneralPage()` with the menu, `noMenuPage()` for login and error screens); the generator only needs the one it is
told about, see [`page_factory`](#page_factory-and-page_factory_method).

## What a generated page expects

When the front controller includes a `.page` file, the page relies on:

| Requirement | Why |
|---|---|
| Composer autoloader + an autoloader for `MyApp\*` | the page `use`s `MyApp\Employee`, `MyApp\Factory` and LAF classes |
| Settings: `database.*`, `project.package_name` | models connect and find their classes |
| `settings.use_pretty_url = false` | the grid links are query-string links |
| `MyApp\Factory::GeneralPage()` returning an `Html` | the page shell |
| an active session (`session_start()`) | `Flash` messages, form and delete-link CSRF tokens |
| `BaseObject::setActiveUserId()` (or `MyApp\Person::getLoggedUserId()`) | `created_by` / `updated_by` and the audit log |
| `grid.js` and Bootstrap 5 JS/CSS loaded by the shell | the list and related grids, tabs, dropdowns |
| no output before the page runs | redirects, JSON answers for grids and autocomplete |

It needs **no variables** from the front controller. It does define its own globals when included at file scope:
`$id`, `$action`, `$form`, `$html`, `$page`, `$page2`, `$grid`, `$dd`, `$updateLink`, `$deleteLink`,
`$tabContainer`, `$tabItem`, one variable named after the model (`$employee`) and one per related grid (`$project`).
Values of the same name set by the front controller are overwritten.

## Anatomy of `employee.page`

The generator's real output for the `employee` table is
[examples/generated/pages/employee.page](examples/generated/pages/employee.page). Section by section:

### 1. Imports

```php
<?php

use MyApp\Employee;
use Laf\Exception\FormValidationException;
use Laf\UI\Component\Alert;
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;
use Laf\UI\Container\ContainerType;
// ... Div, HtmlContainer, TabContainer, TabItem, Flash, DrawMode, Form, FormLayout,
//     ActionButton, Column, PhpGrid, AdminPage, UrlParser
use MyApp\Factory;
```

Every page imports the same set (some are only used by the commented examples). A page whose related-tab filter is
on a non-integer key also imports `Laf\Database\Db`, for `Db::getInstance()->quote()`.

### 2. The record and its form

```php
$id = UrlParser::getId();
$action = UrlParser::getAction();
$employee = new Employee($id);
$form = $employee->getForm($action === 'new' ? DrawMode::INSERT : ($action === 'update' ? DrawMode::UPDATE : DrawMode::VIEW));
```

`new Employee(null)` is an empty record (for `new` and `list`), `new Employee(20)` loads row 20 (an empty object when
the row does not exist, hence the `recordExists()` checks below). The form is laid out by the model:
`getForm($mode)` applies `Employee::getFormLayout()`, by default `FormLayout::auto()` built from the table and the
JSON column comments (the `notes` column's `{"form":{"tab":"Notes"}}` puts it in a tab). See
[06-forms.md](06-forms.md).

### 3. The hand-layout hint

```php
/**
 * The form is laid out by Employee::getFormLayout() (FormLayout::auto() by default).
 * To change it here instead, apply a layout:
 *
 *	$form->applyLayout($form->getDrawMode(), FormLayout::auto($employee->getTable())
 *		->readOnly(['code'], [DrawMode::UPDATE])
 *		->fieldset('Address', ['street', 'city'], 2));
 *
 * or build the components yourself (Div(['row']) / Div(['col-lg']) for columns):
 *
 *	$form->setComponents([])
 *		->addComponent($employee->getIdFormElement())
 *		->addComponent($employee->getCompanyIdFormElement())
 *		...
 */
```

A comment only: a copy-paste starting point listing every column's form element. Prefer changing the layout in the
model (`getFormLayout()` / `configureForm()` in `Employee.php`): it then applies to every form of the model, not only
to this page.

### 4. Autocomplete lookups

```php
// Searches of autocomplete fields are answered here (JSON) and end the request
$form->handleLookupRequest();
```

When the request carries `laf_lookup=employee.company_id&laf_q=…` (an `Autocomplete` input searching), this prints
the JSON answer and exits; otherwise it does nothing. It has to come after `getForm()` (the form knows its
autocomplete fields) and before any output. See [08-relations.md](08-relations.md).

### 5. The shell and the card

```php
$html = Factory::GeneralPage();
$page = new AdminPage();

$page->setTitle("<a href='" . UrlParser::getListLink() . "' class='text-black text-decoration-none'>Employee</a>");
$page->setTitleIcon('far fa-list-alt');

// Messages queued by the previous request (Flash::success() / Flash::error(), drained once)
foreach (Flash::drain() as $flashAlert) {
	$page->addComponent($flashAlert);
}
```

The document comes from your factory, the card is an `AdminPage` whose title links back to the list
([11-pages-and-ui.md](11-pages-and-ui.md)). Messages queued before the last redirect ("Record saved successfully")
are shown first.

### 6. Saving a submission

```php
if ($form->isSubmitted()) {
	try {
		$id = $form->processForm();
		Flash::success('Record saved successfully');
		UrlParser::redirectToViewPage($id);
		exit;
	} catch (FormValidationException $ex) {
		// the form is drawn again below with the submitted values and the messages
		$page->addComponent(new Alert('', 'The record was not saved, please correct the fields marked below', Alert::Type_Danger));
	}
}
```

`processForm()` checks the form's CSRF token, applies the posted values, validates, and inserts (draw mode
`INSERT`) or updates (`UPDATE`); it returns the record id. Success: a flash message and a redirect to the view page
(post/redirect/get, so a reload does not post again). Refusal (a missing required value, a duplicate unique value, a
validation rule): the same request continues into the `switch` below and draws the form again with the posted values
and the messages under the fields. A wrong CSRF token is a `CsrfTokenException`, which is not caught here.

### 7. The actions

```php
switch ($action) {
	case 'update':
		if (!$employee->recordExists()) {
			Flash::error('Record not found');
			UrlParser::redirectToListPage();
			exit;
		}
		$page->setContainerType(ContainerType::TYPE_FLUID);
		$page->addComponent($form);
		$page->addLink(new Link('Cancel', UrlParser::getViewLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
		$html->addComponent($page);
		echo $html->draw();
		break;
	case 'new':
		$page->setContainerType(ContainerType::TYPE_FLUID);
		$page->addComponent($form);
		$page->addLink(new Link('Cancel', UrlParser::getListLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
		$html->addComponent($page);
		echo $html->draw();
		break;
```

`update` and `new` draw the form in the card with a Cancel link (back to the record, back to the list). The form
draws its own submit button and posts to the current URL, so the submission arrives at step 6 of the same page.

```php
	case 'delete':
		// the delete links carry the form's CSRF token: a crafted link cannot delete
		if (!$form->isValidCsrfToken($_GET[$form->getCsrfSessionKey()] ?? null)) {
			Flash::error('The record was not deleted: the link is not valid, open the record and try again');
			UrlParser::redirectToListPage();
			exit;
		}
		if ($employee->recordExists()) {
			if ($employee->canSoftDelete()) {
				$employee->softDelete();
			} else {
				$employee->hardDelete();
			}
			Flash::success('Record deleted successfully');
		} else {
			Flash::error('Record not found');
		}
		UrlParser::redirectToListPage();
		exit;
```

`delete` never draws anything. The link that leads here carries the token of the page's form
(`$form->withCsrfToken($url)` appends `_csrf_token_employee=<64 hex characters>`), and the action refuses any request
without the session's token: an `<img src="…&action=delete&id=20">` planted on another site cannot delete. Tables
with a `record_status_id` (or `deleted`) column are soft-deleted (`record_status_id = 0`, the row stays, see
[03-models.md](03-models.md)); others are deleted for real.

```php
	case 'view':
		if (!$employee->recordExists()) { /* flash + redirect to the list */ }
		$page->setContainerType(ContainerType::TYPE_FLUID);
		$page->addComponent($form);
		$page->addLink(new Link('List', UrlParser::getListLink(), 'far fa-list-alt', [], ['btn', 'btn-sm', 'btn-outline-success']));

		$dd = new Dropdown('Options', '', 'fa fa-cogs', true);
		$dd->addCssClass('btn-outline-success')
			->addCssClass('btn-sm');
		$updateLink = new Link('Update', UrlParser::getUpdateLink(), 'fa fa-edit', ['class' => 'btn btn-sm btn-outline-warning']);
		$deleteLink = new Link('Delete', $form->withCsrfToken(UrlParser::getDeleteLink()), 'fa fa-trash', ['class' => 'btn btn-sm btn-outline-danger']);
		$deleteLink->setConfirmationMessage('Are you sure you want to delete this?');

		$dd->addLink($updateLink)
			->addLink($deleteLink);
		$page->addLink($dd);

		$html->addComponent($page);
```

`view` shows the record (the form in `VIEW` mode draws values, not inputs) with a List link and an Options dropdown
(Update, Delete with confirmation and token). Rendered, the delete entry is:

```html
<a href="?module=employee&amp;submodule=&amp;action=delete&amp;id=20&amp;_csrf_token_employee=d4cd0ed38b1b…1cce3"
   onclick="return confirm('Are you sure you want to delete this?')" style=""
   class="dropdown-item Laf-UI-Component-Link"><i class='fa fa-trash'></i> Delete</a>
```

(The `['class' => 'btn …']` the page passes in the attribute array is not drawn: `Link` takes its classes from the
fifth constructor argument or `addCssClass()`, and inside a dropdown the entries are `dropdown-item`s anyway.)

### 8. Related records (view only)

Below the record, the view action adds a second card, "Related information", with one tab per foreign key that
points **at** this table. `employee` is referenced only by the linker table `employee_project`, so its tab shows
the projects **behind** the linker (a junction tab):

```php
		$tabContainer = new TabContainer('employee_related_tabs');

		$project = new PhpGrid('project_list');
		$project->setTitle('Project List')
			->setRowsPerPage(10)
			->setSqlQuery('
SELECT l1.* FROM (
SELECT * FROM (
	SELECT
		  `project`.`id` AS `project_id`
		, `project`.`name` AS `project_name`
		, `project`.`starts_on` AS `project_starts_on`
		, `project`.`budget` AS `project_budget`
	FROM `project`
	WHERE 1=1 
) l1 
) l1 INNER JOIN `employee_project` jt ON l1.`project_id` = jt.`project_id` WHERE jt.`employee_id` = \' . ((int)UrlParser::getId()) . \'');

		$project->addColumn(((new Column('project_id', 'Id', true, true, '?module=project&action=view&id={project_id}'))->setInnerElementCssClass('btn btn-sm btn-outline-success'))->setOuterElementCssStyle('width:100px;'));
		$project->addColumn(new Column('project_name', 'Name', true));
		$project->addColumn(new Column('project_starts_on', 'Starts On', true));
		$project->addColumn(new Column('project_budget', 'Budget', true));

		$project->addActionButton(new ActionButton('View', '?module=project&action=view&id={project_id}', 'fa fa-eye'));

		if ($project->isReadyToHandleRequests()) {
			$project->bootstrap();
		}
		$project->setDeferInitialize(true);

		$tabItem = (new TabItem('Project'))->setIdPrefix('employee_related_tabs');
		$tabItem->addComponent(new HtmlContainer($project->draw()));
		$tabContainer->addComponent($tabItem);

		$page2 = new AdminPage();
		$page2->setTitle('Related information')
			->addComponent(new HtmlContainer($tabContainer->draw()));
		$html->addComponent($page2);

		echo $html->draw();
		break;
```

What each part does:

- The grid's rows are `project` rows, so its links go to **the project page** (`?module=project&action=view`), and
  it offers View only (deleting belongs to the project page with its own token). The module name comes from
  [`module_for_table`](#module_for_table), default = the table name.
- `isReadyToHandleRequests()` + `bootstrap()`: when grid.js asks for this grid's rows
  (`…&load_grid_by_name=project_list&page=1&limit=10`) the page answers with JSON and exits here.
- `setDeferInitialize(true)`: the grid loads its rows only when its tab becomes visible.
- `setIdPrefix()` keeps the tab ids apart from the form's own tabs ([11-pages-and-ui.md](11-pages-and-ui.md#tabcontainer-and-tabitem)).

> **Note (generator defect, current behaviour):** the filter at the end of the related-tab SQL is meant to splice
> the page's id into the query, but the quotes around it are escaped, so the statement literally ends in
> `WHERE jt.`employee_id` = ' . ((int)UrlParser::getId()) . '`, a comparison with a string. On MariaDB this matches
> no row: the related tabs of the generated pages are always empty (verified: employee 20 linked to project 9, the
> tab's JSON answer has `"rowCount": 0`). The same applies to foreign-key tabs (`company.page`'s employee tab).
> Until the generator is fixed, edit the live page and bind the id as a parameter, which works:
>
> ```php
> ->setSqlQuery('
> ... ) l1 INNER JOIN `employee_project` jt ON l1.`project_id` = jt.`project_id` WHERE jt.`employee_id` = :employee_id')
> ->setParamsList(['employee_id' => (int)UrlParser::getId()]);
> ```

### List: the default action

```php
	case 'list':
	default:

		$grid = new PhpGrid('employee_list');
		$grid->setTitle('Employee List')
			->setRowsPerPage(10)
			->setSqlQuery('
SELECT * FROM (
	SELECT
		  `employee`.`id` AS `employee_id`
		, `employee`.`company_id` AS `employee_company_id`
		, `company`.`name` AS `company_name`
		, `employee`.`first_name` AS `employee_first_name`
		...
	FROM `employee`
	LEFT JOIN `company` `company` ON `employee`.`company_id` = `company`.`id`
	WHERE 1=1 
) l1 ');

		$grid->addColumn(((new Column('employee_id', 'Id', true, true, sprintf('?module=%s&action=view&id={employee_id}', UrlParser::getModule())))->setInnerElementCssClass('btn btn-sm btn-outline-success'))->setOuterElementCssStyle('width:100px;'));
		$grid->addColumn(new Column('employee_company_id', 'Company Id', false));
		$grid->addColumn(new Column('company_name', 'Company', true));
		$grid->addColumn(new Column('employee_first_name', 'First Name', true));
		// ... one column per table column

		$grid->addActionButton(new ActionButton('View', sprintf('?module=%s&action=view&id={employee_id}', UrlParser::getModule()), 'fa fa-eye'));
		$grid->addActionButton(new ActionButton('Update', sprintf('?module=%s&action=update&id={employee_id}', UrlParser::getModule()), 'fa fa-edit'));
		$deleteLink = new ActionButton('Delete', $form->withCsrfToken(sprintf('?module=%s&action=delete&id={employee_id}', UrlParser::getModule())), 'fa fa-trash');
		$deleteLink->addAttribute('onclick', 'return confirm("Are you sure you want to delete this?")');
		$grid->addActionButton($deleteLink);

		if ($grid->isReadyToHandleRequests()) {
			$grid->bootstrap();
		}

		$page->addComponent(new HtmlContainer($grid->draw()));
		$page->addLink(new Link('Add new', UrlParser::getNewLink(), 'fa fa-plus-square', [], ['btn', 'btn-sm', 'btn-outline-success']));
		$page->setContainerType(ContainerType::TYPE_FLUID);
		$html->addComponent($page);
		echo $html->draw();
		break;
}
```

Any action that is not one of the four (including none) shows the list. The grid's SQL joins every foreign key
for display: `company_id` stays as a hidden column (`Company Id`, `false` = not visible) and the company's display
column (`name`) is shown as "Company". Identifiers are quoted for the engine (backticks here, double quotes on
PostgreSQL). Links use the **current module** at runtime (`UrlParser::getModule()`), so the page keeps working if
you route it under another module name. The delete button carries the CSRF token too. Grid details:
[10-grids.md](10-grids.md).

## `project.page`, `company.page` and the junction page

[project.page](examples/generated/pages/project.page) has the same structure; the differences are the table:

- The form, list columns and title are `project`'s (`name`, `starts_on`, `budget`; no foreign keys, no joins).
- The related tab goes the other way through the same linker: on a project, the tab "Employee" lists the employees
  linked through `employee_project` (`INNER JOIN employee_project jt ON l1.employee_id = jt.employee_id WHERE
  jt.project_id = …`), with View buttons to `?module=employee`. The grid variable is `$employee`, the model variable
  `$project`.
- On the forms, the linker table also gives each side a many-to-many picker ("Projects" on employees, "Team members"
  on projects): that comes from the models (the `belongsToMany()` declarations the generator writes into the Base
  classes because `employee_project` is marked `{"linker": true}`), not from the page. The junction tabs above do
  not need the marker: the page generator finds linker tables by the rule alone. See
  [08-relations.md](08-relations.md#many-to-many-declaring-a-relation).

[company.page](examples/generated/pages/company.page) adds two things:

- `company` has a `record_status_id` column, so before processing a submission the page defaults it for new rows:

  ```php
  if ($form->isSubmitted()) {
  	if ($action == 'new') {
  		$form->setSubmittedFieldValue('record_status_id', 1);
  	}
  	try { ...
  ```

  and `delete` soft-deletes (`record_status_id = 0`). The list grid shows every row, soft-deleted ones included; add
  `AND record_status_id <> 0` to its SQL if your application hides them (by design LAF leaves that choice to the
  application, see [03-models.md](03-models.md)).
- `employee.company_id` points at `company`, so the related tab "Employee" is a plain foreign-key tab: the employee
  list SQL with `AND employee.company_id = <the page's id>`, View and Update buttons to `?module=employee`.

When several foreign keys of one table point at the same table, each gets its own tab, labelled with the column
(`Invoice (Billing Company)`, `Invoice (Shipping Company)`).

[employee_project.page](examples/generated/pages/employee_project.page) is the page of the linker table itself: a
plain CRUD page over `(id, employee_id, project_id)` with both foreign keys joined in the list. Most applications
never link to it (the pickers on the employee and project forms maintain the links) and can delete it, or exclude
the table from page generation.

## A request walk-through

Run against the shared database with the front controller above (one PHP session across the requests):

| Request | What happens |
|---|---|
| `GET ?module=project&action=list` | list card with the `project_list` grid (rows loaded by grid.js) and "Add new" |
| `GET ?module=project&action=list&load_grid_by_name=project_list&page=1&limit=10` | JSON page of rows, then exit |
| `GET ?module=project&action=new` | empty form |
| `POST ?module=project&action=new` (name "ch12 Gemini", budget 1500.00, token) | row 10 inserted, `Flash::success()`, 302 to `?module=project&submodule=&action=view&id=10` |
| `GET ?module=project&action=view&id=10` | "Record saved successfully" alert, the record, Options dropdown |
| `POST` the same name again | not saved; the form comes back with "Name: this value is already in use" under the field |
| `GET ?module=employee&action=delete&id=20&_csrf_token_employee=forged` | nothing deleted, 302 to the list, which shows "The record was not deleted: the link is not valid, open the record and try again" |
| `GET` the Delete link of project 10's view page | row deleted, 302 to the list, "Record deleted successfully" |
| `GET ?module=project&action=view&id=10` again | 302 to the list, "Record not found" |
| `GET ?module=nosuch` | 404 "Page not found" from the front controller |

The refused submission renders like this:

```html
<div class='alert alert-danger' role='alert'>
  <p>The record was not saved, please correct the fields marked below</p>
</div>
...
<div id='project_errors' class='alert alert-danger laf-form-errors' role='alert'>
	<ul class='mb-0'>
		<li>Name: this value is already in use</li>
	</ul>
...
<div id='anzr_feedback' class='invalid-feedback d-block'>Name: this value is already in use</div>
```

## Generator options for pages

`PageGenerator` reads its options from the `DatabaseGenerator` config array. The CLI (`laf-generate`) sets the
directories and labels; the other keys are set from PHP (as in `sample_files/class_generator.php`):

```php
use Laf\Generator\DatabaseGenerator;

$generator = new DatabaseGenerator(__DIR__ . '/../lib');
$generator->setConfig($generator->getConfig() + [
    'module_for_table' => ['project' => 'projects'],     // or fn (string $table): string
    'page_factory' => 'MyApp\\Ui\\Shell',                 // default "{namespace}\Factory"
    'page_factory_method' => 'admin',                     // default "GeneralPage"
]);
$generator->setLabelTranslations(['add-new' => 'Shto', 'record-saved' => 'U ruajt']);
$generator->generateEverything();
```

Generated with these options, `employee.page` contains:

```php
use MyApp\Ui\Shell;
$html = Shell::admin();
Flash::success('U ruajt');
$project->addColumn(((new Column('project_id', 'Id', true, true, '?module=projects&action=view&id={project_id}')) ...
$project->addActionButton(new ActionButton('View', '?module=projects&action=view&id={project_id}', 'fa fa-eye'));
$page->addLink(new Link('Shto', UrlParser::getNewLink(), 'fa fa-plus-square', [], ['btn', 'btn-sm', 'btn-outline-success']));
```

### `module_for_table`

Which module shows a table's records. Used for the links of **related** grids (rows of another table): the
generator cannot know how you route, so it assumes the module is the table name (which is what `--install-pages`
names the page file after). Give a map (`table => module`) or a callable taking the table name. The page's own list
grid does not use it; it links to the current module at runtime.

### `page_factory` and `page_factory_method`

The class (fully qualified) and static method that return the shell. The page imports the class and calls
`{ShortName}::{method}()`. Defaults: `{namespace}\Factory` and `GeneralPage`.

### Label translations

Every text the page shows is a label key with an English default (`PageGenerator::LABEL_DEFAULTS`). Translations
come from, in order: `laf-generate --labels=FILE` (a `.php` file returning an array, or `.json`), else Settings
`settings.label.translations`; from PHP, `DatabaseGenerator::setLabelTranslations()` or the argument of
`generateEverything()`. Keys you leave out keep the default. The texts are written into the page as PHP string
literals (quotes escaped), so any text is safe.

| Key | Default |
|---|---|
| `view` | View |
| `cancel` | Cancel |
| `options` | Options |
| `add-new` | Add new |
| `update` | Update |
| `delete` | Delete |
| `list` | List (also the suffix of grid titles: "Employee List") |
| `delete-confirmation` | Are you sure you want to delete this? |
| `record-saved` | Record saved successfully |
| `record-deleted` | Record deleted successfully |
| `record-not-found` | Record not found |
| `record-not-saved` | The record was not saved, please correct the fields marked below |
| `record-not-deleted` | The record was not deleted: the link is not valid, open the record and try again |
| `related-information` | Related information |

Field labels and the card title are not page labels: the title is the class name, field labels come from the
column comments (`setLabel`), see [02-code-generation.md](02-code-generation.md).

## Customising pages

The generator writes two copies:

- `lib/generator_cache/pages/<table>.page` is **rewritten on every run**: the page as the current schema would
  generate it.
- `lib/pages/<table>.page` is the **live** page, copied from the cache only when it does not exist yet
  (`--install-pages` / `setInstallMissingPages(true)`). From then on it is yours; the generator never overwrites it.

So edit the live pages freely, and after a schema change compare them with the fresh output
(`diff lib/generator_cache/pages/employee.page lib/pages/employee.page`) and carry over what you want (a new column
in the list grid, a new related tab).

Before editing a page, check whether the change belongs somewhere that survives regeneration:

| Change | Where |
|---|---|
| form fields, order, sections, tabs, read-only / hidden fields | the model: `getFormLayout()` / `configureForm()` in `MyApp/Employee.php`, or column comments ([06-forms.md](06-forms.md)) |
| field labels, hints, required, input element | column comments `{"fields":…,"form":…}` ([02-code-generation.md](02-code-generation.md)) |
| validation | the model's `validateRecord()` and field rules ([05-validation.md](05-validation.md)) |
| defaults and side effects on save | model hooks `beforeInsert()` / `afterUpdate()` ... ([03-models.md](03-models.md)) |
| page shell, menu, scripts | `Factory::GeneralPage()` |
| button texts, messages | label translations |
| list columns, list filter, extra buttons, extra tabs, permissions per action | the live `.page` file |

Typical page edits:

```php
// hide soft-deleted companies in the list (company.page, list action)
... WHERE 1=1 AND `company`.`record_status_id` <> 0
) l1 ');

// fewer list columns: delete the addColumn() lines you do not want, or hide them
$grid->addColumn(new Column('employee_notes', 'Notes', false));

// an extra action in the view's Options dropdown
$dd->addLink(new Link('Profile', '?module=profile&id=' . $employee->getIdVal(), 'fa fa-user'));

// refuse an action for some users (before the switch)
if ($action === 'delete' && !MyApp\Person::isAdmin()) {      // your own check
    Flash::error('You are not allowed to delete employees');
    UrlParser::redirectToViewPage();
}
```

A page does not have to be generated at all: any PHP file the front controller includes works the same way (see the
profile page at the end of [11-pages-and-ui.md](11-pages-and-ui.md#a-full-page-assembled)).

## Reference

### `Laf\Util\UrlParser`

| Method | Returns | Notes |
|---|---|---|
| `getInstance()` | the singleton | created on first use from Settings and `$_GET` |
| `reset()` | void | forget the instance; the next call re-reads Settings and `$_GET` |
| `getModule()` | `string` | `'home'` (pretty) / `''` (query string) when absent; `mod` accepted |
| `getSubmodule()`, `getAction()` | `?string` | `''` when absent in query-string mode, `null` in pretty mode |
| `getId()` | `int\|string\|null` | int, lower-cased UUID, or null |
| `getRawId()` | `?string` | as written |
| `isUuid(string)` | `bool` | static |
| `getFullUri()` | `string` | the current route as a link (pretty form ends with `/`) |
| `getLink($action, $id = null, $withId = true)` | `string` | the generic builder |
| `getListLink()`, `getNewLink()`, `getInsertLink()` | `string` | list uses id `all`; new has no id part |
| `getViewLink($id = null)`, `getUpdateLink($id = null)`, `getDeleteLink($id = null)` | `string` | invalid / null id = the route's id |
| `redirectToViewPage($id = null, $terminate = true)`, `redirectToListPage($terminate = true)` | void | 302 + `exit` unless `$terminate` is false |
| `isUsePrettyUrl()`, `setUsePrettyUrl(bool)` | | instance methods; `setUsePrettyUrl()` changes link building only, not the parsed route |
| `_getModule()`, `_getSubmodule()`, `_getAction()`, `_getId()` | | instance variants of the getters |

### `Laf\Generator\PageGenerator`

| Method | What it does |
|---|---|
| `__construct(Table $table, array $config, array $labelTranslations = [], ?TableInspectorInterface $inspector = null)` | config keys: `namespace`, `page_dir`, `live_page_dir`, `page_factory`, `page_factory_method`, `module_for_table` |
| `generatePageFile()`, `getPageFile()`, `setPageFile()` | build the source in memory / read it |
| `savePageToFile()` | build and write to `getPageFilePath()`; `RuntimeException` on a directory or write failure |
| `getPageFilePath()`, `pageFileExists()` | `page_dir/<table>.page`, or `live_page_dir/…` with `setWriteOnLiveDirectory(true)` |
| `isWriteOnLiveDirectory()`, `setWriteOnLiveDirectory(bool)` | which directory |
| `buildGrid($table, $gridName = 'grid', $filters = [], $gridId = null, $targetModule = null)` | PHP source of a list grid (used for the list and the FK tabs) |
| `buildManyToManyGrid($otherTable, $gridName, $junctionTable, $fkToCurrent, $fkToOther, $currentTable, $currentPk, $gridId = null)` | PHP source of a junction tab grid |
| `moduleForTable(string $table)` | the module for a table's links (`module_for_table`, else the table name) |
| `getLabelTranslations()`, `setLabelTranslations(array)`, `LABEL_DEFAULTS` | page labels |
| `getWarnings()` | e.g. "table x has no primary key: the generated page cannot view, update or delete rows" |
| `getTable()`, `setTable()`, `getConfig()`, `setConfig()`, `getTableInspector()`, `addTableInspector()`, `setEngine(?string)`, `getEngine()` | inputs; `setEngine()` picks the quoting (mysql / postgres) |

## See also

- [01-getting-started.md](01-getting-started.md): project layout, bootstrap and the first generated page
- [02-code-generation.md](02-code-generation.md): `laf-generate`, `--install-pages`, generator_cache, column comments
- [06-forms.md](06-forms.md): `getForm()`, `processForm()`, CSRF tokens, `FormLayout`
- [08-relations.md](08-relations.md): autocomplete lookups and many-to-many pickers on the generated forms
- [09-lists-and-tables.md](09-lists-and-tables.md): lists without a grid, links with `UrlParser`
- [10-grids.md](10-grids.md): `PhpGrid`, the JSON contract and grid.js
- [11-pages-and-ui.md](11-pages-and-ui.md): `Html`, `AdminPage`, containers, components, `Flash`
- [17-security.md](17-security.md): CSRF on delete links and forms, escaping, sessions
