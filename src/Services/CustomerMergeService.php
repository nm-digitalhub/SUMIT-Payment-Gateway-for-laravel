<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Customer Merge Service
 *
 * Handles synchronization between SUMIT customers and local customer models.
 * Allows developers to connect their existing customer/user models without code changes.
 */
class CustomerMergeService
{
    public function __construct(protected SettingsService $settings) {}

    /**
     * Check if customer sync is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('customer_local_sync_enabled', false);
    }

    /**
     * Get the configured customer model class.
     *
     * Uses 3-layer resolution via container binding:
     * 1. Database: officeguy_settings.customer_model_class (Admin Panel editable)
     * 2. Config: officeguy.models.customer (new nested structure)
     * 3. Config: officeguy.customer_model_class (legacy flat structure)
     *
     * Note: Only the flat key 'customer_model_class' is database-backed.
     * The nested 'models.customer' key is config-only.
     *
     * @return string|null The customer model class name or null if not configured
     */
    public function getModelClass(): ?string
    {
        // Use container binding which handles backward compatibility
        return app('officeguy.customer_model');
    }

    /**
     * Get field mapping configuration.
     */
    public function getFieldMapping(): array
    {
        return [
            'email' => $this->settings->get('customer_field_email', 'email'),
            'name' => $this->settings->get('customer_field_name', 'name'),
            'phone' => $this->settings->get('customer_field_phone', 'phone'),
            'first_name' => $this->settings->get('customer_field_first_name'),
            'last_name' => $this->settings->get('customer_field_last_name'),
            'company' => $this->settings->get('customer_field_company'),
            'address' => $this->settings->get('customer_field_address'),
            'city' => $this->settings->get('customer_field_city'),
            'sumit_id' => $this->settings->get('customer_field_sumit_id', 'sumit_customer_id'),
        ];
    }

    /**
     * Find or create a local customer from SUMIT customer data.
     *
     * @param  array  $sumitCustomer  SUMIT customer data from webhook
     * @return Model|null The local customer model or null if sync disabled
     */
    public function syncFromSumit(array $sumitCustomer): ?Model
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $modelClass = $this->getModelClass();
        if (! $modelClass || ! class_exists($modelClass)) {
            Log::warning('CustomerMergeService: Invalid or missing customer model class', [
                'configured_class' => $modelClass,
            ]);

            return null;
        }

        $fieldMap = $this->getFieldMapping();
        $email = $sumitCustomer['Email'] ?? $sumitCustomer['email'] ?? null;
        $sumitId = $sumitCustomer['ID'] ?? $sumitCustomer['id'] ?? $sumitCustomer['CustomerID'] ?? null;

        if (! $email && ! $sumitId) {
            Log::warning('CustomerMergeService: No email or SUMIT ID in customer data');

            return null;
        }

