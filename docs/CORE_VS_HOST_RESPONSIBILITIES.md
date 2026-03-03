# Core vs Host Responsibilities (Phase 4.6)

## Package core (domain-agnostic)

The package provides:

- **Checkout orchestration** — Single `show` / `process` flow; host supplies Payable via resolver or config.
- **Transactions** — Storage and retrieval of payment transactions.
- **Tokens** — Saved payment methods (J2/J5).
- **Documents** — Invoice/receipt generation and sync from SUMIT.
- **Webhooks** — Incoming SUMIT webhooks, Bit webhooks, callbacks; signature validation and event dispatch.
- **Subscriptions storage** — Recurring billing records and sync with SUMIT.
- **Events** — `PayablePaid`, `DocumentSynced`, `GuestUserCreated`, `PaymentCompleted`, etc. No product-type logic inside the package.

Fulfillment and view selection are **not** implemented by type in the core; the package either dispatches a single event (`PayablePaid`) or uses a config callable.

## Host responsibilities

- **Payable fulfillment** — Listen to `PayablePaid` and run jobs/listeners (e.g. provision order, send email, update state). All product-type logic lives in the host.
- **View selection per payable** — Configure `officeguy.checkout.view_resolver` (callable `(Request, Payable) -> ?string`). If not set, the package uses a single default checkout view.
- **Checkout URLs and routing** — Host owns routing; can pass a Payable or resolver when mounting checkout routes.
- **Order linking** — Listen to `DocumentSynced` and link documents to host orders if needed.
- **Guest welcome email** — Listen to `GuestUserCreated` and send mail.
- **Optional: custom fulfillment handlers** — Host may register type-specific handlers with `FulfillmentDispatcher`; default is a single event-only handler for all types.

## Summary

| Area              | Core                            | Host                                      |
|-------------------|----------------------------------|-------------------------------------------|
| Checkout flow     | show/process, resolve Payable    | Resolver, routing, URLs                   |
| Checkout view     | Default view or view_resolver     | view_resolver callable                    |
| Fulfillment       | Dispatch PayablePaid only        | Listen to PayablePaid, run jobs           |
| Order linking     | None                            | Listen to DocumentSynced                  |
| Transactions/docs | Full                            | —                                         |
| Webhooks/subscriptions | Full                      | —                                         |
