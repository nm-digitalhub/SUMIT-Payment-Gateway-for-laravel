<?php

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CRM Activity Model
 *
 * Represents an activity for a CRM entity.
 * Activities: calls, emails, meetings, notes, tasks, SMS, WhatsApp, etc.
 *
 * @property int $id
 * @property int $crm_entity_id Related entity ID
 * @property int|null $user_id Activity owner user ID
 * @property string $activity_type Type: call, email, meeting, note, task, sms, whatsapp
 * @property string $subject Activity subject
 * @property string|null $description Activity description
 * @property string $status Status: planned, in_progress, completed, cancelled
 * @property string $priority Priority: low, normal, high, urgent
 * @property \Carbon\Carbon|null $start_at Start time
 * @property \Carbon\Carbon|null $end_at End time
 * @property \Carbon\Carbon|null $reminder_at Reminder time
 * @property int|null $related_document_id Link to officeguy_documents
 * @property int|null $related_ticket_id Link to tickets
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read CrmEntity $entity
 * @property-read OfficeGuyDocument|null $document
 */
class CrmActivity extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'officeguy_crm_activities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'crm_entity_id',
        'client_id',
        'user_id',
        'activity_type',
        'subject',
        'description',
        'status',
        'priority',
        'start_at',
        'end_at',
        'reminder_at',
        'related_document_id',
        'related_ticket_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'crm_entity_id' => 'integer',
        'client_id' => 'integer',
        'user_id' => 'integer',
        'related_document_id' => 'integer',
        'related_ticket_id' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'reminder_at' => 'datetime',
    ];

    /**
     * Get the entity that owns this activity.
     *
     * @return BelongsTo<CrmEntity, CrmActivity>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(CrmEntity::class, 'crm_entity_id');
    }

    /**
     * Get the customer relationship using dynamic model resolution.
     *
     * This method uses app('officeguy.customer_model') with 3-layer priority:
     * 1. Database: officeguy_settings.customer_model_class (Admin Panel editable)
     * 2. Config: officeguy.models.customer (new nested structure)
     * 3. Config: officeguy.customer_model_class (legacy flat structure)
     *
     * Fallback: If no customer model is configured, defaults to \App\Models\Client
     * for backward compatibility.
     *
     * @return BelongsTo
     */
    public function customer(): BelongsTo
    {
        $customerModel = app('officeguy.customer_model') ?? \App\Models\Client::class;

        return $this->belongsTo($customerModel, 'client_id');
    }

    /**
     * Legacy client relationship - DEPRECATED.
     *
     * @deprecated Use customer() instead. This method will be removed in v3.0.0.
     *
     * This method is preserved for backward compatibility but delegates to customer().
     * The relationship is identical - only the method name differs.
     *
     * Migration:
     * - Replace $activity->client with $activity->customer
     * - Replace $activity->client() with $activity->customer()
     *
     * @return BelongsTo
     */
    public function client(): BelongsTo
    {
        return $this->customer();
    }

    /**
     * Get the user who created/owns this activity.
     *
     * @return BelongsTo<\App\Models\User, CrmActivity>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Alias for user() relationship for better readability.
     *
     * @return BelongsTo<\App\Models\User, CrmActivity>
     */
    public function createdBy(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Get the related document.
     *
     * @return BelongsTo<OfficeGuyDocument, CrmActivity>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(OfficeGuyDocument::class, 'related_document_id');
    }

    /**
     * Scope a query to filter by activity type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('activity_type', $type);
    }

    /**
     * Scope a query to filter by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include completed activities.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include planned activities.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
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
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by upcoming activities.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'planned')
            ->where('start_at', '>=', now())
            ->orderBy('start_at');
    }

    /**
     * Scope a query to filter by overdue activities.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'planned')
            ->where('start_at', '<', now())
            ->orderBy('start_at', 'desc');
    }

    /**
     * Check if activity is overdue.
     *
     * @return bool
     */
    public function isOverdue(): bool
    {
        return $this->status === 'planned'
            && $this->start_at
            && $this->start_at->isPast();
    }

    /**
     * Check if activity is upcoming.
     *
     * @return bool
     */
    public function isUpcoming(): bool
    {
        return $this->status === 'planned'
            && $this->start_at
            && $this->start_at->isFuture();
    }
}
