Typing pass over `src/Laf/UI/Table` (Table, Tr, Cell, Td, Th) on `improvements1`, 2026-10-12: typed properties, `static` return types on every fluent setter, `Cell::getData(): ?string` / `setData(?string)`, `getTagName()`, docblocks; the markup is unchanged (the 101 existing table tests still pass), 3 new tests, PHPStan level 5 clean.

# What changed

- 32 untyped properties typed (`?Table`, `Tr`, `int`, `bool`, `string`, `array` with element docblocks).
- Fluent setters return `static` instead of `Table` / `Tr` / `Cell`, so a `Th` chain stays a `Th`; `Table::fromArray()`
  keeps returning `Table` (it constructs a `Table`). Nothing in asm, routerarena or LAF extends these classes, so no
  override can conflict with the narrower return type.
- `Cell::getData(): ?string`, `setData(?string $data)` (an int still coerces in the apps' non-strict files; an array is
  now a `TypeError` instead of "Array" with a warning), `Cell::getTagName()`, `Cell::$tagName` typed in `Cell` and `Th`.
- `Tr`: the empty constructor is gone (`new Tr()` is unchanged); `Td` / `Th` got docblocks.

# Verification

- `tests/UiTableReviewTest.php` (3 tests): subclass-preserving chains and the tag names, nullable data, typed state and
  the generated ids.
- `--filter Table` (TableTest, TableComponentTest, KeysAndTableTest, UiTableReviewTest): 104 tests / 402 assertions;
  Unit suite 668 / 2,652; PHPStan level 5 clean on `src/Laf/UI/Table`.
