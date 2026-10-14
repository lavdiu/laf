# Files and documents

LAF stores uploaded files on disk and describes each one with a row in a `document` table. `Laf\Filesystem\Document`
does the work: it stores a posted file or a file already on disk, reads the real MIME type from the content,
resizes images and makes a thumbnail, and later streams the file back with caching headers and rules that stop an
upload from ever running as a page of your site. A foreign key column that points at `document` becomes a file
upload input on forms, so "an employee photo" or "a signed contract" is one column away.

On this page:

- [The document table](#the-document-table)
- [Settings](#settings)
- [Storing files](#storing-files)
- [What gets written](#what-gets-written)
- [Serving files](#serving-files)
- [Security rules](#security-rules)
- [Deleting](#deleting)
- [Files on forms: the File input](#files-on-forms-the-file-input)
- [Reference](#reference)

## The document table

`Document` wraps your application's `<package>\Document` model (`MyApp\Document` for the examples), which you
generate from a `document` table like any other. The example schema does not include it; add it when your
application needs files. These are the columns `Document` reads and writes (the same table LAF's own integration
tests create):

```sql
-- MySQL / MariaDB
CREATE TABLE `document` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `file_name_original` VARCHAR(255) DEFAULT NULL,   -- the name the user uploaded ("Company logo.png")
  `file_name` VARCHAR(255) DEFAULT NULL,            -- stored name: 20261014185736_7fb7210a0c1a_full.png
  `file_extension` VARCHAR(10) DEFAULT NULL,
  `thumbnail_name` VARCHAR(255) DEFAULT NULL,       -- images only
  `file_size` INT DEFAULT NULL,                     -- bytes of the stored file
  `mime_type` VARCHAR(255) DEFAULT NULL,            -- read from the content, not from the browser
  `encrypt_key` VARCHAR(255) DEFAULT NULL,          -- optional: a record with a key is never served
  `created_on` DATETIME DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `updated_on` DATETIME DEFAULT NULL,
  `updated_by` INT DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

```sql
-- PostgreSQL
CREATE TABLE document (
  id SERIAL PRIMARY KEY,
  file_name_original VARCHAR(255) DEFAULT NULL,
  file_name VARCHAR(255) DEFAULT NULL,
  file_extension VARCHAR(10) DEFAULT NULL,
  thumbnail_name VARCHAR(255) DEFAULT NULL,
  file_size INTEGER DEFAULT NULL,
  mime_type VARCHAR(255) DEFAULT NULL,
  encrypt_key VARCHAR(255) DEFAULT NULL,
  created_on TIMESTAMP DEFAULT NULL,
  created_by INTEGER DEFAULT NULL,
  updated_on TIMESTAMP DEFAULT NULL,
  updated_by INTEGER DEFAULT NULL
);
```

`encrypt_key` and the metadata columns are optional; the other six are required by `Document`. Then regenerate
(`vendor/bin/laf-generate --bootstrap=app/bootstrap.php --tables=document`, see
[02-code-generation.md](02-code-generation.md)). A different model class (another namespace, an existing table) is
set once in the bootstrap:

```php
use Laf\Filesystem\Document;

Document::setModelClass(\Legacy\Attachment::class);   // must extend Laf\Database\BaseObject; null restores the default
```

Without a model, `new Document()` and the store methods throw `LogicException: Document model \MyApp\Document not
found: generate the document table or call Laf\Filesystem\Document::setModelClass()`.

To attach files to a record, give its table a foreign key to `document`:

```sql
ALTER TABLE employee
  ADD COLUMN photo_id INT NULL COMMENT '{"fields":{"setLabel":"Photo"}}',
  ADD CONSTRAINT employee_photo_fk FOREIGN KEY (photo_id) REFERENCES document (id);
```

## Settings

| Key | Default | What it does |
|---|---|---|
| `upload.documents_directory` | required | Where files are written. Must exist and be writable; joined with exactly one slash. Keep it **outside** the web root. |
| `upload.allowed_mime_types` | required by `upload()` | List of exact types or `type/*` wildcards (`*/*` allows everything). Checked against the type read from the content. Also used as the file input's `accept` attribute. |
| `upload.max_image_size` | `1200` | Longest side, in pixels, of a stored image. |
| `upload.thumbnail_size` | `300` | Longest side of the thumbnail. |
| `upload.image_mime_types` | common web image types | What `isImage()` accepts (decides thumbnail vs download link on forms). |
| `upload.cache_max_age` | `31536000` (one year) | `Cache-Control: private, max-age=...` on served files. |
| `upload.not_found_url` | none | Where a missing document redirects; fallback: the legacy `404` key; neither set = a plain 404 response. |

```php
use Laf\Util\Settings;

Settings::load([
    'upload' => [
        'documents_directory' => __DIR__ . '/../storage/documents',
        'allowed_mime_types' => ['image/*', 'application/pdf', 'text/plain'],
        'max_image_size' => 1600,
        'not_found_url' => '/errors/404',
    ],
]);
```

## Storing files

### A posted file: `upload()`

```php
use Laf\Filesystem\Document;

// <input type="file" name="contract">
$documentId = Document::upload('contract');      // int id, or null when nothing usable was posted
```

`upload()` returns `null` (and stores nothing) when the field is absent, the upload failed, the file is empty, or
its MIME type is not in `upload.allowed_mime_types`. It throws `RuntimeException` when the file was fine but could
not be stored (missing directory, image processing failure, database error); files written so far are removed. A
second argument takes a `$_FILES`-shaped array instead of reading `$_FILES`, which is how you test it or reuse it
for a file from another source.

The type check uses the content. A file that claims to be a PNG but holds HTML is refused:

```php
file_put_contents('/tmp/evil.png', '<html><script>alert(1)</script></html>');
Document::upload('logo', [
    'name' => 'evil.png', 'type' => 'image/png', 'tmp_name' => '/tmp/evil.png',
    'error' => UPLOAD_ERR_OK, 'size' => filesize('/tmp/evil.png'),
]);                                              // null
Document::detectMimeType('/tmp/evil.png');      // "text/html"
Document::isAllowedMimeType('image/webp');      // true  (image/*)
Document::isAllowedMimeType('text/html');       // false
```

### Several files: `uploadMany()`

```php
// <input type="file" name="attachments[]" multiple>
$ids = Document::uploadMany('attachments');     // [12, 13] in posted order; unusable slots are skipped
```

The rules are those of `upload()`. A failure part-way throws; the documents stored before it keep their records.

### A file already on disk: `storeFile()`

For a generated report, a download, an import:

```php
$id = Document::storeFile('/tmp/big.png', 'Company logo.png');   // the original name to record (default: basename)
```

`storeFile($path, $originalName = '', $mimeType = null)` copies the file (an uploaded file is moved), detects the
type unless you pass one, and **does not consult `upload.allowed_mime_types`**: it trusts the caller. It throws
`InvalidArgumentException` for a missing or unreadable source and `RuntimeException` for storage failures.

## What gets written

`storeFile()` on a 2000x1000 PNG, against a `document` table:

```php
$id = Document::storeFile('/tmp/big.png', 'Company logo.png');   // 1
```

```text
id                 1
file_name_original Company logo.png
file_name          20261014185736_7fb7210a0c1a_full.png     width="1200" height="600"
file_extension     png
thumbnail_name     20261014185736_7fb7210a0c1a_thumb.png    width="300" height="150"
file_size          4307
mime_type          image/png
encrypt_key        NULL
created_by         1
```

- Stored names are the time plus 12 random hex characters, never the user's name, so two uploads cannot collide
  and a user cannot choose a path.
- PNG, JPEG, GIF, WebP, BMP and AVIF images are resized with `claviska/simpleimage` to fit `max_image_size`, and a
  thumbnail is written; the stored extension follows the type. Other files (PDF, text, SVG, Office) are stored
  unchanged with the original name's extension (lower-cased, letters and digits, at most 10) or one derived from the
  type, and get no thumbnail.
- The record is inserted through the model, so `created_on` / `created_by` and the audit log apply.

## Serving files

`handleRequest($action)` streams a stored file and ends the request. The three actions are the class constants
`Document::ACTION_VIEW` (`'view'`, inline), `ACTION_DOWNLOAD` (`'download'`, attachment) and `ACTION_THUMBNAIL`
(`'thumbnail'`, the thumbnail, or the file itself when there is none). A small document front controller:

```php
// public/document.php  -- /document.php?action=view&id=12
use Laf\Filesystem\Document;

require __DIR__ . '/../app/bootstrap.php';
session_start();
if (!isset($_SESSION['user_id'])) {          // your access rule: Document does not check permissions
    http_response_code(403);
    exit;
}

$action = $_GET['action'] ?? Document::ACTION_VIEW;
(new Document((int)($_GET['id'] ?? 0)))->handleRequest($action);   // sends headers + body, then exit
```

`handleRequest($action, false)` returns instead of calling `exit` (tests, embedded runtimes). The headers for the
PNG above:

```text
== view
Cache-Control: private, max-age=31536000
Last-Modified: Wed, 14 Oct 2026 18:57:36 GMT
ETag: "aba1040678da7552ebe3beabf5edfb88"
Content-Type: image/png
Content-Length: 4307
X-Content-Type-Options: nosniff
Content-Security-Policy: sandbox; default-src 'none'
Content-Disposition: inline; filename="Company logo.png"

== download
...
Content-Disposition: attachment; filename="Company logo.png"
```

- A request with a matching `If-None-Match` (or a fresh `If-Modified-Since`) gets `304 Not Modified` without a body.
- A non-ASCII name is sent twice, as an ASCII fallback and RFC 6266 UTF-8:
  `Content-Disposition: inline; filename="Notes _.txt"; filename*=UTF-8''Notes%20%C3%BC.txt`
- A missing record, a record with an `encrypt_key`, a missing file or an unknown action redirect to the not-found URL
  (`Location: /errors/404`, status 302) or, with none configured, answer `404` with the text `Document not found`.

## Security rules

Uploads are user-controlled content. `Document` applies these rules; keep them in mind when you serve files any
other way.

- **The type comes from the content** (`finfo`), never from the browser's `Content-Type`. `upload()` checks it
  against your allow list.
- **Only raster images, PDF and plain text are sent inline** (`image/png`, `jpeg`, `gif`, `webp`, `bmp`, `avif`,
  `application/pdf`, `text/plain`). Everything else, SVG and HTML included, goes out as
  `application/octet-stream` with `Content-Disposition: attachment`. An SVG stored with `storeFile()`:

  ```text
  Content-Type: application/octet-stream
  Content-Security-Policy: sandbox; default-src 'none'
  Content-Disposition: attachment; filename="d.svg"
  ```

- **Every response carries `Content-Security-Policy: sandbox; default-src 'none'` and
  `X-Content-Type-Options: nosniff`**, so even a file opened directly cannot run script in your origin.
- **Stored names are generated**, and the download name is cleaned of path and control characters.
- **Keep `upload.documents_directory` out of the web root.** Files served by the web server directly bypass all of
  the above.
- **Access control is yours.** `Document` serves any id it is given; check that the current user may see the
  document (and the record it belongs to) before calling `handleRequest()`.

> **Note:** the inline list is deliberately short. Do not "fix" an SVG logo by serving it inline from the documents
> directory: an SVG can carry script. If you need inline SVG, sanitise it and store it as a static asset.

See [17-security.md](17-security.md) for the rest of LAF's security model.

## Deleting

```php
$document = new Document(12);
$document->hardDelete();    // true: record deleted, then the file and thumbnail removed
```

The files are removed only after the record was deleted; `false` means the record was not deleted and the files
are untouched. Clear or delete the referencing column (`employee.photo_id`) first when it has a foreign key
constraint.

## Files on forms: the File input

A column that is a foreign key to the `document` table gets a `Laf\UI\Form\Input\File` element automatically
(`Field::isDocumentField()`), and the form is drawn with `enctype="multipart/form-data"`. Nothing else is needed:

```php
use Laf\UI\Form\DrawMode;
use MyApp\Employee;

$employee = new Employee($id);
$form = $employee->getForm(DrawMode::UPDATE);
if ($form->isSubmitted()) {
    $form->processForm();   // a sent file is stored through Document::upload(); no file = the current id is kept
}
echo $form->draw();
```

How each mode draws it:

- **INSERT / UPDATE**: `<input type="file">` with `accept` taken from `upload.allowed_mime_types` (unless you set
  one). In UPDATE mode the stored document is shown under the input: a 150 px thumbnail linking to the full image,
  or a "Download File" link (`setShowCurrentFile(false)` hides it).
- **VIEW**: the thumbnail link or download link, `(no file attached)` when empty, `(file removed)` when the
  record exists but the file is gone.

During `processForm()` a sent file is stored with `Document::upload()` and its id written into the column. A failed
upload or a refused type becomes a form error on that field ("Photo: the file was not accepted"), shown like any
validation error ([06-forms.md](06-forms.md)). `processForm($documentHandlerClass)` takes another class with a static
`upload(string $fieldName)` method if you store files elsewhere (object storage, for example).

The links point to `?module=document&submodule={view|thumbnail|download}&id={id}` by default. Point them at your
own document controller once, in the bootstrap:

```php
use Laf\UI\Form\Input\File;

File::documentUrl('thumbnail', 12);     // "?module=document&submodule=thumbnail&id=12"

File::setDocumentUrlBuilder(fn (string $action, int $documentId): string => "/document/$action/$documentId");
File::documentUrl('download', 12);      // "/document/download/12"
```

The builder's return value is escaped when drawn. [07-form-inputs.md](07-form-inputs.md) covers the input's
common options (hint, prefix/suffix, errors).

## Reference

`Laf\Filesystem\Document`:

| Method | What it does | Notes |
|---|---|---|
| `Document::upload($field, ?$file)` | Store a posted file | `?int`; null = nothing usable; `RuntimeException` on storage failure |
| `Document::uploadMany($field, ?$files)` | Store a multi-file field | `int[]` in posted order |
| `Document::storeFile($path, $originalName = '', ?$mimeType)` | Store a file on disk | No allow-list check |
| `Document::detectMimeType($path)` | Type from the content | `null` when unknown |
| `Document::isAllowedMimeType($type)` | Against `upload.allowed_mime_types` | `MissingConfigParamException` when unset |
| `Document::setModelClass(?$class)` | Model other than `<package>\Document` | |
| `Document::setNotFoundUrl(?$url)` / `Document::notFoundUrl()` | Not-found redirect | Overrides the settings |
| `new Document($id = null)` | Wrapper around a record | `LogicException` without a model |
| `getModel()` / `getId()` / `recordExists()` | The record | |
| `getDocumentsDirectory()` | Directory with a trailing slash | |
| `getFullPathFullSize()` / `getFullPathThumbnail()` | Paths, `''` when unset | |
| `fileFullSizeExists()` / `fileThumbnailExists()` | Record exists and the file is readable | |
| `getMimeType()` / `isImage()` | Stored type | `isImage()` uses `upload.image_mime_types` |
| `hardDelete()` | Record, then files | `bool` |
| `handleRequest($action = 'view', $exit = true)` | Stream a file | |

Protected hooks for subclasses: `generateBaseName()` (stored name), `sendHeader($header, $code)` and
`sendBody($path)` (tests capture the response this way). Constants: `ACTION_VIEW`, `ACTION_DOWNLOAD`,
`ACTION_THUMBNAIL`, `DEFAULT_MAX_IMAGE_SIZE`, `DEFAULT_THUMBNAIL_SIZE`, `DEFAULT_CACHE_MAX_AGE`.

`Laf\UI\Form\Input\File`: `File::setDocumentUrlBuilder(?callable)`, `File::documentUrl($action, $id)`,
`setShowCurrentFile(bool)`, `isShowCurrentFile()`, constants `ACTION_VIEW`, `ACTION_THUMBNAIL`,
`ACTION_DOWNLOAD`; it extends `Text`, so every common input method applies.

## See also

- [06-forms.md](06-forms.md): `processForm()` and form errors
- [07-form-inputs.md](07-form-inputs.md): every input class
- [16-settings-locale-utilities.md](16-settings-locale-utilities.md): the full settings list
- [17-security.md](17-security.md): file serving among the other security rules
- [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md): building a document module in a front controller
