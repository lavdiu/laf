Leftovers after the LAF modernisation pass (improvements1, 2026-10-12): stale CLAUDE.md references fixed, an obsolete TODO removed, the CI PHPStan job moved to PHP 8.5, the LafShell Base fixtures replaced by generator output (regenerate.php now covers the document table), Document::uploadMany() added for multi-file fields, the CSRF gap in asm and routerarena verified to be no gap, and the stale remote branches assessed (two are fully merged and can be deleted, two carry unique commits); the branch is still not merged into master and UI/Grid stays parked, both on the user's instruction.

# Housekeeping

| Item | Change |
|---|---|
| `CLAUDE.md` Code Generation | named a script that does not exist in this repo; now says to copy `sample_files/class_generator.php` into the application (asm and routerarena keep it as `app/util/generate_classes.php`) |
| `CLAUDE.md` Example Application | pointed at a missing `1-example/`; now lists `sample_files/`, `examples/audit_logging_example.php` and the gitignored `sample_project/` copy of ASM |
| `CLAUDE.md` Testing Notes | fixtures: Base classes are generator output, leaf classes hand-kept |
| `src/Laf/Database/Field/Field.php` | `#@TODO sanitizeHTML` comment in `setValueHTML()` removed: values are stored verbatim and escaped at output since 2026-10-11 |
| `.github/workflows/ci.yml` | the static-analysis job ran PHPStan on PHP 8.4 while every other job is on 8.5; now 8.5 |

# Fixture drift (resolved)

`tests/Fixtures/regenerate.php` now also generates the `document` table (skips tables that do not exist). The four
`tests/Fixtures/LafShell/Base/*.php` files are the MariaDB generator output copied verbatim (full docblocks,
`*Fld()` / `*FormElement()` accessors, `getParentIdObj()` etc.); the leaf classes keep their hand-written
`returnLeafClass()` / `findOne()` / `find()`. Unit suite 669 tests / 2,660 assertions, MariaDB integration
155 / 722, PostgreSQL 155 / 723, PHPStan 0.

# Document multi-file uploads

`Document::uploadMany(string $field, ?array $files = null): int[]` reshapes a `name[]` entry of `$_FILES` into
one slot per file and calls `upload()` for each; unusable slots are skipped with `upload()`'s rules, a
single-file entry is accepted, a storage failure throws after the earlier files were stored. routerarena's
article and router pages reshape the arrays themselves today and can switch when they want. Encrypted documents
(`encrypt_key`) stay as they were: nothing in LAF or the apps encrypts, so there is no algorithm to decrypt with.

# CSRF in asm and routerarena: verified, no gap

`Form::processForm()` requires the token only when the form was drawn by `Form::draw()` or the markup carries
`_csrf_token_{form}`. Checked today:

- asm: 133 `processForm()` call sites, all on forms drawn through `Form::draw()` (page components), which writes the
  token and starts the session. The one file with a hand-written `<form>` next to `processForm()`
  (`app/pages/cashbalance.page`) uses it for a GET filter. The modals (`GrupPresentation`, `person.page`,
  `inventarizimi`) post to hand-written `$_POST` handlers, not to `processForm()`.
- routerarena: 125 call sites; the modals in `Router.php` / `Article.php` embed a Laf `Form` (`$element->getForm()`
  drawn inside the modal), so the token is present and the page's `processForm()` for the same table accepts it.
  The hand-written `<form>` in `router.page` (`add_photos_by_url`) has its own handler.

So neither app needs `addHiddenInput()` for its modals. The memory entry that listed this as open is corrected.

# Remote branches

| Branch | State | Action |
|---|---|---|
| `origin/custom` (+ local `custom`) | 0 commits not in master (last 2026-02-13 "Grid enhancements") | safe to delete; not done, the auto-mode permission classifier refused the push |
| `origin/grid_features` | 0 commits not in master (last 2025-08-17) | safe to delete; not done, same reason |
| `origin/generator` | 1 commit (88e4fc7 "update", 2025-11-10) not in master or improvements1: a DI container under `src/Laf/Core/`, nine planning markdown files, `generate.php` | kept, the user decides |
| `origin/qb` | 1 commit (b0185ad "test", 2025-07-18): 239 lines in `BaseObject.php` / `Query.php` from the QueryBuilder experiment | kept, the user decides |

To delete the two merged branches by hand (nothing is lost, every commit is in master):

```
git push origin --delete custom grid_features
git branch -d custom
```

`improvements1-backup-20261012` is the local safety copy from the history rewrite of 2026-10-12 and is left alone.

# Still open on purpose

- `improvements1` is not merged into `master` (user: "do not merge any branches"). asm requires
  `lavdiu/laf: dev-improvements1`, routerarena `dev-master`, so the merge changes what routerarena gets.
- `src/Laf/UI/Grid` is untouched and excluded from PHPStan (user: "ignore grid for now again").
- Rector's dry-run suggestions (advisory) are not applied.
