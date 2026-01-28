<?php

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CRM Folder Field Model
 *
 * Represents a field definition for a CRM folder.
 * Defines the schema for entity types (text, email, phone, date, etc.).
 *
 * @property int $id
 * @property int $crm_folder_id Parent folder ID
 * @property int|null $sumit_field_id SUMIT field ID
 * @property string $name Field name (snake_case)
 * @property string $label Field label (display name)
 * @property string $field_type Field type: text, number, email, phone, date, select, multiselect, boolean
 * @property bool $is_required Is field required
 * @property bool $is_unique Is field unique
 * @property bool $is_searchable Is field searchable
 * @property string|null $default_value Default value
 * @property array|null $validation_rules Validation rules
 * @property array|null $options Options for select/multiselect
 * @property int $display_order Display order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read CrmFolder $folder
 * @property-read \Illuminate\Database\Eloquent\Collection<CrmEntityField> $entityFields
 */
class CrmFolderField extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'officeguy_crm_folder_fields';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'crm_folder_id',
        'sumit_field_id',
        'name',
        'label',
        'field_type',
        'is_required',
        'is_unique',
        'is_searchable',
        'default_value',
        'validation_rules',
        'options',
        'display_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'crm_folder_id' => 'integer',
        'sumit_field_id' => 'integer',
        'is_required' => 'boolean',
        'is_unique' => 'boolean',
        'is_searchable' => 'boolean',
        'validation_rules' => 'array',
        'options' => 'array',
        'display_order' => 'integer',
    ];

    /**
     * Get the folder that owns this field.
     *
     * @return BelongsTo<CrmFolder, CrmFolderField>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(CrmFolder::class, 'crm_folder_id');
    }

    /**
     * Get the entity field values for this field definition.
     *
     * @return HasMany<CrmEntityField>
     */
    public function entityFields(): HasMany
    {
        return $this->hasMany(CrmEntityField::class, 'crm_folder_field_id');
    }

    /**
     * Scope a query to only include required fields.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope a query to only include searchable fields.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchable($query)
    {
        return $query->where('is_searchable', true);
    }

    /**
     * Scope a query to order by display order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }
}
