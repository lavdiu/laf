Notes on the 2026-10-14 pass (improvements1, uncommitted): inline "create new" for autocompletes, and multi-value pickers that exist only through a linker table (person ⇄ group through person_group), detected by the generator and shown on forms by themselves.

# What the user asked

- "Autocomplete: a 'create new' entry and multi-value selection". Decisions: create = inline quick-create (opt-in per field);
  multi-value = junction table rows; then refined: *"the 'select multiple' option only if there is a linker table between
  the two. Otherwise it should select only one ... example person, and grup have a linker as person_group"*.
- asm's real tables: `person_group(id PK auto, person_id → person, group_id → group)` and `group_role(id, role_id, group_id)`
  (`grup` is the invoice table, not the group table).

# Design

| Piece | Where | Notes |
|---|---|---|
| Linker rule | `src/Laf/Generator/JunctionDetector.php` | Moved out of PageGenerator (same rule: 2 FKs to 2 different tables, ≤ 2 data columns besides key/metadata). Adds `other_column` (for its comment) and `insertable` (every data column nullable / default / generated). |
| Definition in the Base class | `ClassGenerator::detectManyToMany()` / `generateManyToMany()` | One `$this->getTable()->addManyToMany('person_group', [...])` per linker pointing at the table, in both sides' Base classes. Nothing emitted without linkers, so older output stays byte-identical. Other tables are inspected through `TableInspectorFactory` or `addTableInspector()`; a table that cannot be inspected is a single warning. |
| Configuration | comment of the linker column that points at the picked table | `fields.setLabel/setHint/setRequired`, `form.order/group/tab/exclude/readonly/hidden/element/create`, `form.multi:false` = no picker. Non-insertable linker = no picker + warning. |
| Metadata | `Table::addManyToMany()/hasManyToMany()/getManyToManyDefinition(s)()/removeManyToMany()` | Runtime-only knowledge of incoming FKs comes from here; Table had none before. |
| Relation | `src/Laf/Database/ManyToMany.php`, `BaseObject::manyToMany($junction)` (cached, reset on clone) | Needs the linker's generated model (`{package}\{Class}`); other side = that model's FK on the "other" column. `sync()` writes through the linker model (audit log + hooks), inside `BaseObject::transaction()`. |
| Pickers | `Input\ManyToManyCheckboxes` (extends CheckboxCollection), `Input\ManyToManyAutocomplete` (extends Autocomplete, chips), `RelationElementTrait`, `Laf\UI\Form\RelationElementInterface` | Value = keys joined with commas, so Select's multi-value machinery is reused. Default class: checkboxes, chips above `form.select_autocomplete_threshold` rows. |
| Form | `getRelationElements()`, `applySubmittedRelations()`, `validateRelations()`, `syncRelations()`, signed list `_rel_{name}`, `buildRequest()` | Row + links in one transaction only when a picker was posted (forms without pickers keep the old, transaction-less path). `update()` is a no-op on a clean row, the links still sync. |
| Layout | `FormLayout::auto()` adds definitions after the columns; `apply()` builds a picker for a relation name in any section and appends unplaced ones | Unavailable relation (no linker model) is skipped silently. |
| Single stays single | `Select::isMultiple()` false on an FK, `CheckboxCollection` draws radios on an FK | No app uses `setMultiple`/`CheckboxCollection` (grep of asm + routerarena). |
| Create | `Autocomplete::setAllowCreate()/setCreateText()/setCreateCallback()/create()`, `Form::handleLookupRequest(..., $post)` + `answerCreate()`, `getLookupStatus()` | POST + CSRF + editable element + not a VIEW form. Default: display field = text, db selection criteria copied, validate on store; exact case-insensitive match reused. Varchar keys get a UUID (`TypeVarchar::generatePrimaryKeyValue()`), so code_table creates work. |
| Script | `Input/autocomplete.js` | Multi mode (chips, Backspace, filtered results, add/remove events, `LafAutocomplete.add/remove/values`), create entry + POST + refusal row. Bug found in the browser: refocusing after a chip × reopened the list (min chars 0) over the Submit button, so a click on Submit picked a result; the refocus is now quiet. |

# Two-list picker (added the same day)

User: *"a parameter or config ... to show the select multiple + new option as two list views where we select and move
from left to right ... or remove them by moving right-to-left"*. `Input\ManyToManyDualList` (extends
ManyToManyAutocomplete for the relation binding, labels and the create request), chosen by `"element":"DualList"` (or
`TransferList`) in the linker column comment, `FormLayout::element()` or `getFormElement('DualList')`;
`FormLayout::resolveElementClass()` also tries `Input\ManyToMany{Name}`. Buttons are labelled Add / Remove (not arrows)
because the lists stack on phones. Posting = hidden inputs rebuilt from the selected list (the native selects post
nothing). Browser bug found: a double click moved every still-selected option of that list; it now moves only the
option clicked. Browser check was cut short (another user closed the shared Playwright page): moves, Add all with
the filter, keyboard and create-in-list were not all exercised in the browser; markup and posting are unit-tested.

# Verified

- Unit 776 / 3,264 (also with no database reachable, as in CI), Integration 192 on MariaDB (977) and PostgreSQL (978),
  PHPStan 0. Generated definitions identical on both engines.
- Browser (Playwright, `http://localhost/laf/sample_files/autocomplete_demo.php`): checkboxes save and reload; chips:
  search, keyboard pick, create, Backspace, × remove, save deletes only the removed link (kept row ids unchanged);
  failed submit keeps chips; refusal message ("Name must be at most 50 characters"); 375px wraps, no horizontal scroll.

# Open / not done

- asm and routerarena get pickers only when they regenerate their Base classes; then every linker table (asm:
  `person_group`, `group_role`, ...) shows on both sides' forms. Hide one with `"form":{"multi":false}` on the linker
  column or `FormLayout::exclude()`. asm's own `person_permissions.page` is untouched.
- Chip × buttons are ~16px, below the 44px touch-target rule (feedback-ui-patterns); the chip itself could become the target.
- Not done: editing linker data columns in the form, soft-deleting links, view-mode labels as links to the other record,
  the generated view page still shows the read-only junction tab next to the picker.
