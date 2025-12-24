<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support\Traits;

use Illuminate\Support\Collection;

/**
 * HasEloquentLineItems - Adaptive Trait for Eloquent Line Items Integration
 *
 * Provides bridge between Eloquent line-item relationships and SUMIT payment gateway.
 * Does NOT assume specific model names, table structures, or field names.
 *
 * ## Purpose
 * Allows models to use Eloquent relationships for line items instead of JSON fields,
 * while maintaining compatibility with SUMIT API's expected format.
 *
 * ## Usage
 * 1. Add trait to your Payable model (alongside HasPayableFields)
 * 2. Override getEloquentLineItems() to return your specific relationship
 * 3. Trait handles SUMIT API mapping automatically
 *
 * ## Example
 * ```php
 * class Order extends Model implements Payable
 * {
 *     use HasPayableFields;
 *     use HasEloquentLineItems;
 *
 *     protected function getEloquentLineItems(): Collection
 *     {
 *         return $this->relationLoaded('lines')
 *             ? $this->lines
 *             : $this->lines()->get();
 *     }
 * }
 * ```
 *
 * ## Field Mapping (Adaptive)
 * The trait maps common field patterns to SUMIT format:
 * - `name` → SUMIT 'name'
 * - `metadata.sku` or `sku` → SUMIT 'sku'
 * - `quantity` → SUMIT 'quantity' (float)
 * - `price_unit` or `unit_price` → SUMIT 'unit_price' (float)
 * - `package_id` or `product_id` → SUMIT 'product_id'
 * - `metadata.variation_id` → SUMIT 'variation_id'
 *
 * ## Design Principles
 * - ✅ NO assumptions about table names (lines, items, orderItems, etc.)
 * - ✅ NO dependencies on specific models (OrderLine, LineItem, etc.)
 * - ✅ NO coupling to domain logic
 * - ✅ Adaptive field mapping (supports multiple naming conventions)
 * - ✅ Falls back to HasPayableFields default if no Eloquent items exist
 *
 * @since 1.19.0
 * @package OfficeGuy\LaravelSumitGateway
 */
trait HasEloquentLineItems
{
    /**
     * Override this method to return your model's line items relationship.
     *
     * IMPORTANT: This method should return your specific Eloquent relationship.
     * The trait does NOT know your model structure - you tell it where to find items.
     *
     * Default implementation returns empty Collection (falls back to HasPayableFields).
     *
     * @return Collection Collection of line item models
     */
    protected function getEloquentLineItems(): Collection
    {
        return collect();
    }

    /**
     * Get line items for SUMIT payment processing.
     *
     * Overrides HasPayableFields::getLineItems() to use Eloquent relationships
     * instead of JSON fields or hardcoded method names.
     *
     * @return array<int, array<string, mixed>> Array of SUMIT-compatible line items
     */
    public function getLineItems(): array
    {
        $items = $this->getEloquentLineItems();

        // If no Eloquent items, fall back to HasPayableFields default behavior
        if ($items->isEmpty()) {
            return parent::getLineItems();
        }

        // Map Eloquent models to SUMIT API format (adaptive field mapping)
        return $items->map(function ($line) {
            return [
                'name' => $line->name,
                // Adaptive SKU: Check metadata first, then direct field, then generate from package_id
                'sku' => data_get($line, 'metadata.sku')
                    ?? $line->sku
                    ?? ($line->package_id ? "PKG-{$line->package_id}" : null),
                // Quantity (cast to float for API)
                'quantity' => (float) $line->quantity,
                // Adaptive unit_price: Support both price_unit and unit_price field names
                'unit_price' => (float) ($line->price_unit ?? $line->unit_price ?? 0),
                // Adaptive product_id: Support both package_id and product_id
                'product_id' => $line->package_id ?? $line->product_id ?? null,
                // Variation ID from metadata
                'variation_id' => data_get($line, 'metadata.variation_id'),
            ];
        })->toArray();
    }
}
