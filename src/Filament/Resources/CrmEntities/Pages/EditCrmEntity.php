<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Pages;

use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\CrmEntityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCrmEntity extends EditRecord
{
    protected static string $resource = CrmEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Handle data mutation before filling the form.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load custom fields from the entity
        $customFields = $this->record->customFields()->get();

        foreach ($customFields as $customField) {
            $fieldKey = "custom_field_{$customField->crm_folder_field_id}";
            $data[$fieldKey] = $customField->field_value;
        }

        return $data;
    }

    /**
     * Handle data mutation before saving the record.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract custom fields
        $customFields = [];
        $standardData = $data;

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'custom_field_')) {
                $customFields[$key] = $value;
                unset($standardData[$key]);
            }
        }

        // Store custom fields for later use in afterSave
        $this->customFields = $customFields;

        return $standardData;
    }

    /**
     * Handle actions after saving the record.
     */
    protected function afterSave(): void
    {
        // Update custom fields using the model's setCustomField method
        if (!empty($this->customFields)) {
            foreach ($this->customFields as $key => $value) {
                // Extract field ID from key: custom_field_123 -> 123
                $fieldId = (int) str_replace('custom_field_', '', $key);

                // Get the field to find its name
                $field = \OfficeGuy\LaravelSumitGateway\Models\CrmFolderField::find($fieldId);

                if ($field) {
                    $this->record->setCustomField($field->field_name, $value);
                }
            }
        }
    }

    protected array $customFields = [];
}
