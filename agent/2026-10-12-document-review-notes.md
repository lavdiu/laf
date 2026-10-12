Review of `src/Laf/Filesystem/Document.php` on `improvements1`, 2026-10-12: the class was untouched since 2021 and rewritten typed; it now reads the MIME type from the file, resizes only images, stores other files unchanged, returns null only when nothing usable was posted and throws on storage failures, builds paths from values, streams files with correct headers and a configurable not-found response; a `Document` model was added to the LafShell fixture, 9 unit and 3 integration tests (MariaDB and PostgreSQL) cover it, PHPStan level 5 is clean, SimpleImage updated to 4.4.0.

# Who uses it

- The Laf `File` form input (`new Document($id)`, `fileFullSizeExists()`, `isImage()`) for the stored-file line.
- routerarena has its own `RouterArena\Document` model with the same method names and its own `upload()`; it does
  **not** use `Laf\Filesystem\Document`. asm does not use it either. So every public method keeps its name and
  arguments, but the implementation was free to change.

# Defects fixed

| # | Defect | Fix |
|---|---|---|
| D1 | `upload()` documented `int`, returned null on every failure, including server-side ones (3 PHPStan hits) | `upload(): ?int`: null only when nothing usable was posted (field absent, upload error, empty file, MIME type not allowed); directory, image and database failures throw `RuntimeException` (files written so far are removed) |
| D2 | the client-sent `$_FILES[..]['type']` was trusted for the allowed-types check and stored as the MIME type | `finfo` reads the type from the file; the posted type is only a fallback when finfo gives nothing |
| D3 | every upload went through SimpleImage, so a PDF or document upload threw inside the try and silently returned null | only `image/png`, `jpeg`, `gif`, `webp`, `bmp`, `avif` are resized (`upload.max_image_size`, default 1200) and get a thumbnail (`upload.thumbnail_size`, 300); other files are moved (uploads) or copied (`storeFile()`) unchanged, `thumbnail_name` stays null |
| D4 | `bestFit('300', '300')` passed strings (2 PHPStan hits) | ints from Settings |
| D5 | `getFullPathFullSize()` concatenated the `Field` object from `getFileNameFld()` (worked only through `Field::__toString()`); `hardDelete()` called `getFullPathFullSize()` on the model, which only exists in routerarena | values via `getFieldValue()`; `hardDelete()` uses its own paths |
| D6 | an empty thumbnail name made `fileThumbnailExists()` test the directory itself (`file_exists(dir)` is true) | empty name → '' path → false |
| D7 | `handleRequest()` loaded the whole file into memory, sent fixed 2030 `Expires` / `Pragma: cache` / 2016 `Last-Modified` headers, an unquoted `Content-Disposition` named after the random stored name, and redirected to the bare Settings key `404` (an exception when unset) | `readfile()` streaming; `Content-Type` (validated, else octet-stream), `Content-Length`, real `Last-Modified`, `ETag` with 304 on `If-None-Match` / `If-Modified-Since`, `Cache-Control: private, max-age=upload.cache_max_age`, RFC 6266 `Content-Disposition` with the original name (ASCII fallback + `filename*`); not found → `upload.not_found_url`, legacy `404` key, `setNotFoundUrl()`, else a 404 response |
| D8 | `isImage()` hard-coded five types | `upload.image_mime_types` (default adds webp, bmp, avif, svg) |
| D9 | `$document_id` write-only; a missing model class was a fatal "class not found" | `getId()`, `getModel()`, `recordExists()`; `LogicException` naming `setModelClass()` |

# Modernisation

- Typed throughout, constants for the actions and defaults, `setModelClass()`, `setNotFoundUrl()`, `notFoundUrl()`,
  `detectMimeType()`, `isAllowedMimeType()` (`image/*` wildcards), `storeFile($path, $originalName, $mimeType)` for
  files already on disk (routerarena's `uploadFromUrl` case), `upload($field, $file)` accepts a `$_FILES`-shaped array
  (routerarena's article page reshapes multi-file uploads into one slot), `handleRequest($action, $exit)`.
- Stored names: `YYYYMMDDHHMMSS_<12 hex>_full.ext` / `_thumb.ext` instead of `microtime(true)_full.ext`; resized images
  always get the extension of their real type; other extensions are lower-cased, reduced to `[a-z0-9]` and ten chars
  (routerarena's column is `varchar(10)`), or derived from the MIME type when the name has none.
- `file_size` records the stored file (after resizing), not the posted size.
- Headers go through the protected `sendHeader()` and the body through `sendBody()`, so tests (and apps) can capture them.
- `getDocumentsDirectory()` returns the setting with exactly one trailing slash; routerarena's value already ends with
  `/`, so paths are unchanged there.
- `claviska/simpleimage` 4.3.0 → 4.4.0 (`composer.lock`, committed with the scaffolding/dependency update): removes the
  `imagedestroy()` deprecation PHP 8.5 reported on every resize.

# Tests

- `tests/Fixtures/LafShell/Document.php` + `Base/BaseDocument.php`: the document table in routerarena's column set.
- `tests/DocumentTest.php` (Unit, 9 tests, no database): request checks, MIME from content, wildcards, source and
  directory errors, missing model, paths, `isImage()`, not-found response (404 / legacy key / explicit URL / encrypt
  key / unknown action / missing file), the streamed response with its headers and the 304 path.
- `tests/DocumentIntegrationTest.php` (Integration suite, both engines): an 800×400 PNG posted as `text/plain` is
  stored as `image/png`, resized to 400×200 with a 100×50 thumbnail, the source copied not moved; a text file stored
  unchanged without a thumbnail, served as a download with the original name, then hard-deleted with its file; a
  failed insert (name longer than the column) leaves no file behind.
- Unit suite 665 tests / 2,630 assertions; PHPStan level 5 clean on Filesystem (6 errors before).

# Open items

- `upload()` handles one file per field; multi-file arrays (`name[]`) return null, the application loops them.
- Encrypted documents (`encrypt_key`) are still only refused, never decrypted; nothing in the apps decrypts either.
