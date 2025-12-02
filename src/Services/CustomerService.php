<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;

/**
 * Customer Service
 *
 * Wraps SUMIT accounting/customer endpoints to create or update customers
 * from local Client/CrmEntity data.
 */
class CustomerService
{
    /**
     * Create or update a SUMIT customer based on local CrmEntity data.
     * Returns array with success flag and sumit_customer_id if available.
     */
    public static function syncFromEntity(CrmEntity $entity): array
    {
        $payload = self::buildPayloadFromEntity($entity);

        // decide endpoint: create if no sumit_entity_id, else update
        $endpoint = $entity->sumit_entity_id ? '/accounting/customers/update/' : '/accounting/customers/create/';

        $response = OfficeGuyApi::post(
            $payload,
            $endpoint,
            config('officeguy.environment', 'www'),
            false
        );

        if ($response === null || ($response['Status'] ?? 1) !== 0) {
            return [
                'success' => false,
                'error' => $response['UserErrorMessage'] ?? 'Failed to sync customer with SUMIT',
            ];
        }

        $sumitId = $response['Data']['CustomerID'] ?? $entity->sumit_entity_id ?? null;

        if ($sumitId) {
            $entity->updateQuietly(['sumit_entity_id' => $sumitId]);
        }

        // משיכת פרטים עדכניים מ-SUMIT כדי להשלים שדות חסרים (אינו חוסם הצלחה)
        if ($sumitId) {
            self::pullCustomerDetails($sumitId, $entity);

            if ($entity->client) {
                // סנכרון מסמכים עבור הלקוח לאחר עדכון פרטי SUMIT
                DocumentService::syncForClient($entity->client);
            }
        }

        return [
            'success' => true,
            'sumit_customer_id' => $sumitId,
        ];
    }

    /**
     * Pull SUMIT customer details by CustomerID and update local CrmEntity/Client.
     */
    public static function pullCustomerDetails(int $sumitCustomerId, ?CrmEntity $entity = null): array
    {
        $payload = [
            'Credentials' => PaymentService::getCredentials(),
            'Customer' => [
                'ID' => $sumitCustomerId,
            ],
        ];

        $response = OfficeGuyApi::post(
            $payload,
            '/accounting/customers/getdetailsurl/',
            config('officeguy.environment', 'www'),
            false
        );

        if ($response === null || ($response['Status'] ?? 1) !== 0) {
            return [
                'success' => false,
                'error' => $response['UserErrorMessage'] ?? 'Failed to pull customer details from SUMIT',
            ];
        }

        $details = $response['Data']['Customer'] ?? null;

        if ($entity && $details) {
            $entity->updateQuietly([
                'name' => $entity->name ?: ($details['Name'] ?? null),
                'email' => $entity->email ?: ($details['EmailAddress'] ?? null),
                'phone' => $entity->phone ?: ($details['Phone'] ?? null),
                'address' => $entity->address ?: ($details['Address'] ?? null),
                'city' => $entity->city ?: ($details['City'] ?? null),
                'postal_code' => $entity->postal_code ?: ($details['ZipCode'] ?? null),
                'tax_id' => $entity->tax_id ?: ($details['CompanyNumber'] ?? null),
                'sumit_entity_id' => $entity->sumit_entity_id ?: ($details['ID'] ?? null),
            ]);

            if ($entity->client) {
                $entity->client->updateQuietly([
                    'name' => $entity->client->name ?: ($details['Name'] ?? null),
                    'email' => $entity->client->email ?: ($details['EmailAddress'] ?? null),
                    'phone' => $entity->client->phone ?: ($details['Phone'] ?? null),
                    'vat_number' => $entity->client->vat_number ?: ($details['CompanyNumber'] ?? null),
                    'client_address' => $entity->client->client_address ?: ($details['Address'] ?? null),
                    'client_city' => $entity->client->client_city ?: ($details['City'] ?? null),
                    'client_postal_code' => $entity->client->client_postal_code ?: ($details['ZipCode'] ?? null),
                    'sumit_customer_id' => $entity->client->sumit_customer_id ?: ($details['ID'] ?? null),
                ]);
            }
        }

        return [
            'success' => true,
            'customer' => $details,
        ];
    }

    /**
     * Build SUMIT customer payload from local entity fields.
     */
    protected static function buildPayloadFromEntity(CrmEntity $entity): array
    {
        $fields = $entity->raw_data ? json_decode($entity->raw_data, true) ?? [] : [];

        $details = [
            'ID' => $entity->sumit_entity_id,
            'Folder' => $entity->folder?->sumit_folder_id,
            'Name' => $entity->name,
            'Phone' => $entity->phone ?? $entity->mobile,
            'EmailAddress' => $entity->email,
            'Address' => $entity->address,
            'City' => $entity->city,
            'ZipCode' => $entity->postal_code,
            'CompanyNumber' => $entity->tax_id,
            'Properties' => $fields['Properties'] ?? null,
        ];

        if ($entity->client) {
            $details['Name'] = $details['Name'] ?? $entity->client->company ?? $entity->client->name ?? $entity->client->client_name;
            $details['EmailAddress'] = $details['EmailAddress'] ?? $entity->client->email ?? $entity->client->client_email;
            $details['Phone'] = $details['Phone'] ?? $entity->client->phone ?? $entity->client->client_phone ?? $entity->client->mobile_phone;
            $details['CompanyNumber'] = $details['CompanyNumber'] ?? $entity->client->vat_number;
            $details['Address'] = $details['Address'] ?? $entity->client->client_address;
            $details['City'] = $details['City'] ?? $entity->client->client_city;
            $details['ZipCode'] = $details['ZipCode'] ?? $entity->client->client_postal_code;
        }

        return [
            'Credentials' => PaymentService::getCredentials(),
            'Details' => $details,
            'ResponseLanguage' => null,
        ];
    }
}
