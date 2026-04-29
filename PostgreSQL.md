CONTROLLED EXECUTION DIRECTIVE — DB PORTABILITY FIX (STRICT MODE, ZERO BREAKAGE)

Goal
Make the package migrations fully MySQL ↔ PostgreSQL compatible by removing DB-specific SQL (SHOW INDEX / information_schema / raw ALTER TABLE index ops), while preserving behavior exactly (no schema changes beyond what the migration already intended).

Hard constraints (STRICT MODE)
	1.	Do NOT change any column names, types, nullability, defaults, or constraints beyond what the original migrations already do.
	2.	Do NOT change index names or foreign key names that already exist in the migration logic.
	3.	Do NOT add new migrations. Modify ONLY the problematic migrations already shipped.
	4.	Do NOT touch vendor code outside this package.
	5.	Do NOT add tests in this phase.
	6.	All changes must be done using Laravel Schema Builder (Blueprint) only. No raw SQL for DDL.
	7.	If a change cannot be expressed portably via Schema Builder without renaming/dropping objects differently than before → STOP and report.

Scope of work (only these files)
A) @database/migrations/2025_11_30_120000_add_subscription_support_to_documents_table.php
B) @database/migrations/2025_12_26_000001_add_transaction_linking_fields_to_officeguy_transactions.php

Task 0 — Safety prep (mandatory)
	1.	Ensure clean working tree:
git status
	2.	Create branch:
git checkout -b fix/db-portability-strict

Task 1 — Fix migration A (remove MySQL-only introspection + raw index DDL)
File: @database/migrations/2025_11_30_120000_add_subscription_support_to_documents_table.php
	1.	Remove ALL usage of:
	•	SHOW INDEX
	•	information_schema.*
	•	DATABASE()
	•	DB::select / DB::statement used for index existence checks
	•	raw ALTER TABLE … ADD/DROP INDEX
	2.	Replace with Schema Builder patterns ONLY:
a) Column presence checks:
	•	use Schema::hasColumn(‘officeguy_documents’, ‘subscription_id’) etc.
b) Index creation/removal:
	•	use Schema::table(‘officeguy_documents’, function (Blueprint $table) { … })
For each index that the migration originally added:
	•	create it via $table->index([…], ‘EXACT_ORIGINAL_INDEX_NAME’);
For each index the migration originally removed:
	•	remove it via $table->dropIndex(‘EXACT_ORIGINAL_INDEX_NAME’);
c) Foreign key creation/removal (if present):
	•	create via $table->foreign(‘subscription_id’, ‘EXACT_ORIGINAL_FK_NAME’)->references(‘id’)->on(‘subscriptions’)->nullOnDelete()/cascadeOnDelete() exactly as originally intended.
	•	remove via $table->dropForeign(‘EXACT_ORIGINAL_FK_NAME’);

STRICT NAME REQUIREMENT (non-negotiable)
	•	If the original migration used a custom index name (e.g. officeguy_documents_subscription_id_created_at), keep it exactly.
	•	If the original migration relied on Laravel’s default naming (no explicit name), do NOT “invent” a new name. Derive the exact default name Laravel would use for that definition and use it explicitly so behavior is deterministic across DBs and rollbacks.

Idempotency requirement (portable)
	•	Do NOT attempt DB-level introspection to see if an index exists.
	•	Use only Schema::hasColumn() (portable) as guards.
	•	If you need to guard index/foreign creation, do it with try/catch around Schema::table() and rethrow with clear message (only if absolutely necessary). Prefer deterministic operations.

Task 2 — Fix migration B (remove non-portable DROP INDEX … ON table)
File: @database/migrations/2025_12_26_000001_add_transaction_linking_fields_to_officeguy_transactions.php
	1.	Remove DB::statement-based DDL for index create/drop.
	2.	Replace with Schema Builder:

Up():
	•	Schema::table(‘officeguy_transactions’, function (Blueprint $table) {
// add columns exactly as before (no type changes)
// add indexes using $table->index([…], ‘EXACT_ORIGINAL_INDEX_NAME’);
});

Down():
	•	Schema::table(‘officeguy_transactions’, function (Blueprint $table) {
// drop indexes using $table->dropIndex(‘EXACT_ORIGINAL_INDEX_NAME’);
// drop columns exactly as before
});

Again: keep index names exactly consistent with the previous migration behavior.

Task 3 — Minimal verification (no tests)
	1.	Run syntax/lint checks:
php -l database/migrations/2025_11_30_120000_add_subscription_support_to_documents_table.php
php -l database/migrations/2025_12_26_000001_add_transaction_linking_fields_to_officeguy_transactions.php
	2.	Run package-level autoload sanity:
composer dump-autoload
	3.	Run migrations on PostgreSQL (local):
php artisan migrate –env=testing

If any migration fails:
	•	STOP
	•	Report the exact failing statement and the Laravel migration context.
	•	Do NOT apply “quick fixes” outside the two files.

Task 4 — Commit + Tag + Push (required)
	1.	Commit:
git add database/migrations/2025_11_30_120000_add_subscription_support_to_documents_table.php 
database/migrations/2025_12_26_000001_add_transaction_linking_fields_to_officeguy_transactions.php
git commit -m “Fix: make migrations portable (MySQL/PostgreSQL) without breaking changes”
	2.	Tag (choose next semantic patch version, example vX.Y.Z):
git tag -a vX.Y.Z -m “DB portability: remove MySQL-specific migration SQL; keep strict compatibility”
	3.	Push branch + tag:
git push origin fix/db-portability-strict
git push origin vX.Y.Z

Final output required (in chat)
Return:
	1.	A diff summary per file (what removed, what replaced)
	2.	Exact index/FK names preserved (list them explicitly)
	3.	Confirmation that no additional layers/services/migrations were added
	4.	Confirmation that PostgreSQL migration run now passes (or exact failure report if not)

STOP CONDITIONS
	•	If you cannot guarantee exact preservation of object names/behavior.
	•	If you discover the original migration created objects with names only known at runtime via introspection.
	•	If Schema Builder cannot express the same change portably.

If any STOP condition is hit: do not proceed—return a report explaining what cannot be made portable without a breaking change.