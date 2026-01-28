<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OfficeGuy\LaravelSumitGateway\Models\PayableFieldMapping;

/**
 * PayableMappingService
 *
 * Service for managing Payable interface field mappings.
 * Provides CRUD operations and metadata about Payable fields.
 */
class PayableMappingService
{
    /**
     * Get field mapping for a specific model.
     *
     * @param  Model|string  $model  Model instance or class name
     * @return array|null Field mappings or null if not found
     */
    public function getMappingForModel(Model | string $model): ?array
    {
        $modelClass = is_string($model) ? $model : $model::class;

        $mapping = PayableFieldMapping::forModel($modelClass)
            ->active()
            ->first();

        return $mapping?->field_mappings;
    }

    /**
     * Create or update a field mapping.
     *
     * @param  string  $modelClass  Fully qualified model class name
     * @param  array  $fieldMappings  Mapping of Payable fields to model fields
     * @param  string|null  $label  Optional user-friendly label
     */
    public function upsertMapping(
        string $modelClass,
        array $fieldMappings,
        ?string $label = null
    ): PayableFieldMapping {
        return PayableFieldMapping::updateOrCreate(
            ['model_class' => $modelClass],
            [
                'field_mappings' => $fieldMappings,
                'label' => $label ?? class_basename($modelClass) . ' Mapping',
            ]
        );
    }

    /**
     * Delete a mapping by model class.
     *
     * @param  string  $modelClass  Fully qualified model class name
     * @return bool True if deleted, false if not found
     */
    public function deleteMapping(string $modelClass): bool
    {
        return PayableFieldMapping::forModel($modelClass)->delete() > 0;
    }

    /**
     * Get all active mappings.
     *
     * @return Collection<PayableFieldMapping>
     */
    public function getAllMappings(): Collection
    {
        return PayableFieldMapping::active()->get();
    }

    /**
     * Deactivate a mapping without deleting it.
     *
     * @param  string  $modelClass  Fully qualified model class name
     * @return bool True if deactivated, false if not found
     */
    public function deactivateMapping(string $modelClass): bool
    {
        return PayableFieldMapping::forModel($modelClass)
            ->update(['is_active' => false]) > 0;
    }

    /**
     * Activate a previously deactivated mapping.
     *
     * @param  string  $modelClass  Fully qualified model class name
     * @return bool True if activated, false if not found
     */
    public function activateMapping(string $modelClass): bool
    {
        return PayableFieldMapping::where('model_class', $modelClass)
            ->update(['is_active' => true]) > 0;
    }