        try {
            // Try to find existing customer
            $customer = $this->findLocalCustomer($modelClass, $fieldMap, $email, $sumitId);

            if ($customer instanceof \Illuminate\Database\Eloquent\Model) {
                // Update existing customer
                return $this->updateCustomer($customer, $sumitCustomer, $fieldMap);
            }

            // Create new customer
            return $this->createCustomer($modelClass, $sumitCustomer, $fieldMap);
        } catch (\Exception $e) {
            Log::error('CustomerMergeService: Failed to sync customer', [
                'error' => $e->getMessage(),
                'sumit_customer' => $sumitCustomer,
            ]);

            return null;
        }
    }

    /**
     * Find a local customer by email or SUMIT ID.
     */
    protected function findLocalCustomer(string $modelClass, array $fieldMap, ?string $email, $sumitId): ?Model
    {
        $query = $modelClass::query();

        // First try to find by SUMIT ID (most reliable)
        if ($sumitId && ! empty($fieldMap['sumit_id'])) {
            $customer = $query->where($fieldMap['sumit_id'], $sumitId)->first();
            if ($customer) {
                return $customer;
            }
        }

        // Then try to find by email
        if ($email && ! empty($fieldMap['email'])) {
            return $modelClass::where($fieldMap['email'], $email)->first();
        }

        return null;
    }

    /**
     * Update an existing customer with SUMIT data.
     */
    protected function updateCustomer(Model $customer, array $sumitData, array $fieldMap): Model
    {
        $updates = $this->mapSumitToLocal($sumitData, $fieldMap);

        // Don't update email if it's the unique identifier
        if (! empty($fieldMap['email']) && $customer->getAttribute($fieldMap['email'])) {
            unset($updates[$fieldMap['email']]);
        }

        if ($updates !== []) {
            $customer->update($updates);
        }

        return $customer;
    }

    /**
     * Create a new local customer from SUMIT data.
     */
    protected function createCustomer(string $modelClass, array $sumitData, array $fieldMap): Model
    {
        $data = $this->mapSumitToLocal($sumitData, $fieldMap);

        return $modelClass::create($data);
    }

    /**
     * Map SUMIT customer data to local model fields.
     */
    protected function mapSumitToLocal(array $sumitData, array $fieldMap): array
    {
        $mapped = [];

        // Map email
        if (! empty($fieldMap['email'])) {
            $email = $sumitData['Email'] ?? $sumitData['email'] ?? null;
            if ($email) {
                $mapped[$fieldMap['email']] = $email;
            }
        }

        // Map phone
        if (! empty($fieldMap['phone'])) {
            $phone = $sumitData['Phone'] ?? $sumitData['phone'] ?? $sumitData['Mobile'] ?? null;
            if ($phone) {
                $mapped[$fieldMap['phone']] = $phone;
            }
        }

        // Map name (combined or separate)
        $firstName = $sumitData['FirstName'] ?? $sumitData['first_name'] ?? '';
        $lastName = $sumitData['LastName'] ?? $sumitData['last_name'] ?? '';
        $fullName = $sumitData['Name'] ?? $sumitData['name'] ?? trim("$firstName $lastName");

        if (! empty($fieldMap['name']) && $fullName) {
            $mapped[$fieldMap['name']] = $fullName;
        }
        if (! empty($fieldMap['first_name']) && $firstName) {
            $mapped[$fieldMap['first_name']] = $firstName;
        }
        if (! empty($fieldMap['last_name']) && $lastName) {
            $mapped[$fieldMap['last_name']] = $lastName;
        }

        // Map company
        if (! empty($fieldMap['company'])) {
            $company = $sumitData['CompanyName'] ?? $sumitData['company'] ?? null;
            if ($company) {
                $mapped[$fieldMap['company']] = $company;
            }
        }

        // Map address
        if (! empty($fieldMap['address'])) {
            $address = $sumitData['Address'] ?? $sumitData['address'] ?? null;
            if ($address) {
                $mapped[$fieldMap['address']] = $address;
            }
        }

        // Map city
        if (! empty($fieldMap['city'])) {
            $city = $sumitData['City'] ?? $sumitData['city'] ?? null;
            if ($city) {
                $mapped[$fieldMap['city']] = $city;
            }
        }

        // Map SUMIT ID
        if (! empty($fieldMap['sumit_id'])) {
            $sumitId = $sumitData['ID'] ?? $sumitData['id'] ?? $sumitData['CustomerID'] ?? null;
            if ($sumitId) {
                $mapped[$fieldMap['sumit_id']] = $sumitId;
            }
        }

        return $mapped;
    }

    /**
     * Sync a local customer to SUMIT.
     *
     * @param  Model  $customer  Local customer model
     * @return array|null SUMIT API response or null on failure
     */
    public function syncToSumit(Model $customer): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $fieldMap = $this->getFieldMapping();

        // This would need to use the SUMIT API to create/update customer
        // For now, return the mapped data for the developer to use
        return $this->mapLocalToSumit($customer, $fieldMap);
    }

    /**
     * Map local customer data to SUMIT format.
     */
    protected function mapLocalToSumit(Model $customer, array $fieldMap): array
    {
        $data = [];

        if (! empty($fieldMap['email'])) {
            $data['Email'] = $customer->getAttribute($fieldMap['email']);
        }

        if (! empty($fieldMap['phone'])) {
            $data['Phone'] = $customer->getAttribute($fieldMap['phone']);
        }

        if (! empty($fieldMap['name'])) {
            $data['Name'] = $customer->getAttribute($fieldMap['name']);
        }

        if (! empty($fieldMap['first_name'])) {
            $data['FirstName'] = $customer->getAttribute($fieldMap['first_name']);
        }

        if (! empty($fieldMap['last_name'])) {
            $data['LastName'] = $customer->getAttribute($fieldMap['last_name']);
        }

        if (! empty($fieldMap['company'])) {
            $data['CompanyName'] = $customer->getAttribute($fieldMap['company']);
        }

        if (! empty($fieldMap['address'])) {
            $data['Address'] = $customer->getAttribute($fieldMap['address']);
        }

        if (! empty($fieldMap['city'])) {
            $data['City'] = $customer->getAttribute($fieldMap['city']);
        }

        if (! empty($fieldMap['sumit_id'])) {
            $sumitId = $customer->getAttribute($fieldMap['sumit_id']);
            if ($sumitId) {
                $data['ID'] = $sumitId;
            }
        }

        return $data;
    }

    /**
     * Get local customer by SUMIT ID.
     */
    public function findBySumitId($sumitId): ?Model
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $modelClass = $this->getModelClass();
        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        $fieldMap = $this->getFieldMapping();
        if (empty($fieldMap['sumit_id'])) {
            return null;
        }

        return $modelClass::where($fieldMap['sumit_id'], $sumitId)->first();
    }

    /**
     * Get local customer by email.
     */
    public function findByEmail(string $email): ?Model
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $modelClass = $this->getModelClass();
        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        $fieldMap = $this->getFieldMapping();
        if (empty($fieldMap['email'])) {
            return null;
        }

        return $modelClass::where($fieldMap['email'], $email)->first();
    }
}
