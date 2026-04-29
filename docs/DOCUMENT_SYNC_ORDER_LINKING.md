# Document Sync and Order Linking (Host Responsibility)

The package syncs documents from SUMIT into `officeguy_documents` and associates them with the customer via `customer_id` (SUMIT Customer ID). It does **not** link documents to your host orders. Order linking is the host application’s responsibility.

## If You Want to Link Documents to Orders

1. **Listen to `DocumentSynced`.**  
   The package dispatches `OfficeGuy\LaravelSumitGateway\Events\DocumentSynced` once per document after it is created or updated during sync.

2. **Use the event payload.**  
   The event provides:
   - `$event->document` — `OfficeGuyDocument` instance (with `external_reference`, `document_number`, `customer_id`, etc.)
   - `$event->sumitPayload` — raw SUMIT document array

3. **Resolve your order in your domain.**  
   Use your own schema and models (e.g. find order by customer and external reference).

4. **Attach order to the document.**  
   Set `order_id` and `order_type` on the document and save.

5. **Persist.**  
   Call `$document->save()` (or use your own persistence) so the link is stored.

## Example (host application code only)

This code must **not** exist inside the package. Register it in your app (e.g. `AppServiceProvider` or an event listener class):

```php
use Illuminate\Support\Facades\Event;
use OfficeGuy\LaravelSumitGateway\Events\DocumentSynced;

Event::listen(DocumentSynced::class, function (DocumentSynced $event): void {
    $document = $event->document;

    // Resolve your customer (e.g. by SUMIT customer_id) then find order by your schema
    $order = \App\Models\Order::where('client_id', $document->customer_id)
        ->where('order_number', $document->external_reference)
        ->first();

    if ($order) {
        $document->order_id = $order->id;
        $document->order_type = $order::class;
        $document->save();
    }
});
```

Adjust the query to your schema (e.g. if `customer_id` on the document is SUMIT’s ID, resolve your customer first and use your customer’s primary key in the order query).

## References

- **Phase 3 directive:** `ORDER_LINKING_EXTRACTION_DIRECTIVE.md`
- **Event class:** `OfficeGuy\LaravelSumitGateway\Events\DocumentSynced`
- **Sync entry point:** `DocumentService::syncForClient(HasSumitCustomer $customer, ...)`