    /**
     * Get comprehensive metadata about all 16 Payable interface fields.
     *
     * Returns detailed information for each field including Hebrew/English labels,
     * method signatures, return types, whether required, and examples.
     *
     * @return array<string, array{key: string, label_he: string, label_en: string, method: string, return_type: string, required: bool, example: string, category: string, description_he: string}>
     */
    public function getPayableFields(): array
    {
        return [
            // Category: Core Payment Info
            'payable_id' => [
                'key' => 'payable_id',
                'label_he' => 'מזהה ייחודי',
                'label_en' => 'Unique ID',
                'method' => 'getPayableId()',
                'return_type' => 'string|int',
                'required' => true,
                'example' => 'id',
                'category' => 'core',
                'description_he' => 'מזהה ייחודי של הפריט הניתן לתשלום (בדרך כלל Primary Key)',
            ],
            'amount' => [
                'key' => 'amount',
                'label_he' => 'סכום לתשלום',
                'label_en' => 'Payment Amount',
                'method' => 'getPayableAmount()',
                'return_type' => 'float',
                'required' => true,
                'example' => 'final_price_ils',
                'category' => 'core',
                'description_he' => 'הסכום הכולל לחיוב (כולל מע"מ אם tax_enabled מופעל)',
            ],
            'currency' => [
                'key' => 'currency',
                'label_he' => 'מטבע',
                'label_en' => 'Currency Code',
                'method' => 'getPayableCurrency()',
                'return_type' => 'string',
                'required' => true,
                'example' => 'ILS',
                'category' => 'core',
                'description_he' => 'קוד מטבע ISO 4217 (ILS, USD, EUR וכו\')',
            ],

            // Category: Customer Information
            'customer_name' => [
                'key' => 'customer_name',
                'label_he' => 'שם לקוח',
                'label_en' => 'Customer Name',
                'method' => 'getCustomerName()',
                'return_type' => 'string',
                'required' => true,
                'example' => 'order.client.name',
                'category' => 'customer',
                'description_he' => 'שם מלא של הלקוח (שדה חובה למסמך חשבונית)',
            ],
            'customer_email' => [
                'key' => 'customer_email',
                'label_he' => 'אימייל לקוח',
                'label_en' => 'Customer Email',
                'method' => 'getCustomerEmail()',
                'return_type' => '?string',
                'required' => false,
                'example' => 'order.client.email',
                'category' => 'customer',
                'description_he' => 'כתובת אימייל לשליחת מסמכים ואישורים',
            ],
            'customer_phone' => [
                'key' => 'customer_phone',
                'label_he' => 'טלפון לקוח',
                'label_en' => 'Customer Phone',
                'method' => 'getCustomerPhone()',
                'return_type' => '?string',
                'required' => false,
                'example' => 'order.client.phone',
                'category' => 'customer',
                'description_he' => 'מספר טלפון ליצירת קשר (פורמט: +972-XX-XXX-XXXX)',
            ],
            'customer_id' => [
                'key' => 'customer_id',
                'label_he' => 'מזהה לקוח במערכת',
                'label_en' => 'Customer ID',
                'method' => 'getCustomerId()',
                'return_type' => 'string|int|null',
                'required' => false,
                'example' => 'order.client_id',
                'category' => 'customer',
                'description_he' => 'מזהה הלקוח במערכת שלך (לא SUMIT Customer ID)',
            ],
            'customer_address' => [
                'key' => 'customer_address',
                'label_he' => 'כתובת לקוח',
                'label_en' => 'Customer Address',
                'method' => 'getCustomerAddress()',
                'return_type' => '?array',
                'required' => false,
                'example' => 'order.shipping_address',
                'category' => 'customer',
                'description_he' => 'כתובת מלאה כמערך עם שדות: street, city, postal_code, country',
            ],
            'customer_company' => [
                'key' => 'customer_company',
                'label_he' => 'שם חברה',
                'label_en' => 'Company Name',
                'method' => 'getCustomerCompany()',
                'return_type' => '?string',
                'required' => false,
                'example' => 'order.client.company_name',
                'category' => 'customer',
                'description_he' => 'שם החברה (לחשבונית עסקית)',
            ],
            'customer_note' => [
                'key' => 'customer_note',
                'label_he' => 'הערת לקוח',
                'label_en' => 'Customer Note',
                'method' => 'getCustomerNote()',
                'return_type' => '?string',
                'required' => false,
                'example' => 'description',
                'category' => 'customer',
                'description_he' => 'הערה או תיאור נוסף מהלקוח',
            ],

            // Category: Order Items & Costs
            'line_items' => [
                'key' => 'line_items',
                'label_he' => 'פריטי הזמנה',
                'label_en' => 'Line Items',
                'method' => 'getLineItems()',
                'return_type' => 'array',
                'required' => true,
                'example' => '[]',
                'category' => 'items',
                'description_he' => 'מערך פריטים בהזמנה (לחשבונית מפורטת). פורמט: [{"name": "...", "quantity": 1, "price": 100}]',
            ],
            'shipping_amount' => [
                'key' => 'shipping_amount',
                'label_he' => 'עלות משלוח',
                'label_en' => 'Shipping Cost',
                'method' => 'getShippingAmount()',
                'return_type' => 'float',
                'required' => true,
                'example' => '0',
                'category' => 'items',
                'description_he' => 'עלות המשלוח (0 אם אין משלוח)',
            ],
            'shipping_method' => [
                'key' => 'shipping_method',
                'label_he' => 'שיטת משלוח',
                'label_en' => 'Shipping Method',
                'method' => 'getShippingMethod()',
                'return_type' => '?string',
                'required' => false,
                'example' => 'null',
                'category' => 'items',
                'description_he' => 'שם שיטת המשלוח (דואר רשום, שליח, איסוף עצמי)',
            ],
            'fees' => [
                'key' => 'fees',
                'label_he' => 'עמלות נוספות',
                'label_en' => 'Additional Fees',
                'method' => 'getFees()',
                'return_type' => 'array',
                'required' => true,
                'example' => '[]',
                'category' => 'items',
                'description_he' => 'מערך עמלות נוספות. פורמט: [{"type": "processing", "amount": 5}]',
            ],

            // Category: Tax
            'vat_rate' => [
                'key' => 'vat_rate',
                'label_he' => 'שיעור מע״מ',
                'label_en' => 'VAT Rate',
                'method' => 'getVatRate()',
                'return_type' => '?float',
                'required' => false,
                'example' => '0.17',
                'category' => 'tax',
                'description_he' => 'שיעור מע"מ כעשרוני (0.17 = 17%). null אם אין מע"מ',
            ],
            'tax_enabled' => [
                'key' => 'tax_enabled',
                'label_he' => 'מע״מ מופעל',
                'label_en' => 'Tax Enabled',
                'method' => 'isTaxEnabled()',
                'return_type' => 'bool',
                'required' => true,
                'example' => 'true',
                'category' => 'tax',
                'description_he' => 'האם לכלול מע"מ בחישוב (true/false)',
            ],
        ];
    }

    /**
     * Get Payable fields grouped by category.
     *
     * @return array<string, array<string, array>>
     */
    public function getPayableFieldsByCategory(): array
    {
        $allFields = $this->getPayableFields();
        $grouped = [];

        foreach ($allFields as $field) {
            $category = $field['category'];
            $grouped[$category][$field['key']] = $field;
        }

        return $grouped;
    }

    /**
     * Get only required Payable fields.
     *
     * @return array<string, array>
     */
    public function getRequiredPayableFields(): array
    {
        return array_filter(
            $this->getPayableFields(),
            fn (array $field) => $field['required']
        );
    }

    /**
     * Get only optional Payable fields.
     *
     * @return array<string, array>
     */
    public function getOptionalPayableFields(): array
    {
        return array_filter(
            $this->getPayableFields(),
            fn (array $field): bool => ! $field['required']
        );
    }
}
