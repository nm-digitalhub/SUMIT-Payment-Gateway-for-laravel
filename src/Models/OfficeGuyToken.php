<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use RuntimeException;

class OfficeGuyToken extends Model
{
    use SoftDeletes;

    protected $table = 'officeguy_tokens';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'token',
        'gateway_id',
        'card_type',
        'last_four',
        'citizen_id',
        'expiry_month',
        'expiry_year',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'metadata'   => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * יצירת טוקן מתוך תשובת SUMIT API
     * כולל שמירה לבסיס נתונים
     */
    public static function createFromApiResponse(
        mixed $owner,
        array $response,
        string $gatewayId = 'officeguy'
    ): static {
        $data = $response['Data'] ?? null;

        if (!is_array($data) || !isset($data['CardToken'])) {
            throw new RuntimeException('CardToken missing from SUMIT response');
        }

        return static::create([
            'owner_type'   => get_class($owner),
            'owner_id'     => $owner->getKey(),
            'token'        => $data['CardToken'],
            'gateway_id'   => $gatewayId,
            'card_type'    => $data['Brand'] ?? 'card',
            'last_four'    => isset($data['CardPattern'])
                ? substr($data['CardPattern'], -4)
                : '',
            'citizen_id'   => $data['CitizenID'] ?? null,
            'expiry_month' => str_pad((string)($data['ExpirationMonth'] ?? '01'), 2, '0', STR_PAD_LEFT),
            'expiry_year'  => (string)($data['ExpirationYear'] ?? date('Y')),
            'is_default'   => false,
            'metadata'     => $data,
        ]);
    }

    /**
     * האם הכרטיס פג תוקף?
     */
    public function isExpired(): bool
    {
        if (!$this->expiry_month || !$this->expiry_year) {
            return false;
        }

        $expiry = Carbon::createFromDate(
            (int) $this->expiry_year,
            (int) $this->expiry_month,
            1
        )->endOfMonth();

        return $expiry->isPast();
    }

    /**
     * סימון ככרטיס ברירת־מחדל ללקוח
     */
    public function setAsDefault(): void
    {
        static::where('owner_type', $this->owner_type)
            ->where('owner_id', $this->owner_id)
            ->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }

    public function getMaskedNumber(): string
    {
        return '**** **** **** ' . $this->last_four;
    }
}