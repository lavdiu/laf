# Security

This chapter collects the security rules that are spread over LAF: how output is escaped and which APIs take raw
HTML, how forms, delete links, autocomplete "create new" and modals are protected against cross-site request
forgery, how the signed field list stops mass assignment, why SQL built by LAF cannot be injected (and where your own
SQL still can be), how uploaded files are served, and what LAF expects from your session setup. Read it once
before you put a LAF application on the internet; the other chapters link here for the details.

On this page:

- [Output escaping](#output-escaping)
- [APIs that take raw HTML](#apis-that-take-raw-html)
- [Field::setAllowHtml()](#fieldsetallowhtml)
- [CSRF protection](#csrf-protection)
- [Mass-assignment guard](#mass-assignment-guard)
- [SQL injection](#sql-injection)
- [Uploaded files](#uploaded-files)
- [Sessions](#sessions)
- [Access control](#access-control)
- [Checklist](#checklist)

## Output escaping

LAF's rule since 2026: **storage keeps values verbatim; escaping happens at output.** A company called
`Smith & <Sons>` is stored exactly like that, and every place that writes it into HTML escapes it. Two functions do
the escaping (both in `Laf\Util\Util`, see [16-settings-locale-utilities.md](16-settings-locale-utilities.md#util)):

| Function | Context | `O'Brien & <Sons> "Ltd"` becomes |
|---|---|---|
| `Util::e($value)` | element text and single- or double-quoted attributes | `O&apos;Brien &amp; &lt;Sons&gt; &quot;Ltd&quot;` |
| `Util::attr($value)` | double-quoted attributes only (leaves `'` for inline handlers) | `O'Brien &amp; &lt;Sons&gt; &quot;Ltd&quot;` |

What LAF escapes for you (verified by drawing `<b>X</b>` through each API):

- Field values, labels, hints and placeholders in every form input, in all draw modes.
- Field values in VIEW forms and generated pages.
- `Html::setPageTitle()`, `Dropdown` text, `Fieldset` legend, `TabItem` title.
- `Table::addRow()`, `Table::fromArray()` (cells and headers), `Table::setCaption()`, `Cell::setText()`.
- `Link` href and title attributes; `Link::setConfirmationMessage()` is JavaScript-escaped into `confirm('...')`.
- `HeaderComponent` attribute values; `Flash::success()` / `error()` / `warning()` / `info()` messages.
- Grid cell values (grid.js writes them with `textContent`) and the grid title.
- Autocomplete labels (the script inserts them as text).

What you escape yourself: anything you concatenate into HTML strings and pass to one of the raw APIs below.

```php
use Laf\UI\Page\GenericPage;
use Laf\Util\Util;

$page = new GenericPage();
// setTitle() takes HTML (pages pass anchors), so the user-provided part is escaped by hand
$page->setTitle('Employee: <a href="?module=employee&action=view&id=' . (int)$employee->getIdVal() . '">'
    . Util::e($employee->getFirstNameVal() . ' ' . $employee->getLastNameVal()) . '</a>');
```

## APIs that take raw HTML

These methods insert their argument into the page **without escaping**, because pages pass icons, links and line
breaks through them. Never pass user data to them without `Util::e()`.

| Class | Raw HTML API |
|---|---|
| `Laf\UI\Page\GenericPage` / `AdminPage` | `setTitle()`, `setSubtitle()`, `setHeader()`, `setFooter()` |
| `Laf\UI\Page\Html` | `setMenu()`, `setHtmlHeader()`, `setFooterHtml()`, `addInlineScript()` (script code), `addInlineStyle()` (CSS) |
| `Laf\UI\Page\HeaderComponent` | the `$code` / `$css` of `::inlineScript()` / `::inlineStyle()` |
| `Laf\UI\Container\HtmlContainer` | constructor, `setContent()`, `addContent()` |
| `Laf\UI\Container\Modal` | `setTitle()` |
| `Laf\UI\Component\Link` | the caption (constructor `$text`, `setValue()`) |
| `Laf\UI\Component\Alert` | constructor `$title` and `$message`, `setTitle()`, `setMessage()` |
| `Laf\UI\Component\Dropdown` | `addHtml()` |
| `Laf\UI\Component\Carousel` | `setTopContent()` |
| `Laf\UI\Table\Cell` (`Td`, `Th`) | constructor `$data`, `setData()` (use `setText()` for text) |
| `Laf\UI\Form\FormLayout` | `html($html, $modes)` |
| `Laf\UI\Form\Input\*` (every input) | `setPrefixHtml()`, `setSuffixHtml()` (`setPrefix()` / `setSuffix()` are escaped) |
| `Laf\UI\Flash` | `Flash::html()`, `Flash::add($message, $type, true)` |
| `Laf\Database\Field\Field` | values of a field with `setAllowHtml(true)` |
| grid.js | a column callback that returns a string (it becomes the cell's `innerHTML`) |

```php
use Laf\UI\Component\Alert;
use Laf\UI\Table\Td;
use Laf\Util\Util;

$alert = new Alert('Saved', 'Employee <strong>' . Util::e($name) . '</strong> was updated.');   // markup + escaped data
$cell  = new Td();
$cell->setText($employee->getNotesVal());            // escaped
$cell2 = new Td('<i class="fa fa-check"></i>');      // raw: a constant icon
```

> **Note:** `Util::e()` does not encode existing entities a second time. That keeps old rows readable; it never
> turns escaped text back into markup.

## Field::setAllowHtml()

A column that legitimately stores HTML (a rich-text description edited by trusted staff) can opt out of escaping:

```php
$employee->getNotesFld()->setAllowHtml(true);   // VIEW mode and getValueNl2Br() print the value raw
```

Do this only for content that is sanitised on the way in (an HTML sanitiser such as HTML Purifier) or written by
fully trusted users. The audit log's `changes` column uses it because LAF writes that JSON itself.

## CSRF protection

Every write that a browser can trigger carries a token tied to the session.

**Forms.** Each form has one token per session and form name (`_csrf_token_{form}` in the session and as a hidden
input), created on first use and reused by every draw, so several tabs and the back button keep working.
`processForm()` checks it before anything else and throws `Laf\Exception\CsrfTokenException` when it is missing or
wrong. Protection is on by default (`setCsrfProtection(false)` exists for forms that are not posted by a browser).
After login or logout, rotate it with `$form->regenerateCsrfToken()`.

```php
use Laf\Exception\CsrfTokenException;

try {
    $id = $form->processForm();
} catch (CsrfTokenException $e) {
    Flash::error('The form expired. Please try again.');   // e.g. the session timed out
}
```

**Delete links.** Generated pages build delete links with the form's token in the query string and check it before
deleting, so a crafted link or an `<img src>` on another site cannot delete records:

```php
echo $form->withCsrfToken('?module=employee&action=delete&id=5');
// ?module=employee&action=delete&id=5&_csrf_token_employee=529f19735af04df8b04c804fe7d3ad610a05cd3a8b678c0e737cf86683582411

// in the delete action
if (!$form->isValidCsrfToken($_GET[$form->getCsrfSessionKey()] ?? null)) {
    Flash::error('The record was not deleted: the link is not valid');
    UrlParser::redirectToListPage();
}
```

Pretty-URL rewrite rules must keep the query string (`[QSA]` in Apache) or the token is lost.
See [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md).

**Autocomplete "create new".** Creating a referenced row from an autocomplete is a POST that carries the form's
token; `handleLookupRequest()` answers `405` for GET, `403` for a bad token, a non-editable field or a VIEW form
([08-relations.md](08-relations.md)).

**Modals with their own form.** A `Modal` with `setIncludeForm(true)` posts to your own handler; put the token in
it and check it there:

```php
$modal->setIncludeForm(true)
      ->setFormAction('?module=employee&action=deactivate')
      ->addHiddenInput($form->getCsrfSessionKey(), $form->getCsrfToken());

// handler
if (!$form->isValidCsrfToken($_POST[$form->getCsrfSessionKey()] ?? null)) { /* refuse */ }
```

**Your own endpoints.** Any hand-written handler that changes data should do the same: reuse a form's
`getCsrfToken()` / `isValidCsrfToken()`, and never change data on GET without a token.

## Mass-assignment guard

A form posts a signed list of the editable elements it drew (`{form}_fields`, `"name,name|hmac"`, signed with a
per-session secret the browser never sees):

```text
svefg_anzr,ynfg_anzr,rznvy|17298d270639fe73b162f113e65926ab728b5c4de61f3425dfc119ae309f54d8
```

A tampered list is ignored. With `setRestrictToRenderedFields(true)` (or Settings
`form.restrict_to_rendered_fields`), posted fields that are not in the list are ignored too. The difference, for
an employee form that drew first name, last name and e-mail and a request that adds `salary` by hand:

```php
$request = $form->buildRequest(['first_name' => 'Mass', 'last_name' => 'Assign', 'email' => 'mass@ch17.test'], DrawMode::INSERT);
$request[str_rot13('salary')] = '999999.00';   // not drawn, not in the signed list

// setRestrictToRenderedFields(false), the default:  salary = '999999.00'
// setRestrictToRenderedFields(true):                salary = NULL
```

Turn the guard on for every form that leaves columns out (a self-service profile without `salary` or `is_admin`):

```php
// app/settings.php
'form' => ['restrict_to_rendered_fields' => true],
```

Read-only elements (`FormLayout::readOnly()`) post nothing and are left untouched either way; values set with
`setSubmittedFieldValue()` always apply. Details in [06-forms.md](06-forms.md).

## SQL injection

SQL that LAF builds is safe by construction:

- **Values are always bound** as parameters (models, `Query`, `Db` helpers, grid filters), typed through
  `Db::binding()` or the field types.
- **Identifiers are quoted by one rule**, `Identifier::quote()`, which first strips everything that is not
  `[A-Za-z0-9_]`. `Db::identifier()` (used by `insert()` / `update()` / `delete()`) refuses such names outright.
- **Column names from callers are validated against the model.** `Query::where()`, `orderBy()` and friends throw
  `InvalidArgumentException` for a column the table does not have ("Table employee has no column ..."), operators
  must be in `Query::OPERATORS`, and sort directions must be ASC or DESC. `find()` filters go through the same
  check.
- **`whereContains()` / `whereStartsWith()` / `whereEndsWith()`** escape `%`, `_` and `\` in the search text.
- **Grid requests** (`GridRequest`) drop unknown columns and operators before `GridQuery` builds the SQL.

Where you can still introduce injection:

- Raw SQL strings you pass to `Db` (`getAllAssoc()`, `execute()`, `query()`) or to `PhpGrid::setSqlQuery()`. Use
  `:name` / `?` parameters for every value, never string concatenation.
- A table or column *name* that comes from a request: whitelist it, or pass it through `Db::identifier()`.
  (`Identifier::quote()` would strip characters and might turn the name into a different, valid column.)
- `Query::where($field, 'LIKE', $pattern)` keeps the pattern as given: `%` and `_` in user input act as
  wildcards. Use `whereContains()` for user search text.

```php
// wrong
Db::getAllAssoc("SELECT * FROM employee WHERE last_name = '" . $_GET['name'] . "'");

// right
Db::getAllAssoc('SELECT * FROM employee WHERE last_name = :name', ['name' => $_GET['name'] ?? '']);
Employee::query()->whereContains('last_name', $_GET['q'] ?? '')->limit(20)->get();
```

## Uploaded files

`Laf\Filesystem\Document` treats every upload as hostile ([15-files-and-documents.md](15-files-and-documents.md#security-rules)):

- the MIME type is read from the content, and `upload()` checks it against `upload.allowed_mime_types`;
- stored names are generated (time + random), never taken from the user;
- only raster images, PDF and plain text are served inline; SVG, HTML and everything else are sent as an
  `application/octet-stream` attachment;
- every response carries `Content-Security-Policy: sandbox; default-src 'none'` and
  `X-Content-Type-Options: nosniff`.

Your part: keep `upload.documents_directory` outside the web root, check permissions before
`handleRequest()`, and keep `storeFile()` (which skips the allow list) for files your own code produced.

## Sessions

The CSRF tokens, the field-list signing key (`_laf_form_key`) and flash messages live in `$_SESSION`. When a form
needs the session and none is active, LAF calls `session_start()` with PHP's defaults. Start the session yourself,
early in the bootstrap, with secure cookie settings:

```php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,        // HTTPS only
    'httponly' => true,      // not readable from JavaScript
    'samesite' => 'Lax',     // a second line of defence against CSRF
]);
session_name('myapp_session');
session_start();

// after a successful login
session_regenerate_id(true);
$_SESSION['user_id'] = $user->getIdVal();
BaseObject::setActiveUserId($user->getIdVal());   // audit log and created_by/updated_by
```

`session_regenerate_id()` keeps the session data, so the CSRF tokens and the signing key survive the login. If you
want fresh tokens after a privilege change, call `regenerateCsrfToken()` on the forms concerned (or clear the
`_csrf_token_*` keys).

## Access control

LAF has no built-in authorisation layer. Generated pages, `Document::handleRequest()`, grid endpoints
(`bootstrap()`) and `handleLookupRequest()` serve whoever reaches them. Check authentication and permissions in
your front controller before dispatching to a page ([12-routing-and-generated-pages.md](12-routing-and-generated-pages.md)),
and in every hand-written endpoint. Restrict which rows a user may see with `Query` conditions or the field's
database selection criteria, not by hiding links.

## Checklist

- Escape user data with `Util::e()` before passing it to any raw-HTML API.
- Use `setAllowHtml(true)` only for sanitised or trusted content.
- Keep CSRF protection on; regenerate the session id on login (`session_regenerate_id(true)`).
- Keep the query string on pretty-URL rewrites (delete tokens).
- Turn on `form.restrict_to_rendered_fields` for forms that do not show every column.
- Bind every value in raw SQL; whitelist identifiers from requests.
- Store documents outside the web root; serve them through `Document::handleRequest()` after a permission check.
- Configure secure session cookies and HTTPS.
- Check authorisation in the front controller and every custom endpoint.
- Keep `settings.php`, `.env` and anything written by `Settings::saveToFile()` out of the web root and out of git.

## See also

- [06-forms.md](06-forms.md): CSRF, the signed field list, `setRequest()` / `buildRequest()`
- [08-relations.md](08-relations.md): autocomplete lookups and "create new"
- [11-pages-and-ui.md](11-pages-and-ui.md): components and their raw/escaped arguments
- [13-database.md](13-database.md): bindings and identifier quoting
- [15-files-and-documents.md](15-files-and-documents.md): file storage and serving
- [16-settings-locale-utilities.md](16-settings-locale-utilities.md): `Util::e()` / `Util::attr()`
