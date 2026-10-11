# LAF Code Quality Notes

## Known Bugs
- `FormElementTrait::setAutocomplete()` — sets attribute `'attribute'` instead of `'autocomplete'`
- `Field.php` calls `Table::setDisplayFieldName()` which doesn't exist (should be `setDisplayField()`)
- ~~`BaseObject::returnLeafClass()` — potential infinite recursion~~ fixed 2026-10-11 (returns `$this`)
- ~~`checkUniqueFieldsForDuplicateValues()` — marked TODO~~ implemented 2026-10-11 (single-column unique fields checked before the statement)
- ~~`QueryBuilder::with()` / `loadRelation()`~~ QueryBuilder deleted; replaced by the single-table `Laf\Database\Query` (2026-10-11)
- `Settings::saveToFile()` / `loadFromFile()` — not implemented (stubbed)

## PHP 8+ Adoption Gaps
- No typed properties (PHP 7.4+) on most classes
- No enums — `DrawMode` and `ContainerType` should be PHP 8.1 enums
- No `match` expressions, union types, or named arguments
- Only `mixed` return type used occasionally (Db.php)
- Good use of nullable types and return types throughout

## Query (replaced QueryBuilder, 2026-10-11)
- Single table only: no joins, GROUP BY, HAVING, subqueries or eager loading (by design; add when needed)
- Aggregates: `count()` only
- Transactions: `Db::transaction()` / `BaseObject::transaction()` (nest-aware); audit rows are still a separate statement
- Quoting through `Identifier` on both engines

## Documentation Gaps
- README.md is just `# LAF` — needs content
- No CONTRIBUTING.md, CHANGELOG, or installation guide
- phpstan.neon and rector.php config files missing
- No CI/CD pipeline

## Monolog Version
- Using `^1.24` — current is v3.x, should be updated
