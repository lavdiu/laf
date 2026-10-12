Review of `sample_files/` (grid.js left out) on `improvements1`, 2026-10-12: the bootstrap is a 2019-era web bootstrap that does not run from the CLI as shipped (no `project.package_name`, `$_SERVER` reads, HTML output), `class_generator.php` offers no table selection, no dry run and no way to install new pages, and the apps worked around that with their own scripts; the recommendation is a real `vendor/bin/laf-generate` command backed by a small `Laf\Generator\Cli` class, a settings-array config that web and CLI share, and a trimmed `examples/` folder.

# What is there

| File | Lines | Purpose | Verdict |
|---|---|---|---|
| `config.php` | 33 | web bootstrap: timezone, autoloaders, includes `database.php` + `constants.php`, sets `debug_level` | partly stale: `debug_level` is read nowhere in `src/`, the generated-autoloader entry is dead for Composer apps |
| `database.php` | 17 | `database.*` via `setProperty()` | fine, but it never sets `project.package_name`, so `class_generator.php` throws `MissingConfigParamException` ("Invalid Namespace") on the first run |
| `constants.php` | 41 | SMTP block, `allowed_upload_images`, host-derived URLs, `404` key | mostly stale: `allowed_upload_images` is not a key LAF reads (it is `upload.allowed_mime_types`), `404` is the legacy fallback of `upload.not_found_url`, SMTP is not LAF, `filter_input(INPUT_SERVER, …)` is null on the CLI so `homepage` becomes `http:///` |
| `functions.php` | 49 | `isLocalhost()`, `isLive()`, `coalesce()`, `uuid()`, `getBannerImage()` | `isLocalhost()` reads `$_SERVER['SERVER_NAME']` (warning under E_ALL on the CLI); `coalesce()` / `uuid()` duplicate `Util::coalesce()` / `Util::uuid()`; `getBannerImage()` uses `rand()` and a root-absolute `/assets/...` path |
| `class_generator.php` | 32 | runs `DatabaseGenerator` | works only from a browser-shaped bootstrap, see below |
| `test.php` | 121 | inspector demo + a 100-line PhpGrid demo | the grid demo queries a `business_partner` schema that does not exist in the sample database (`lafshell`) and uses PostgreSQL `::date` casts; the inspector part is the only runnable bit |
| `populate_table_object_list.php` | 15 | `BaseObject::populateTableObjectList()` | fine |
| `grid-callback-examples.md` | 182 | Grid callback docs | Grid is parked; leave |

Keys the framework actually reads (from `src/`, Grid excluded): `project.package_name`, `database.*`,
`settings.use_pretty_url`, `settings.label.translations`, `upload.documents_directory`,
`upload.allowed_mime_types`, `upload.image_mime_types`, `upload.max_image_size`, `upload.thumbnail_size`,
`upload.cache_max_age`, `upload.not_found_url`, `form.restrict_to_rendered_fields`, `form.native_date_inputs`,
`locale.*`. The sample config sets only `database.*` of these.

# class_generator.php today

```php
require_once __DIR__ . '/config.php';        // the whole web bootstrap
ob_start();
echo "<pre>Starting Class Generation";
$generator = new DatabaseGenerator($libraryPath);
$generator->setExcludedTables([...])->setGenerateAutoloader(false)->generateEverything($labels);
```

1. **It is a web page, not a command.** `ob_start()` + `<pre>` are for a browser; on the CLI it prints the tag
   and the generator's `\n`-prefixed lines. There is no `exit` code, no argument parsing, no usage text.
2. **It cannot run as shipped.** `config.php` never sets `project.package_name`; `DatabaseGenerator` then
   builds its config with an empty namespace and `generateEverything()` throws.
3. **All or nothing.** Every run regenerates every table's Base class and page. routerarena wrote
   `app/util/regenerate_single_base.php` (argv table name, hand-copied config array) to get around it, and both
   apps keep a copy of this script in `app/util/generate_classes.php` with the HTML `echo` intact. That is the
   evidence that a CLI is missing.
4. **New pages never reach `pages/`.** Pages are written to `generator_cache/pages/` and the developer copies
   them by hand. `PageGenerator::setWriteOnLiveDirectory()` exists but `DatabaseGenerator` never calls it, so there
   is no "install the page of a new table, leave existing pages alone" step, which is exactly what
   `rewrite_class = false` does for the model classes.
5. **No dry run / no listing.** You cannot see which tables match `setTables()` / `setExcludedTables()` patterns
   before files are written, and `getGeneratedFiles()` is never printed.
6. **Options are code edits.** Table selection, excluded tables, the library path and `rewrite_class` are
   literals in the script; `--force` and friends do not exist.
7. Minor: `Settings::getInstance()->propertyExists(...)` is the old spelling of `Settings::has()`.

