<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

/**
 * Vendor Credentials Model
 *
 * Stores SUMIT API credentials for individual vendors in multi-vendor setups.
 * Port of credential storage from OfficeGuyDokanMarketplace.php and similar marketplace files.
 */
class VendorCredential extends Model
{
    use SoftDeletes;

    protected $table = 'officeguy_vendor_credentials';

    protected $fillable = [
        'vendor_type',
        'vendor_id',
        'company_id',
        'api_key',
        'public_key',
        'merchant_number',
        'is_active',
        'validation_status',
        'validation_message',
        'validated_at',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'validated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
    ];

    /**
     * Get the vendor (polymorphic)
     */
    public function vendor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get credentials array for API requests
     */
    public function getCredentials(): array
    {
        return [
            'CompanyID' => $this->company_id,
            'APIKey' => $this->api_key,
        ];
    }

    /**
     * Validate credentials against SUMIT API
     */
    public function validateCredentials(): bool
    {
        $result = OfficeGuyApi::checkCredentials(
            (int) $this->company_id,
            $this->api_key
        );

        $this->validation_status = $result === null ? 'valid' : 'invalid';
        $this->validation_message = $result;
        $this->validated_at = now();
        $this->save();

        return $result === null;
    }

    /**
     * Check if credentials are valid
     */
    public function isValid(): bool
    {
        return $this->is_active && $this->validation_status === 'valid';
    }

    /**
     * Scope to only active credentials
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only valid credentials
     */
    public function scopeValid($query)
    {
        return $query->active()->where('validation_status', 'valid');
    }

    /**
     * Get credentials for a specific vendor
     */
    public static function forVendor(mixed $vendor): ?static
    {
        return static::where('vendor_type', get_class($vendor))
            ->where('vendor_id', $vendor->getKey())
            ->active()
            ->first();
    }
}
