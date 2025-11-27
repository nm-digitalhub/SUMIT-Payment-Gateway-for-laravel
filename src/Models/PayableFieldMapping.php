<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * PayableFieldMapping Model
 *
 * Stores custom field mappings for models that need to implement the Payable interface
 * without directly modifying the model or using the PayableAdapter trait.
 *
 * @property int $id
 * @property string $model_class Fully qualified model class name (e.g., App\Models\MayaNetEsimProduct)
 * @property string|null $label User-friendly label for this mapping
 * @property array $field_mappings JSON mapping of Payable interface fields to model field names
 * @property bool $is_active Whether this mapping is currently active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class PayableFieldMapping extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'payable_field_mappings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'model_class',
        'label',
        'field_mappings',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'field_mappings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Check if the mapped model class exists and is valid.
     */
    public function isModelValid(): bool
    {
        return class_exists($this->model_class);
    }

    /**
     * Get the mapping for a specific Payable field.
     *
     * @param string $payableField The Payable interface field name (e.g., 'amount', 'customer_name')
     * @return string|null The mapped model field name or null if not mapped
     */
    public function getMapping(string $payableField): ?string
    {
        return $this->field_mappings[$payableField] ?? null;
    }

    /**
     * Update the mapping for a specific Payable field.
     *
     * @param string $payableField The Payable interface field name
     * @param string|null $modelField The model field name to map to (null to remove mapping)
     */
    public function updateMapping(string $payableField, ?string $modelField): void
    {
        $mappings = $this->field_mappings;
        $mappings[$payableField] = $modelField;
        $this->update(['field_mappings' => $mappings]);
    }

    /**
     * Get count of mapped fields (non-null values).
     */
    public function mappedFieldsCount(): Attribute
    {
        return Attribute::make(
            get: fn () => count(array_filter($this->field_mappings ?? []))
        );
    }

    /**
     * Get a short model name (basename).
     */
    public function shortModelName(): Attribute
    {
        return Attribute::make(
            get: fn () => class_basename($this->model_class)
        );
    }

    /**
     * Scope to get only active mappings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to find mapping by model class.
     */
    public function scopeForModel($query, string $modelClass)
    {
        return $query->where('model_class', $modelClass);
    }
}
