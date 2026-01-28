<?php

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CRM Entity Relation Model
 *
 * Represents relationships between CRM entities.
 * Examples: Parent/Child, Related, Duplicate detection, Merged.
 *
 * @property int $id
 * @property int $from_entity_id Source entity ID
 * @property int $to_entity_id Target entity ID
 * @property string $relation_type Relation type: parent, child, related, duplicate, merged
 * @property array|null $metadata Additional relation metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read CrmEntity $fromEntity
 * @property-read CrmEntity $toEntity
 */
class CrmEntityRelation extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'officeguy_crm_entity_relations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'from_entity_id',
        'to_entity_id',
        'relation_type',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'from_entity_id' => 'integer',
        'to_entity_id' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the source entity.
     *
     * @return BelongsTo<CrmEntity, CrmEntityRelation>
     */
    public function fromEntity(): BelongsTo
    {
        return $this->belongsTo(CrmEntity::class, 'from_entity_id');
    }

    /**
     * Get the target entity.
     *
     * @return BelongsTo<CrmEntity, CrmEntityRelation>
     */
    public function toEntity(): BelongsTo
    {
        return $this->belongsTo(CrmEntity::class, 'to_entity_id');
    }

    /**
     * Scope a query to filter by relation type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('relation_type', $type);
    }
}
