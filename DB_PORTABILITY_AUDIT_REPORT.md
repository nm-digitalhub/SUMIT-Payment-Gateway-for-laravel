# Database Portability Audit Report (Compact)

**Scope:** MySQL ↔ PostgreSQL | Laravel 12+ | Package: `officeguy/laravel-sumit-gateway`  
**Scanned:** `src/`, `database/migrations/`, `config/`, `routes/` (excl. vendor, storage, node_modules)

---

## 1. Executive Summary

The codebase has **targeted but high-impact** MySQL-specific logic that will fail on PostgreSQL. **Two migration files** drive most of the risk.

**Critical:** `database/migrations/2025_11_30_120000_add_subscription_support_to_documents_table.php` uses **SHOW INDEX**, **information_schema.KEY_COLUMN_USAGE**, **DATABASE()**, and raw **ALTER TABLE … ADD INDEX / DROP INDEX**. All of these are MySQL-only; PostgreSQL uses `pg_indexes` and different DDL. **Index and FK checks in that migration are non-portable.**

**High:** `database/migrations/2025_12_26_000001_add_transaction_linking_fields_to_officeguy_transactions.php` uses **DB::statement()** for **CREATE INDEX IF NOT EXISTS** and **DROP INDEX IF EXISTS**. The CREATE is valid on both; **DROP INDEX** uses MySQL syntax (`DROP INDEX … ON table`). On PostgreSQL the correct form is `DROP INDEX IF EXISTS index_name` (no `ON table`), so the down() migration will fail on PostgreSQL.

**Medium:** **->after('column')** appears in **15+** migration files. Laravel implements this with MySQL’s `AFTER`; PostgreSQL does not support column position, so these migrations may throw or be ignored depending on driver. Behavior is MySQL-specific.

**Low:** **DB::raw('attempts + 1')** in `src/Jobs/CheckSumitDebtJob.php` is a simple increment; supported on both MySQL and PostgreSQL. **unsignedBigInteger** and **json** in migrations are translated by Laravel’s schema builder to appropriate types on both engines; no change required for portability. **Schema::hasTable()** in `src/` is portable.

**Recommendation:** Replace all raw index/FK introspection and DDL in the two migrations above with Schema Builder (e.g. **$table->index()**, **$table->foreign()**) and avoid **->after()** where cross-DB support is required. Then re-run migrations on PostgreSQL to validate.

---

## 2. Raw SQL Findings (Grouped)

| Issue | Location (examples) | Count | Risk | Breaks |
|-------|----------------------|-------|------|--------|
| **SHOW INDEX** | `2025_11_30_120000_...documents_table.php` (L86, L145) | 2 | HIGH | PostgreSQL |
| **information_schema** + **DATABASE()** | Same file (L95–101, L122–128) | 2 | HIGH | PostgreSQL |
| **DB::statement ALTER TABLE ADD/DROP INDEX** | Same file (L88, L147) | 2 | HIGH | PostgreSQL |
| **DB::statement CREATE/DROP INDEX** | `2025_12_26_000001_...linking_fields...php` (L34–35, L44–45) | 4 | HIGH (down) | PostgreSQL (DROP syntax) |
| **DB::raw('attempts + 1')** | `src/Jobs/CheckSumitDebtJob.php` (L111) | 1 | LOW | Neither |

**Snippets (max 5):**

1. `SHOW INDEX FROM officeguy_documents WHERE Key_name = ?`  
2. `FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() ...`  
3. `ALTER TABLE officeguy_documents ADD INDEX ...` / `DROP INDEX ...`  
4. `CREATE INDEX IF NOT EXISTS idx_transaction_type ON officeguy_transactions(...)`  
5. `DROP INDEX IF EXISTS idx_transaction_type ON officeguy_transactions` (invalid on PostgreSQL)

---

## 3. Migration Structural Issues (Grouped)

| Issue | Files (examples) | Count | Impact |
|-------|-------------------|-------|--------|
| **->after('column')** | `2025_11_30_120000_...`, `2025_01_01_000007_...`, `2025_12_26_000001_...`, `2025_12_29_020000_...`, `2025_12_07_000001_...` | 15+ | MEDIUM: MySQL-only column order |
| **unsignedBigInteger** | Multiple create/add migrations | 20+ | LOW: Laravel maps to BIGINT on PG |
| **json** columns | Webhook, documents, subscriptions, settings, etc. | 15+ | LOW: Laravel abstracts json/jsonb |
| **timestamp** / **timestamps()** | All migrations with dates | Many | LOW: Portable via Laravel |
| **Foreign keys** | constrained(), onDelete('set null'|'cascade') | Many | OK: Portable |
| **constrained('clients')** | `add_client_id_to_officeguy_crm_*.php` | 3 | LOW: App table name; ensure exists on PG |

No **enum()** columns. No raw conditional index creation outside the two files above.

---

## 4. Data Type Risks (Summary)

- **unsignedBigInteger:** Used for FKs and IDs. Laravel uses BIGINT (no unsigned on PostgreSQL). **Portable.**  
- **json:** Used throughout. Laravel uses `json` (PG often stores as jsonb). **Portable.**  
- **timestamp / timestamps():** No timezone assumptions in schema; **portable.**  
- **tinyInteger / mediumText / longText / enum:** Not used in scanned migrations or package models. **No extra risk.**

---

## 5. Portability Score: **58 / 100**

| Criterion | Weight | Score | Note |
|-----------|--------|-------|------|
| No MySQL-only raw SQL | 30% | 0 | SHOW INDEX, information_schema, ALTER INDEX, DROP ON table |
| Dialect-neutral migrations | 25% | 40 | ->after() and raw index DDL |
| Data types | 20% | 100 | Laravel abstractions used |
| Introspection | 15% | 0 | information_schema in migration |
| Defaults / FK / indexes | 10% | 80 | useCurrent/constrained fine; raw index bad |

**Rationale:** Two migrations contain MySQL-only introspection and DDL, and many migrations use **->after()**. Fixing the two critical migrations and optionally removing **->after()** would raise the score into the 85–95 range.

---

## 6. Fix Strategy (High-Level)

1. **Replace index/FK logic in `2025_11_30_120000_add_subscription_support_to_documents_table.php`**  
   Use **Blueprint::index()** / **dropIndex()** and **foreign()** / **dropForeign()** with **Schema::hasTable** / **Schema::hasColumn** only. Remove SHOW INDEX, information_schema, DATABASE(), and raw ALTER TABLE.

2. **Replace raw index in `2025_12_26_000001_add_transaction_linking_fields_to_officeguy_transactions.php`**  
   Use **$table->index('transaction_type')** (and equivalent for payment_token) in up(), and **$table->dropIndex(...)** in down(). Avoid **DB::statement** for index create/drop.

3. **Optional:** Remove **->after()** from all migrations for strict PostgreSQL compatibility (column order not guaranteed; some drivers ignore it).

4. **Leave unchanged:** DB::raw('attempts + 1'), unsignedBigInteger, json, timestamp, Schema::hasTable in src/, and existing foreign key definitions.

5. **Validate:** Run full migration cycle on PostgreSQL 16 and re-run this audit.

---

*Report generated for static analysis. No code or vendor files were modified.*
