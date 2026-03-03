PHASE 4 — Final Host Boundary Elimination

Objective:
Remove every remaining host-domain assumption across the package so it becomes domain-agnostic and publicly installable.

No new architectural layers.
No abstraction frameworks.
No overengineering.
Minimalistic decoupling only.

⸻

Areas to Address (Mandatory)
	1.	GenericFulfillmentHandler → event-based only
	2.	CrmDataService → container-only customer model
	3.	OfficeGuyTransactionPolicy → configurable auth model, no enums
	4.	CrmEntity → configurable staff model
	5.	PublicCheckoutController → config-driven payable resolution
	6.	Remove all ?? \App\Models\Client::class fallbacks
	7.	Remove all constrained('clients') in migrations
	8.	Remove any remaining hardcoded morph types or table names

⸻

Hard Rule

After completion:

Global search must return zero results for:
	•	App\Models
	•	App\Jobs
	•	App\Enums
	•	constrained(‘clients’)
	•	‘users’
	•	‘clients’
---

PHASE 4 — Final Host Boundary Elimination

Execution Directive — Sequential Implementation Required

Objective

Eliminate every remaining hard dependency on host models, host tables, host enums, host jobs, and fallback assumptions so the package becomes fully domain-agnostic and publicly installable.

No new architectural layers.
No repositories.
No bridges.
No abstractions beyond minimal config usage.

Minimalism is mandatory.

⸻

EXECUTION ORDER (Do Not Skip Order)

Implementation must follow this exact sequence to reduce regression risk.

⸻

STEP 1 — Remove Database-Level FK to clients

Files

All migrations containing:

->constrained('clients')

Required Changes
	1.	Replace FK definition with:

unsignedBigInteger('client_id')->index();

	2.	Remove constrained('clients') entirely.
	3.	Create upgrade migration:
	•	Drop existing foreign key constraint safely.
	•	Keep column intact.
	•	Add index if missing.

Verification

Search must return zero:

constrained('clients')

Production migrations must not reference host tables.

⸻

STEP 2 — GenericFulfillmentHandler → Event-Only

Current Risk
	•	instanceof \App\Models\Order
	•	\App\Jobs\ProcessPaidOrderJob

Required Changes
	1.	Remove:
	•	All App\Models\Order
	•	All App\Jobs\*
	•	Any host-type branching logic
	2.	Dispatch single event instead:

event(new PayablePaid($transaction, $payable));

	3.	Event payload:
	•	OfficeGuyTransaction
	•	Payable instance

Verification

Search must return zero:

App\Jobs
App\Models\Order


⸻

STEP 3 — CrmDataService → Container-Only Customer

Current Risk

Static:

\App\Models\Client::where(...)

Required Changes
	1.	Replace with:

$customerModel = app('officeguy.customer_model');

	2.	Use:

$customerModel::where(...)

	3.	Remove all:

?? \App\Models\Client::class

	4.	Failure mode:
	•	If container returns null → skip operation.
	•	Log warning.
	•	Do not crash.

Verification

Search must return zero:

App\Models\Client


⸻

STEP 4 — DocumentService Morph Cleanup

Current Risk
	•	'App\Models\User'
	•	->from('users')

Required Changes

Replace manual subquery with:

Subscription::whereHas('subscriber', function ($q) use ($sumitCustomerId) {
    $q->where('sumit_customer_id', $sumitCustomerId);
});

No table names.
No morph string literals.

Verification

Search must return zero in production code:

'users'
App\Models\User

(excluding documentation)

⸻

STEP 5 — OfficeGuyTransactionPolicy Decoupling

Current Risk
	•	App\Models\User
	•	App\Enums\UserRole

Required Changes
	1.	Remove type-hint to specific User class.
	2.	Remove Enum usage.
	3.	Replace with capability-based checks:

method_exists($user, 'isAdmin') && $user->isAdmin()

	4.	Do not introduce new interfaces.
	5.	Do not introduce config-based policy callables.

Keep it minimal and duck-typed.

Verification

Search must return zero:

App\Models\User
App\Enums


⸻

STEP 6 — CrmEntity Staff Model

Current Risk

Hardcoded:

\App\Models\User::class

Required Changes

Replace with:

config('officeguy.staff_model')

No fallback.
If not configured → relationship returns null.

Verification

Search must return zero:

\App\Models\User::class


⸻

STEP 7 — PublicCheckoutController Payable Resolution

Current Risk

Hardcoded:
	•	App\Models\Package
	•	App\Models\MayaNetEsimProduct
	•	Fallback Client model

Required Changes
	1.	Introduce config mapping:

'checkout_models' => [
    'package' => ...,
    'esim' => ...,
]

	2.	Resolve payable class from config.
	3.	Remove all hardcoded model class strings.
	4.	Remove fallback to App\Models\Client.

Verification

Search must return zero:

App\Models\Package
App\Models\MayaNetEsimProduct
?? \App\Models\Client::class


⸻

STEP 8 — Global Fallback Purge

Remove all occurrences of:

?? \App\Models\Client::class

Across:
	•	Controllers
	•	Models
	•	Services
	•	Webhooks
	•	Transactions
	•	CRM models

If customer model not configured:
	•	Skip operation
	•	Or return null
	•	Never fallback

⸻

FINAL BOUNDARY VALIDATION

After completing all steps:

Run global search on production code (src/, database/, config/):

Must return zero occurrences:

App\Models
App\Jobs
App\Enums
constrained('clients')

Table names such as 'users' or 'clients' must not appear in production logic.

Documentation and examples excluded.

⸻

VERSIONING

This is a breaking architectural change.

Release as:

4.0.0

Include upgrade guide:
	•	Order linking moved to event
	•	Fulfillment moved to event
	•	No default customer fallback
	•	FK removed from migrations

⸻

COMPLETION CRITERIA

Phase 4 is complete only when:
	•	Package can be installed in a fresh Laravel app without:
	•	Creating clients table
	•	Creating users table
	•	Defining App\Models\Client
	•	Defining App\Models\Order
	•	Defining App\Enums\UserRole
	•	Defining App\Jobs\ProcessPaidOrderJob

If any of the above are required, Phase 4 is not complete.

⸻

This directive finalizes the transformation into a public, domain-agnostic package.