`DatabaseGenerator` itself is in good shape after the generator review (`setTables()` / `setExcludedTables()`
with `*` patterns, `setOutput()`, `setLogger()`, `getGeneratedFiles()`, `getWarnings()`, `processTables()` /
`processPages()` callable separately, `write()` already flushes only outside the CLI). The missing pieces are the
command around it and two small methods on it.

# Recommendation

## 1. A real command: `vendor/bin/laf-generate`

Add `bin/laf-generate` (declared in `composer.json` `"bin"`) that is a four-line launcher for a new
`Laf\Generator\Cli` class, `getopt()`-based, no console dependency (keeps the dependency list and the PHP 8.0 floor):

```
vendor/bin/laf-generate --bootstrap=data/config.php [--lib=app/lib] [options]

  --bootstrap=FILE   PHP file that configures Settings (database.*, project.package_name); required
                     unless LAF_BOOTSTRAP is set
  --lib=DIR          library folder that receives {Namespace}/, default app/lib
  --tables=a,b,inv_* only these tables (patterns with *)
  --exclude=x,y      tables left out (default: audit_log, sql_error, object_list, phinx_*)
  --classes          only Base + model classes
  --pages            only pages (into generator_cache/pages)
  --install-pages    also copy the page of every table that has none in pages/ yet
  --force            overwrite existing model classes (Base classes are always rewritten)
  --list             dry run: print the tables and the files that would be written
  --labels=FILE      JSON/PHP file with page label translations (default: Settings settings.label.translations)
  --strict           exit 1 when the generator reported warnings
  --quiet / -q       only warnings and the summary
```

Behaviour: plain-text progress lines (no HTML), one summary line (`12 tables, 24 classes, 12 pages, 2 pages
installed, 1 warning`), exit 0 on success, 1 on warnings with `--strict`, 2 on usage or configuration errors
(missing bootstrap, no `project.package_name`, no database connection) with the message on STDERR. Unknown
options are errors, `--help` prints the block above.

The apps then replace their `app/util/generate_classes.php` with `vendor/bin/laf-generate
--bootstrap=data/config.php --lib=app/lib` and routerarena's `regenerate_single_base.php` becomes
`--tables=router --classes`. Their current scripts keep working (nothing in `DatabaseGenerator` changes
signature), so this is additive.

## 2. Two additions to `DatabaseGenerator`

- `installMissingPages(): static` (or `setInstallMissingPages(bool)` honoured by `generateEverything()`): after
  `processPages()`, copy `generator_cache/pages/{table}.page` to `live_page_dir` when no file is there. Records
  the copies in `getGeneratedFiles()`.
- `setForceRewriteClasses(bool)` as the fluent twin of the constructor flag, so the CLI can toggle it after
  construction.

Both are small and keep `generateEverything()` backwards compatible.

## 3. One config for web and CLI

Replace `config.php` + `database.php` + `constants.php` with one bootstrap that loads a settings array and
reads overrides from the environment, so the same file serves `index.php`, the CLI and the tests:

```php
// examples/bootstrap.php
require_once __DIR__ . '/../vendor/autoload.php';
Settings::getInstance()->loadFromFile(__DIR__ . '/settings.php');   // nested array -> dotted keys
foreach (['database.hostname' => 'LAF_DB_HOST', 'database.database_name' => 'LAF_DB_NAME', ...] as $key => $env) {
    if (getenv($env) !== false) { Settings::set($key, getenv($env)); }
}
```

and a `settings.php` that returns the keys the framework reads (list above) with comments, instead of SMTP
credentials and host-name sniffing. Environment detection (`isLocalhost()`) becomes an `APP_ENV` variable or
a Settings key, which exists on the CLI too.

## 4. Trim the folder

- Move `sample_files/` to `examples/` (one place; `README.md` and `CLAUDE.md` already point to `examples/`),
  keeping `grid-callback-examples.md` and `grid.js` as they are.
- Drop `functions.php`: `Util::coalesce()` and `Util::uuid()` exist; `isLocalhost()` is replaced by the
  environment key; `getBannerImage()` is app code.
- Split `test.php`: keep the inspector demo as `examples/inspect_table.php` (runs against the test schema);
  drop the 100-line grid demo or point to `grid-callback-examples.md` (Grid is parked, so no rewrite).
- `populate_table_object_list.php` stays, it is fine.

# Effort

| Step | Size |
|---|---|
| `Laf\Generator\Cli` + `bin/laf-generate` + composer `bin` + unit test (argument parsing, `--list` against the LafShell schema in the integration suite) | about 250 lines + tests |
| `DatabaseGenerator::installMissingPages()` / `setForceRewriteClasses()` + test | 40 lines |
| `examples/bootstrap.php` + `settings.php`, folder move, README/CLAUDE.md pointers | 100 lines, mostly moves |

Not touched: Grid (`grid.js`, `grid-callback-examples.md`, the grid half of `test.php`), the apps' own
scripts (they can switch when they update their LAF copy).