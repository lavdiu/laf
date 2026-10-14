# LAF documentation

LAF (Lulzim Application Framework) is a PHP 8 framework for database-driven web applications. You design the
tables; the generator writes one Active Record model per table (a regenerated `Base` class plus a class of your own)
and one CRUD page per table; forms, lists, grids and pages are built from the models. MySQL/MariaDB and PostgreSQL
are supported.

Every chapter uses the same four-table example, so the pieces fit together:

```
company ──< employee >──< employee_project >──< project
 (standalone)  (FK company_id)   (linker table)    (standalone)
```

- `company`: a standalone table (unique name, contact columns, soft-delete status, created/updated metadata)
- `employee`: belongs to one company through the foreign key `company_id`
- `project`: a standalone table
- `employee_project`: the linker table that ties employees and projects many-to-many

The schema is in [`examples/schema.mysql.sql`](examples/schema.mysql.sql) and
[`examples/schema.postgres.sql`](examples/schema.postgres.sql). The generator's real output for it (Base classes,
model classes and CRUD pages) is in [`examples/generated/`](examples/generated/), so you can compare what the
chapters describe with real files.

## Reading order

Start with chapters 1 to 6 in order. After that, read whichever chapters you need.

| # | Chapter | What you learn |
|---|---|---|
| 1 | [Getting started](01-getting-started.md) | Install, bootstrap and settings, create the four tables, generate, store a first record, show a first page |
| 2 | [Code generation](02-code-generation.md) | `laf-generate` and `DatabaseGenerator`, Base and model classes, JSON column comments, linker-table detection, regeneration rules |
| 3 | [Models](03-models.md) | `BaseObject`: loading, getters/setters, `store()`, deletes, state, hooks, foreign-key navigation, transactions, exceptions, metadata classes |
| 4 | [Queries](04-queries.md) | `find()`, the `Query` builder, aggregates, `paginate()`, `cursor()`, `chunk()` |
| 5 | [Validation](05-validation.md) | Field rules, `validateRecord()`, validation on store |
| 6 | [Forms](06-forms.md) | Draw modes, `FormLayout`, handling a submission and storing it, CSRF, the mass-assignment guard, `buildRequest()` |
| 7 | [Form inputs](07-form-inputs.md) | Every input element and how to choose one |
| 8 | [Relations](08-relations.md) | Foreign keys in forms, Autocomplete and "create new", many-to-many through linker tables and their pickers |
| 9 | [Lists and tables](09-lists-and-tables.md) | Lists of rows with `Laf\UI\Table`, pagination links, formatting |
| 10 | [Grids](10-grids.md) | `PhpGrid` + `grid.js`: paging, sorting, searching, totals, export |
| 11 | [Pages and UI components](11-pages-and-ui.md) | The HTML document, page cards, containers, components, flash messages |
| 12 | [Routing and generated pages](12-routing-and-generated-pages.md) | `UrlParser`, a front controller, the anatomy of a generated `.page` |
| 13 | [Database access](13-database.md) | `Db`: connections, typed queries, write helpers, transactions, logging |
| 14 | [Audit log](14-audit-log.md) | Automatic change history and how to read it |
| 15 | [Files and documents](15-files-and-documents.md) | Uploads, thumbnails and safe file serving |
| 16 | [Settings, locale and utilities](16-settings-locale-utilities.md) | `Settings`, `Locale`, `Util`, exceptions |
| 17 | [Security](17-security.md) | Escaping, CSRF, mass assignment, SQL injection, file serving |
| 18 | [Testing and tooling](18-testing-and-tooling.md) | PHPUnit suites, testing your app, PHPStan, Rector, CI |
| 19 | [Extending LAF](19-extending.md) | Custom field types, elements and components, model logging, wrapper styling, every extension point |
| 20 | [Upgrading](20-upgrading.md) | Moving an app from `master` to `improvements1`: what changed, what to check, a checklist |
| 21 | [Tutorial: a team directory](21-tutorial.md) | The guided path: from an empty folder to a working app, step by step |

New to LAF? Read chapter 1, then follow the [tutorial](21-tutorial.md), and use chapters 2–19 as the reference.
The [changelog](../CHANGELOG.md) lists what changed on the branch.

## The ideas in one page

- **Database first.** The schema is the source of truth. Column types, `NOT NULL`, unique indexes and foreign keys
  become field types, required flags, unique checks and relations in the generated code. JSON column comments add
  labels, hints and form hints (see [Code generation](02-code-generation.md#json-column-comments)).
- **Two-tier classes.** `MyApp\Base\BaseEmployee` is rewritten on every generator run, so never edit it.
  `MyApp\Employee` is created once and holds your code (hooks, validation rules, form layout).
- **Models draw their own forms.** `$employee->getForm(DrawMode::UPDATE)` returns a Bootstrap 5 form laid out from
  the model. `$form->processForm()` reads the POST, checks the CSRF token, validates the input and stores it.
- **Relations come from the schema.** A foreign key is a single-value select (or an autocomplete for big tables).
  A linker table you mark with the table comment `{"linker": true}` (or declare with
  `$this->belongsToMany('project')`) gives both sides a multi-value picker.
- **Safe by default.** Values are stored verbatim and escaped when they are output. Statements use bound
  parameters, forms and delete links carry CSRF tokens, and writes are recorded in the audit log.
- **Constraints become messages.** Required values, unique values and unique combinations are checked before the
  write. A form shows a refusal as a message on the field, never as an error page.

## Other material in the repository

- [`../CLAUDE.md`](../CLAUDE.md): a dense API summary (the reference these docs expand on)
- [`../sample_files/`](../sample_files/): bootstrap + settings templates, the grid demo, the autocomplete and
  many-to-many demo
- [`../examples/audit_logging_example.php`](../examples/audit_logging_example.php)
