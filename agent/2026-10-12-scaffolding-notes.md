Project scaffolding and dependency update on `improvements1`, 2026-10-12: PHPStan and Rector configured (the commands CLAUDE.md advertised did nothing before), CI and Dockerfile on PHP 8.5 with a static-analysis job, a README quick start instead of one line, the sample script on the current inspector API, `psr/log` required directly, unused `phpoffice/phpspreadsheet` dropped, lock updated and `composer audit` clean.

# Static analysis

- `phpstan.neon`: level 5, `src/`, `phpVersion: 80000` (the composer floor, so 8.1+ syntax is reported), `src/Laf/UI/Grid`
  excluded until it is reviewed, cache in `var/phpstan`. `vendor/bin/phpstan analyse` → 0 errors (PHPStan 2.3.1).
  CI runs it after the unit suite.
- `rector.php`: advisory only (`vendor/bin/rector process --dry-run`): PHP 8.0 set, dead-code and code-quality
  prepared sets on `src/` and `tests/`, Grid and the fixtures skipped, `SafeDeclareStrictTypesRector` skipped
  (`declare(strict_types=1)` only goes into new files). Dry-run on 2026-10-12: 93 files, mostly
  `FlipTypeControlToUseExclusiveTypeRector` (31), `RemoveUselessReturnTagRector` (24),
  `ClassPropertyAssignToConstructorPromotionRector` (24), `RenameFunctionRector` (22, `join`/`sizeof`-style aliases),
  `RemoveUnusedVariableInCatchRector` (18). None applied: they are style suggestions to take review by review.

# CI, Docker, README, samples

- `.github/workflows/ci.yml`: unit matrix 8.1–8.5, integration on 8.1 and 8.5, new `static-analysis` job, every
  phpunit run under `php -d error_reporting=E_ALL` (the project rule). `Dockerfile`: `php:8.5-cli`.
- `README.md`: what the framework is, bootstrap, generator, records and queries, forms, routing, audit log, how to
  run the suites and the tools.
- `sample_files/test.php`: `TableInspectorFactory::create('person')` + column listing instead of
  `new MysqlTableInspector("person")` (constructor and inspect() still exist, but the factory is the engine-aware
  entry point the generators use); the grid example is untouched.
- `.gitignore`: `/var/` (tool caches).

# Dependencies (`composer.json` / `composer.lock`)

| Package | Before | After | Why |
|---|---|---|---|
| `psr/log` | transitive via monolog | `^1.1 \|\| ^2.0 \|\| ^3.0` required | `Db::setLogger()` type-hints `Psr\Log\LoggerInterface` |
| `phpoffice/phpspreadsheet` | `^1.10` (9 open advisories) | removed | not used anywhere in `src/`; asm and routerarena require it in their own composer.json |
| `monolog/monolog` | lock 1.27.1 | lock 2.11.1 | constraint unchanged (`^1.24 \|\| ^2.0 \|\| ^3.0`); `config.platform.php = 8` keeps the lock on 2.x; asm uses Monolog through LAF, so it stays required |
| `claviska/simpleimage` | lock 4.3.0 | lock 4.4.0 | PHP 8.5 `imagedestroy()` deprecation on every resize |
| `phpunit/phpunit` | 9.6.29 | 9.6.38 | advisory (PHPT code coverage deserialisation) |
| `phpstan/phpstan`, `rector/rector` | 2.1.29, 2.1.7 | 2.3.1, 2.7.0 | current |

`composer audit`: no advisories. Unit suite 668 / 2,652, MariaDB integration 154 / 718, PostgreSQL 154 / 719 after
the update.

# Still open (not scaffolding)

- `improvements1` is not merged into `master`.
- The apps' modals do not use `Modal::addHiddenInput()` for the CSRF token.
- The hand-kept `tests/Fixtures/LafShell/Base/*` differ from the generator output (`tests/Fixtures/regenerate.php`).
- `src/Laf/UI/Grid` (PhpGrid, SimpleTable, grid.js) parked by the user.
