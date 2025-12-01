<?php

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CRM View Model
 *
 * Represents a saved view/filter for a CRM folder.
 * Views allow users to save custom filters and column configurations.
 *
 * @property int $id
 * @property int $crm_folder_id Folder ID
 * @property int|null $sumit_view_id SUMIT view ID
 * @property string $name View name
 * @property bool $is_default Is default view for folder
 * @property bool $is_public Is public view (all users)
 * @property int|null $user_id View owner user ID (NULL if public)
 * @property array|null $filters Filter conditions
 * @property string|null $sort_by Sort field
 * @property string $sort_direction Sort direction: asc, desc
 * @property array|null $columns Visible columns
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read CrmFolder $folder
 */
class CrmView extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'officeguy_crm_views';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'crm_folder_id',
        'sumit_view_id',
        'name',
        'is_default',
        'is_public',
        'user_id',
        'filters',
        'sort_by',
        'sort_direction',
        'columns',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'crm_folder_id' => 'integer',
        'sumit_view_id' => 'integer',
        'is_default' => 'boolean',
        'is_public' => 'boolean',
        'user_id' => 'integer',
        'filters' => 'array',
        'columns' => 'array',
    ];

    /**
     * Get the folder that owns this view.
     *
     * @return BelongsTo<CrmFolder, CrmView>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(CrmFolder::class, 'crm_folder_id');
    }

    /**
     * Scope a query to only include public views.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope a query to only include default views.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to filter by user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_public', true)
                ->orWhere('user_id', $userId);
        });
    }

    /**
     * Apply this view's filters to a query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyToQuery($query)
    {
        if ($this->filters) {
            foreach ($this->filters as $field => $value) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
        }

        if ($this->sort_by) {
            $query->orderBy($this->sort_by, $this->sort_direction);
        }

        return $query;
    }
}
