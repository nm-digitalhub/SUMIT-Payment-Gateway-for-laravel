DATABASE PORTABILITY AUDIT DIRECTIVE

(MySQL ↔ PostgreSQL Compatibility Scan)

OBJECTIVE

Perform a full static analysis scan of the codebase to detect all database-specific logic that may break PostgreSQL or MySQL compatibility.

Do NOT fix anything yet.
Do NOT modify code.
Only generate a structured compatibility report.

⸻

PHASE 1 — RAW SQL SCAN

Search entire codebase for database-specific SQL.

Scan for:

SHOW 
DESCRIBE 
information_schema
ENGINE=
UNSIGNED
ZEROFILL
AUTO_INCREMENT
CHARSET=
COLLATE=
ENUM(
SET(
->unsigned(

Also search for:

DB::statement(
DB::select(
DB::raw(
Schema::getConnection()->select(

For each occurrence produce:

• File path
• Line number
• Code snippet
• Why it is DB-specific
• Risk level (LOW / MEDIUM / HIGH)
• Which DB breaks (MySQL / PostgreSQL / Both)

⸻

PHASE 2 — MIGRATION STRUCTURE AUDIT

Scan all migrations and detect:
	1.	Usage of:
	•	unsigned()
	•	tinyInteger() used as boolean
	•	enum() columns
	•	json vs jsonb usage
	•	timestamp without timezone assumptions
	•	bigIncrements vs id()
	2.	Foreign key definitions:
	•	Are they using constrained() properly?
	•	Are cascade behaviors DB-safe?
	3.	Index checks:
	•	Any conditional index creation using raw SQL?
	4.	Default values:
	•	MySQL-specific CURRENT_TIMESTAMP behaviors

For each issue report:

• Migration file
• Line
• Problem
• Cross-DB impact
• Recommended portable alternative (brief description only)

⸻

PHASE 3 — SCHEMA INTROSPECTION AUDIT

Detect usage of:

SHOW INDEX
pg_indexes
information_schema.tables

If introspection logic exists:

• Mark as NON-PORTABLE
• Classify as: Replace with Schema Builder / Replace with Doctrine DBAL

⸻

PHASE 4 — DATA TYPE PORTABILITY CHECK

Scan Models & Migrations for:

• unsignedBigInteger
• mediumText
• longText
• tinyInteger
• enum

Explain whether PostgreSQL supports equivalent type automatically via Laravel abstraction.

⸻

PHASE 5 — PACKAGE-LEVEL RISK SUMMARY

Produce a summary table:

Category | Count Found | Severity | Action Required
Raw SQL | X | HIGH | Rewrite
Unsigned Columns | X | MEDIUM | Remove unsigned()
Enum Columns | X | MEDIUM | Consider string + validation
Index Introspection | X | HIGH | Replace with Schema Builder
MySQL-only Defaults | X | MEDIUM | Normalize

⸻

OUTPUT FORMAT (STRICT)

Generate:

1️⃣ Detailed Findings Table
2️⃣ Migration-Specific Risk List
3️⃣ Overall Portability Score (0–100)
4️⃣ Fix Strategy Recommendation (High-level only)

Do NOT fix anything yet.
Do NOT change vendor code.
Only generate audit report.

⸻

IMPORTANT RULES

• Assume package must support BOTH MySQL and PostgreSQL
• Assume Laravel 12+
• Assume strict PostgreSQL 16
• No DB-specific raw SQL allowed
• All migrations must be dialect-neutral

DELIVERABLE (MANDATORY)
	1.	Write the full audit report to a Markdown file at:

@DB_PORTABILITY_AUDIT_REPORT.md
	2.	The file must include these sections in this exact order:

	•	Executive Summary
	•	Phase 1 — Raw SQL Findings (table)
	•	Phase 2 — Migration Findings (table + per-migration notes)
	•	Phase 3 — Schema Introspection Findings
	•	Phase 4 — Data Type Portability Findings
	•	Phase 5 — Risk Summary Table
	•	Portability Score (0–100) + scoring rationale
	•	High-level Fix Strategy (no code changes)

	3.	Do not print the entire report in chat.
Only print:

	•	The file path saved
	•	A 10–20 line excerpt (top of the file)

	4.	Do NOT modify any code. Do NOT run migrations. No vendor changes.
