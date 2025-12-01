<?php

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CRM Folder Model
 *
 * Represents a CRM entity type (Contacts, Leads, Companies, Deals).
 * Each folder defines a type of entity with its own fields and schema.
 *
 * @property int $id
 * @property int|null $sumit_folder_id SUMIT folder ID
 * @property string $name Folder name (singular)
 * @property string $name_plural Folder name (plural)
 * @property string|null $icon Icon name
 * @property string|null $color Hex color code
 * @property string $entity_type Entity type: contact, lead, company, deal
 * @property bool $is_system System folder (cannot be deleted)
 * @property bool $is_active Is folder active
 * @property array|null $settings Folder settings
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<CrmFolderField> $fields
 * @property-read \Illuminate\Database\Eloquent\Collection<CrmEntity> $entities
 * @property-read \Illuminate\Database\Eloquent\Collection<CrmView> $views
 */
class CrmFolder extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'officeguy_crm_folders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'sumit_folder_id',
        'name',
        'name_plural',
        'icon',
        'color',
        'entity_type',
        'is_system',
        'is_active',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sumit_folder_id' => 'integer',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the fields for this folder.
     *
     * @return HasMany<CrmFolderField>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(CrmFolderField::class, 'crm_folder_id');
    }

    /**
     * Get the entities in this folder.
     *
     * @return HasMany<CrmEntity>
     */
    public function entities(): HasMany
    {
        return $this->hasMany(CrmEntity::class, 'crm_folder_id');
    }

    /**
     * Get the views for this folder.
     *
     * @return HasMany<CrmView>
     */
    public function views(): HasMany
    {
        return $this->hasMany(CrmView::class, 'crm_folder_id');
    }

    /**
     * Scope a query to only include active folders.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include system folders.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
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
}
