Implementation notes for the `laf-generate` command and the `sample_files/` cleanup on `improvements1`, 2026-10-12 (follows `2026-10-12-sample-files-review.md`): `vendor/bin/laf-generate` backed by `Laf\Generator\Cli`, `DatabaseGenerator::installMissingPages()` / `setForceRewriteClasses()` / `plan()`, one `bootstrap.php` + `settings.php` for web and CLI, the stale sample files removed; 9 new tests, all suites and PHPStan green.

# The command

`bin/laf-generate` (declared in `composer.json` `bin`, so Composer links it into `vendor/bin` of asm and
routerarena) finds the autoloader (own checkout, `vendor/lavdiu/laf/bin`, or the current directory) and calls
`Cli::main($argv)`. `Cli::run(array $argv, $stdout, $stderr): int` does the work on injected streams, which is
how the tests drive it without a process.

| Option | Effect |
|---|---|
| `--bootstrap=FILE` (or `LAF_BOOTSTRAP`) | required; `require`d in an isolated scope, must leave Settings `database.*` and `project.package_name` set |
| `--lib=DIR` | default `app/lib`; `DatabaseGenerator` derives `{Namespace}/`, `{Namespace}/Base/`, `generator_cache/pages/`, `pages/` |
| `--tables=a,b,inv_*` / `--exclude=x,y` | patterns with `*` (the generator's `matchesAny()`); default exclusions `audit_log, sql_error, object_list, phinx_*`; `--exclude=` keeps everything |
| `--classes` / `--pages` | one half only (mutually exclusive); the generators create their own directories, so `--classes` leaves no `generator_cache/` behind |
| `--install-pages` | `DatabaseGenerator::setInstallMissingPages(true)`: copy `generator_cache/pages/{t}.page` to `pages/{t}.page` when missing |
| `--force` / `-f` | `rewrite_class`: overwrite existing model classes (never the live pages) |
| `--list` / `-l` | `DatabaseGenerator::plan()`: per table `write`/`keep`/`install` lines, nothing written |
| `--labels=FILE` | `.php` returning an array or `.json`; default Settings `settings.label.translations` |
| `--strict` | exit 1 when `getWarnings()` is not empty |
| `--quiet` / `-q` | only `Warning:` lines and the summary |

Output: the generator's progress lines (trimmed, one per line), then `Done: N tables, N class files, N pages,
N pages installed, N warnings`. Exit codes: 0, 1 (warnings with `--strict`), 2 (usage error, no bootstrap,
bootstrap threw, no package name, no connection, no table matches, labels file unusable, generation threw);
every error goes to STDERR prefixed `laf-generate:`.

Parsing is hand-written (`--name=value`, `--name value`, flags, `-f -l -q -h`), no console dependency, so the
dependency list and the PHP 8.0 floor are unchanged.

# DatabaseGenerator additions (all additive)

- `setForceRewriteClasses(bool)` / `isForceRewriteClasses()`: fluent twin of the constructor flag.
- `setInstallMissingPages(bool)`: `generateEverything()` ends with `installMissingPages()`.
- `installMissingPages()`: for each selected table copies the generated page to `live_page_dir` when no page
  exists there; an existing live page is never touched; a missing generated page is a warning. Copies are
  listed by `getInstalledPages()` and `getGeneratedFiles()`.
- `plan()`: the Base class, class (+ exists), page, live page (+ exists) paths per selected table, computed from
  the generators' own path methods without inspecting the tables.
- `createDirectoryStructure()` announces itself once instead of once per directory.

# sample_files/

| Before | After |
|---|---|
| `config.php`, `database.php`, `constants.php`, `functions.php` | `bootstrap.php` (Composer autoloader, `Settings::loadFromFile('settings.php')`, `LAF_DB_*` / `APP_ENV` overrides, timezone and error display from `app.*`, session + `BaseObject::setActiveUserId()` for web requests only) + `settings.php` (nested array of every key LAF reads: `app`, `project`, `database`, `settings`, `form`, `upload`, `locale`) |
| `class_generator.php` (`ob_start()`, `<pre>`, no exit code, no package name) | DatabaseGenerator from PHP for applications that generate from inside the app: plain output on the CLI, warnings on STDERR, exit code |
| `test.php` (inspector demo + 100-line grid demo on a schema the sample DB lacks) | `inspect_table.php <table>`: prints the inspector's columns, nullability and foreign keys |
| `populate_table_object_list.php` | unchanged apart from the bootstrap include |
| `grid.js`, `grid-callback-examples.md` | untouched (Grid is parked) |

The folder keeps its name: a rename to `examples/` would have moved `grid.js`, and the user asked for the Grid
files to stay untouched.

# Tests

- `tests/GeneratorCliTest.php` (Unit, 5 tests): parsing (values, lists, `--exclude=`, short options, defaults),
  every parse error, `--help`, unknown option → 2, missing / absent / throwing bootstrap (also via
  `LAF_BOOTSTRAP`), empty package name.
- `tests/GeneratorCliIntegrationTest.php` (Integration, 4 tests, both engines): `--list` writes nothing and names
  the files, a run writes classes + page and installs the live page, a second run keeps the edited live page and
  model class while rewriting the Base class, `--force` rewrites the model class only, `--classes` leaves no
  `generator_cache/`, `--pages` with a `--labels` JSON file puts the label into the page, a missing labels file
  → 2, no matching table → 2, `--exclude=` keeps all.
- Verified by hand: `php bin/laf-generate --bootstrap=… --lib=… --tables='dummy_*,link_table' --list` and the
  real run against the MariaDB test database (4 classes, 2 pages, 2 installed, exit 0).

Unit 674 tests / 2,694 assertions; MariaDB 159 / 762; PostgreSQL 159 / 763; PHPStan level 5: 0 errors.

# For the apps

asm and routerarena keep working unchanged (their `app/util/generate_classes.php` calls `generateEverything()`
as before). After they update their LAF copy they can run `vendor/bin/laf-generate --bootstrap=data/config.php
--lib=app/lib --install-pages`; routerarena's `regenerate_single_base.php` is `--tables=router --classes`.
asm's `data/config.php` already guards its session code with `php_sapi_name() !== 'cli'`, so it works as a
bootstrap file.
