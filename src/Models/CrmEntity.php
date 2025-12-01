<?php

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CRM Entity Model
 *
 * Represents a CRM entity (contact, lead, company, deal).
 * This is the main table for all CRM data with common fields for all entity types.
 *
 * @property int $id
 * @property int $crm_folder_id Entity type/folder
 * @property int|null $sumit_entity_id SUMIT entity ID
 * @property string $entity_type Entity type: contact, lead, company, deal
 * @property string $name Full name / Company name
 * @property string|null $email Email address
 * @property string|null $phone Phone number
 * @property string|null $mobile Mobile number
 * @property string|null $address Street address
 * @property string|null $city City
 * @property string|null $state State/Region
 * @property string|null $postal_code Postal code
 * @property string $country Country
 * @property string|null $company_name Company name (for contacts)
 * @property string|null $tax_id Tax ID / VAT number
 * @property string $status Status: active, inactive, archived
 * @property string|null $source Source: website, referral, import, manual
 * @property int|null $owner_user_id Owner user ID
 * @property int|null $assigned_to_user_id Assigned to user ID
 * @property int|null $sumit_customer_id SUMIT customer ID
 * @property \Carbon\Carbon|null $last_contact_at Last contact date
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read CrmFolder $folder
 * @property-read \Illuminate\Database\Eloquent\Collection<CrmEntityField> $customFields
 * @property-read \Illuminate\Database\Eloquent\Collection<CrmActivity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<CrmEntity> $relatedFrom
 * @property-read \Illuminate\Database\Eloquent\Collection<CrmEntity> $relatedTo
 */
class CrmEntity extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'officeguy_crm_entities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'crm_folder_id',
        'sumit_entity_id',
        'entity_type',
        'name',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'company_name',
        'tax_id',
        'status',
        'source',
        'owner_user_id',
        'assigned_to_user_id',
        'sumit_customer_id',
        'last_contact_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'crm_folder_id' => 'integer',
        'sumit_entity_id' => 'integer',
        'owner_user_id' => 'integer',
        'assigned_to_user_id' => 'integer',
        'sumit_customer_id' => 'integer',
        'last_contact_at' => 'datetime',
    ];

    /**
     * Get the folder that owns this entity.
     *
     * @return BelongsTo<CrmFolder, CrmEntity>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(CrmFolder::class, 'crm_folder_id');
    }

    /**
     * Get the custom field values for this entity.
     *
     * @return HasMany<CrmEntityField>
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CrmEntityField::class, 'crm_entity_id');
    }

    /**
     * Get the activities for this entity.
     *
     * @return HasMany<CrmActivity>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'crm_entity_id');
    }

    /**
     * Get entities related from this entity.
     *
     * @return HasMany<CrmEntityRelation>
     */
    public function relatedFrom(): HasMany
    {
        return $this->hasMany(CrmEntityRelation::class, 'from_entity_id');
    }

    /**
     * Get entities related to this entity.
     *
     * @return HasMany<CrmEntityRelation>
     */
    public function relatedTo(): HasMany
    {
        return $this->hasMany(CrmEntityRelation::class, 'to_entity_id');
    }

    /**
     * Scope a query to only include active entities.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to filter by entity type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('entity_type', $type);
    }

    /**
     * Scope a query to filter by owner.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('owner_user_id', $userId);
    }

    /**
     * Scope a query to filter by assigned user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to_user_id', $userId);
    }

    /**
     * Scope a query to search entities.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%");
        });
    }

    /**
     * Get the full display name for this entity.
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->entity_type === 'contact' && $this->company_name) {
            return "{$this->name} ({$this->company_name})";
        }

        return $this->name;
    }

    /**
     * Get custom field value by field name.
     *
     * @param string $fieldName
     * @return mixed
     */
    public function getCustomField(string $fieldName)
    {
        $field = $this->customFields()
            ->whereHas('folderField', function ($query) use ($fieldName) {
                $query->where('name', $fieldName);
            })
            ->first();

        if (! $field) {
            return null;
        }

        // Return appropriate value based on field type
        $folderField = $field->folderField;

        return match ($folderField->field_type) {
            'number' => $field->value_numeric,
            'date' => $field->value_date,
            'boolean' => $field->value_boolean,
            default => $field->value,
        };
    }

    /**
     * Set custom field value by field name.
     *
     * @param string $fieldName
     * @param mixed $value
     * @return void
     */
    public function setCustomField(string $fieldName, $value): void
    {
        $folderField = $this->folder->fields()->where('name', $fieldName)->first();

        if (! $folderField) {
            return;
        }

        $entityField = $this->customFields()
            ->where('crm_folder_field_id', $folderField->id)
            ->firstOrNew();

        $entityField->crm_entity_id = $this->id;
        $entityField->crm_folder_field_id = $folderField->id;

        // Set appropriate value based on field type
        match ($folderField->field_type) {
            'number' => $entityField->value_numeric = $value,
            'date' => $entityField->value_date = $value,
            'boolean' => $entityField->value_boolean = $value,
            default => $entityField->value = $value,
        };

        $entityField->save();
    }
}
