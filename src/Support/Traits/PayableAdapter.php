<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support\Traits;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;

/**
 * Trait PayableAdapter
 *
 * מספק מימוש ברירת מחדל ל-Payable על בסיס שדות נפוצים במודל הזמנה.
 * המודל צריך להכיל שדות: id,total,currency,email,phone,first_name,last_name,company,address,city,state,country,zip,customer_id,customer_note
 * וכן יחסים או אוספים: items (name, sku, quantity, unit_price, product_id, variation_id), fees (name, amount)
 */
trait PayableAdapter
{
    public function getPayableId(): string | int
    {
        return $this->id;
    }

    public function getPayableAmount(): float
    {
        return (float) ($this->total ?? 0);
    }

    public function getPayableCurrency(): string
    {
        return $this->currency ?? 'ILS';
    }

    public function getCustomerEmail(): ?string
    {
        return $this->email ?? null;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->phone ?? null;
    }

    public function getCustomerName(): string
    {
        $first = $this->first_name ?? '';
        $last = $this->last_name ?? '';
        $full = trim($first . ' ' . $last);

        return $full === '' ? ($this->company ?? '') : $full;
    }

    public function getCustomerAddress(): ?array
    {
        return [
            'address' => $this->address ?? '',
            'address2' => $this->address2 ?? null,
            'city' => $this->city ?? '',
            'state' => $this->state ?? null,
            'country' => $this->country ?? '',
            'zip_code' => $this->zip ?? '',
        ];
    }

    public function getCustomerCompany(): ?string
    {
        return $this->company ?? null;
    }

    public function getCustomerId(): string | int | null
    {
        return $this->customer_id ?? null;
    }

    public function getLineItems(): array
    {
        if (! method_exists($this, 'items') || $this->items === null) {
            return [];
        }

        return collect($this->items)->map(fn ($item): array => [
            'name' => $item->name ?? '',
            'sku' => $item->sku ?? null,
            'quantity' => (float) ($item->quantity ?? 0),
            'unit_price' => (float) ($item->unit_price ?? 0),
            'product_id' => $item->product_id ?? null,
            'variation_id' => $item->variation_id ?? null,
        ])->all();
    }

    public function getShippingAmount(): float
    {
        return (float) ($this->shipping_total ?? 0);
    }

    public function getShippingMethod(): ?string
    {
        return $this->shipping_method ?? null;
    }

    public function getFees(): array
    {
        if (! method_exists($this, 'fees') || $this->fees === null) {
            return [];
        }

        return collect($this->fees)->map(fn ($fee): array => [
            'name' => $fee->name ?? '',
            'amount' => (float) ($fee->amount ?? 0),
        ])->all();
    }

    public function getVatRate(): ?float
    {
        return isset($this->vat_rate) ? (float) $this->vat_rate : null;
    }

    public function isTaxEnabled(): bool
    {
        return $this->vat_rate !== null;
    }

    public function getCustomerNote(): ?string
    {
        return $this->customer_note ?? null;
    }
}
