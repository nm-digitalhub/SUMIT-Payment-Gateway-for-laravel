<?php

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CRM Entity Field Model
 *
 * Represents a custom field value for a CRM entity.
 * Stores dynamic field values based on folder field definitions.
 *
 * @property int $id
 * @property int $crm_entity_id Parent entity ID
 * @property int $crm_folder_field_id Field definition ID
 * @property string|null $value Text value
 * @property float|null $value_numeric Numeric value
 * @property \Carbon\Carbon|null $value_date Date value
 * @property bool|null $value_boolean Boolean value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read CrmEntity $entity
 * @property-read CrmFolderField $folderField
 */
class CrmEntityField extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'officeguy_crm_entity_fields';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'crm_entity_id',
        'crm_folder_field_id',
        'value',
        'value_numeric',
        'value_date',
        'value_boolean',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'crm_entity_id' => 'integer',
        'crm_folder_field_id' => 'integer',
        'value_numeric' => 'decimal:2',
        'value_date' => 'date',
        'value_boolean' => 'boolean',
    ];

    /**
     * Get the entity that owns this field.
     *
     * @return BelongsTo<CrmEntity, CrmEntityField>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(CrmEntity::class, 'crm_entity_id');
    }

    /**
     * Get the folder field definition.
     *
     * @return BelongsTo<CrmFolderField, CrmEntityField>
     */
    public function folderField(): BelongsTo
    {
        return $this->belongsTo(CrmFolderField::class, 'crm_folder_field_id');
    }

    /**
     * Get the appropriate value based on field type.
     *
     * @return mixed
     */
    public function getValue()
    {
        $fieldType = $this->folderField->field_type ?? 'text';

        return match ($fieldType) {
            'number' => $this->value_numeric,
            'date' => $this->value_date,
            'boolean' => $this->value_boolean,
            default => $this->value,
        };
    }
}
