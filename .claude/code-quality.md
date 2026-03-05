# LAF Code Quality Notes

## Known Bugs
- `FormElementTrait::setAutocomplete()` — sets attribute `'attribute'` instead of `'autocomplete'`
- `Field.php` calls `Table::setDisplayFieldName()` which doesn't exist (should be `setDisplayField()`)
- `BaseObject::returnLeafClass()` — potential infinite recursion
- `checkUniqueFieldsForDuplicateValues()` — marked TODO, not implemented
- `QueryBuilder::with()` — eager loading completely stubbed, `loadRelation()` not implemented
- `Settings::saveToFile()` / `loadFromFile()` — not implemented (stubbed)

## PHP 8+ Adoption Gaps
- No typed properties (PHP 7.4+) on most classes
- No enums — `DrawMode` and `ContainerType` should be PHP 8.1 enums
- No `match` expressions, union types, or named arguments
- Only `mixed` return type used occasionally (Db.php)
- Good use of nullable types and return types throughout

## QueryBuilder Gaps
- No GROUP BY, HAVING, UNION
- No aggregate functions (COUNT, SUM, AVG)
- No subqueries or nested WHERE conditions
- No transaction support
- Hardcoded backticks — PostgreSQL incompatible

## Documentation Gaps
- README.md is just `# LAF` — needs content
- No CONTRIBUTING.md, CHANGELOG, or installation guide
- phpstan.neon and rector.php config files missing
- No CI/CD pipeline

## Monolog Version
- Using `^1.24` — current is v3.x, should be updated
