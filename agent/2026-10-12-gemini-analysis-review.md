Fact-check of agent/2026-10-12-gemini-analysis.md (Gemini's framework audit): the report predates the Grid rewrite, its Phase 1 is already done, several claims are wrong, and five of its ideas are worth keeping.

# Context

The report was committed at 784fc69, before the Grid rewrite (0a84963, feb14d9). Every PhpGrid finding and the
whole of Phase 1 describe code that no longer exists. Its test counts (674 / 2,694) are the pre-rewrite numbers;
today's are 712 / 2,944 unit and 179 per engine.

# Claims checked against the code

| Claim | Status |
|---|---|
| `Db` has no `getAttribute()`, so `PhpGrid::isPostgres()` always returned false | **Wrong.** `Db::getAttribute()` exists (Db.php:476, delegates to PDO). The PostgreSQL branch failed for another reason (`isTextualColumn()` ended in `&& false`, formats learned after the query), see 2026-10-12-grid-review.md finding 5. Moot since the rewrite. |
| PhpSpreadsheet crash, unquoted identifiers, `LOWER()` on integers, hard `exit;`, Grid excluded from PHPStan | **Was true, all fixed** in 0a84963 (OpenSpout only, `GridQuery` quoting, `CAST(col AS TEXT) ILIKE`, `$terminate` parameters, only `SimpleTable` excluded). |
| SQL injection through `loadConfigFromObject()` | Overstated: a developer configuration path, not request input. Quoted now anyway. |
| `Util::isJson()` should use `json_validate()` | **No such method.** `TypeJson` validates with `json_decode(..., JSON_THROW_ON_ERROR)`. The idea transfers to `TypeJson::validate()` (trivial, with a `function_exists` fallback). |
| `database.schema` should be supported | **Already exists** (Db.php:372 sets `search_path`). Qualifying table names with a schema is a real but unused gap. |
| `Document::handleRequest()` always exits; forms lack a request abstraction | **Stale.** `handleRequest($action, false)` returns; `Form::setRequest($data, $files)` exists. `UrlParser::redirect()` still exits unconditionally (true). |
| `TypeDecimal` uses BCMath | True, with a float fallback when the extension is missing. |
| Inspector parity table (MariaDB `CHECK_CONSTRAINTS`, `pg_enum`, uuid) | Accurate. |
| Delete via GET link with CSRF token | **Accurate.** The token blocks cross-site forgery and the link has a JS confirm, but a link prefetcher that follows in-page links would carry the valid token. Low risk, legitimate. |
| Metadata objects rebuilt per model instance | **Accurate.** Measured: 1,000 `DummyTable` instances (19 fields) = 41 ms, 13 KB each. Moderate, not "megabytes" for typical pages; list pages use PhpGrid (raw SQL), not models. |
| N+1 on foreign-key getters | **Accurate.** Generated `getXxxObj()` builds a new object on every call, no memoisation. |
| `Query` has no joins | True by design (single-table finder; PhpGrid and `Db::getAllAssoc()` cover multi-table reads). |
| `Field` imports form inputs, `BaseObject` imports Dropdown/Link/Form/SimpleTable | True. Splitting out a form builder is a large refactor with no application benefit. |

# Modern PHP section versus the project rules

Enums, `readonly`, typed constants and property hooks need PHP 8.1 / 8.2 / 8.3 / 8.4. LAF's composer floor is
`>=8.0` and the standing rule keeps syntax at that floor. asm requires `php >=8` (platform 8.4), routerarena
`>=8.2` (platform 8.2), so a floor of 8.2 would not break either application, but raising it is the user's
decision. `#[\Override]` is harmless on older PHP (unknown attributes are ignored unless reflected) but adds
little since the hooks are covered by tests. PHPUnit 10 needs 8.1 and 11 needs 8.2 (dev only; CI starts at
8.1), a moderate migration for no functional gain. `config.platform.php = 8` matches the floor and should stay.

# Worth doing (small, compatible, in priority order)

1. `UrlParser::redirect()` gets a `$terminate` parameter like `Document::handleRequest()`.
2. Memoise the object returned by generated `getXxxObj()` per instance (invalidate when the key changes); a
   `Query::with()` eager load is the bigger follow-up if a real page needs it.
3. `PageGenerator`: delete through a POST form (CSRF token as hidden input) instead of a GET link; generated
   pages only, existing pages unchanged.
4. `TypeJson::validate()` via `json_validate()` when available.
5. Optional `form.scramble_field_names` setting (default true; the signed field list and markers follow it).

# Skip

Schema flyweight (big refactor of every generated Base class for a moderate gain), PSR-7/PSR-11 and worker
runtimes (no such deployment), joins on `Query`, the UI/Database split, the Grid items (done), PHPUnit upgrade.
