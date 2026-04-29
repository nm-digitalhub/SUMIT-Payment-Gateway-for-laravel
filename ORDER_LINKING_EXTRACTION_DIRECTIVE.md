PHASE 3 — ORDER LINKING EXTRACTION DIRECTIVE

Move Order Linking Out of Core and Into Event Layer
Implementation Phase — Strict Boundary Enforcement

⸻

Objective

Remove all Order / Payable model lookup logic from DocumentService.

The package must:
	•	Not query host models.
	•	Not assume column names.
	•	Not assume order schema.
	•	Not assume payable types.
	•	Not require column maps.
	•	Not resolve host models from container.

Order linking becomes a host responsibility via event listening.

⸻

Architectural Principle

The package owns:
	•	SUMIT document synchronization
	•	Storage in officeguy_documents
	•	Association to Customer only
	•	Emission of domain events

The host owns:
	•	Order lookup
	•	Payable schema
	•	Column names
	•	Business fulfillment logic

⸻

SECTION 1 — Remove Order Lookup from DocumentService

Identify and Remove

In syncForClient:

Remove all logic that:
	•	Uses Order::where(...)
	•	References client_id
	•	References order_number
	•	References Order::class
	•	Sets $document->order_id
	•	Sets $document->order_type

After removal:

syncForClient must:
	1.	Fetch documents from SUMIT.
	2.	Create or update OfficeGuyDocument.
	3.	Associate document with Customer only.
	4.	Persist.
	5.	Emit event.
	6.	Return sync count.

No host model queries remain.

⸻

SECTION 2 — Introduce DocumentSynced Event

Create a single event:

DocumentSynced

Payload must contain:
	•	OfficeGuyDocument $document
	•	array $sumitPayload

No host types allowed in event.

Event must be dispatched:
	•	Once per document created or updated.
	•	After document is saved.
	•	Inside syncForClient.

⸻

SECTION 3 — Remove Payable Column Map Design

Delete from design:
	•	Any payable_model_class config
	•	Any customer FK column config
	•	Any external reference column config
	•	Any “column map” logic

The package must not need to know:
	•	What table Orders live in
	•	What column stores customer ID
	•	What column stores external reference

⸻

SECTION 4 — Host Responsibility Documentation

Add documentation section:

If You Want to Link Documents to Orders
	1.	Listen to DocumentSynced.
	2.	Extract:
	•	$document->external_reference
	•	$document->document_number
	•	$document->customer_id
	3.	Perform your own domain lookup.
	4.	Attach order to document.
	5.	Save changes.

Example (documentation only):

Event::listen(DocumentSynced::class, function ($event) {
    $document = $event->document;

    $order = Order::where('client_id', $document->customer_id)
        ->where('order_number', $document->external_reference)
        ->first();

    if ($order) {
        $document->order_id = $order->id;
        $document->order_type = Order::class;
        $document->save();
    }
});

This code must NOT exist inside the package.

⸻

SECTION 5 — Boundary Verification Checklist

After implementation, verify:
	•	No App\Models\* remains in DocumentService.
	•	No static queries on host models.
	•	No hardcoded table names.
	•	No hardcoded morph types.
	•	No column maps.
	•	No container resolution of host models.
	•	Only OfficeGuyDocument and package models are referenced.

⸻

SECTION 6 — Expected Result

After this phase:
	•	DocumentService becomes domain-agnostic.
	•	Order is no longer a package concern.
	•	Package becomes globally installable.
	•	No schema assumptions remain.
	•	No model assumptions remain.
	•	No host job assumptions remain.

Architectural Integrity Score expected: 90–95.

⸻

Final Constraint

Do NOT introduce:
	•	Repositories
	•	Bridges
	•	Factories
	•	Abstraction layers
	•	Optional fallback logic
	•	Column mapping systems

Only:
	•	Remove order-linking logic.
	•	Emit one event.

Minimalism is mandatory.

⸻

This phase completes the decoupling of DocumentService from host domain assumptions.