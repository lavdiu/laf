Linker tables are opt-in and many-to-many relations have a fluent declaration (2026-10-14, improvements1): `{"linker": true}` table comment, generator modes marked/auto/off, `$this->belongsToMany('group')->through()->keys()->label()->chips()`.

# What the user asked

"would it be better not to autodetect linker tables, I can define them manually?" → "lets switch it to opt-in. any
better syntax to configure it? maybe $person->addManyToMany('group')->through('person_group')->(...) ... figure out a
syntax that is easy to remember and eloquent".

# Why

Autodetection is structural. asm/routerarena have 15 tables that match the rule; fewer than half are relations
(person_group, group_role, group_routing_table, article_tag, ...); the rest are logs, view counters and stock/price
tables that would have got pickers on both sides. Nothing used the pickers yet (no app regenerated since 5517ccb), so
switching the default cost nothing.

# Design

- Marker: the linker table's own comment `{"linker": true}` (`ColumnComment::linker()`; inspectors `getTableComment()`:
  MySQL information_schema.TABLES.TABLE_COMMENT, PostgreSQL obj_description(pg_class)). Not on the interface (BC).
- Modes (`ClassGenerator::MANY_TO_MANY_*`): `marked` default, `auto` (old behaviour, `{"linker": false}` opts out), `off`.
  Config key `many_to_many` > Settings `generator.many_to_many` > marked; `DatabaseGenerator::setManyToManyMode()`;
  CLI `--many-to-many=`. Unmarked matches = notices (`getNotices()`, "Note:" lines, deduped per run, shown with -q,
  not counted by --strict). A marked table failing the rule = warning.
- Syntax (`Laf\Database\ManyToManyBuilder`, `BaseObject::belongsToMany()`): Laravel-like name everyone remembers;
  `through()` / `keys()` optional (junction guessed as {a}_{b} / {b}_{a} with a model class, or an already declared
  relation for that table; keys from the linker model's foreign keys). Column mapping strings like
  'person.id:person_group.person_id' were rejected: the linker's foreign keys already say it; `keys()` only for
  ambiguity (self-links). Options: label/hint/required/max, checkboxes/chips/dualList/picker, creatable,
  group/tab/order, form([...]).
- Resolution is lazy: `Table::addManyToManyBuilder()` keeps builders; every relation read resolves them in order;
  a second declaration of the same relation merges (refines the generated one). The builder resolves against the
  table passed in, so clones work.
- Generated Base classes now write the same fluent line (`ClassGenerator::renderBelongsToMany()`) instead of
  `$this->getTable()->addManyToMany(...)`; `Table::addManyToMany()` array form kept.
- `BaseObject::manyToMany()` also takes the other table's name. `Util::pluralLabel()` shared with the generator.
  `ManyToMany` reads `max` and the aliases `chips` / `dual`.

# Verified

Unit 794 (no database), Integration 198 on MariaDB and PostgreSQL, PHPStan 0; fixtures regenerated (identical on both
engines): link_table no longer gives DummyTable/CodeTable a relation (not marked), m2m_person_group is marked.
New tests: `tests/ManyToManyBuilderTest.php`, generator tests for modes/notices/warning/rendering, an integration test
for the table comment on both engines. CLI checked against laf_docs: unmarked → Note, marked → fluent line,
auto/`{"linker": false}`, a wrongly marked table → warning, bad `--many-to-many` value → exit 2.

# For the apps

After regenerating, mark the wanted linker tables (`ALTER TABLE person_group COMMENT = '{"linker": true}'`); every
other match is a Note in the generator output. Or declare relations in the model classes with `belongsToMany()`.
