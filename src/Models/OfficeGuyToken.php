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
        'admin_notes',
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
     * משתמש ב-updateOrCreate עם בדיקת owner למניעת העברת כרטיסים בין לקוחות
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

        $cardToken = $data['CardToken'];
        $ownerType = get_class($owner);
        $ownerId = $owner->getKey();

        // Check if token exists for a different owner
        $existingToken = static::where('token', $cardToken)->first();

        if ($existingToken &&
            ($existingToken->owner_type !== $ownerType || $existingToken->owner_id !== $ownerId)) {
            throw new RuntimeException(
                'כרטיס זה כבר רשום עבור לקוח אחר במערכת. ' .
                'לא ניתן להוסיף את אותו כרטיס לשני לקוחות שונים.'
            );
        }

        // Use updateOrCreate for same owner (updates existing or creates new)
        return static::updateOrCreate(
            [
                'token'      => $cardToken,
                'owner_type' => $ownerType,
                'owner_id'   => $ownerId,
            ],
            [
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
            ]
        );
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

    /**
     * קבלת שם חברת האשראי (Brand) בפורמט קריא
     */
    public function getCardTypeName(): string
    {
        $type = (string) ($this->metadata['Brand'] ?? $this->card_type ?? '');

        return match ($type) {
            '1' => 'Visa',
            '2' => 'MasterCard',
            '6' => 'American Express',
            '22' => 'CAL / כאל',
            default => $type !== '' ? "כרטיס אשראי ({$type})" : 'כרטיס אשראי',
        };
    }

    /**
     * קבלת שם המנפיק (Issuer) בפורמט קריא
     */
    public function getIssuerName(): ?string
    {
        $issuer = $this->metadata['Issuer'] ?? null;

        if (!$issuer) {
            return null;
        }

        return match ((string) $issuer) {
            '1' => 'בנק לאומי',
            '2' => 'בנק הפועלים',
            '3' => 'בנק דיסקונט',
            '4' => 'בנק יהב',
            '6' => 'בנק מזרחי טפחות',
            '9' => 'בנק פועלי אגודת ישראל',
            '10' => 'בנק ירושלים',
            '11' => 'בנק אוצר החייל',
            '12' => 'בנק הבינלאומי',
            '13' => 'בנק מסד',
            '14' => 'בנק יובנק',
            '17' => 'בנק מרכנתיל דיסקונט',
            '20' => 'ישראכרט',
            '31' => 'לאומי קארד',
            '35' => 'כאל',
            default => "מנפיק {$issuer}",
        };
    }

    /**
     * קבלת תאריך תפוגה בפורמט קריא
     */
    public function getFormattedExpiry(): string
    {
        if (!$this->expiry_month || !$this->expiry_year) {
            return 'לא זמין';
        }

        return sprintf(
            '%s/%s',
            str_pad($this->expiry_month, 2, '0', STR_PAD_LEFT),
            $this->expiry_year
        );
    }
}