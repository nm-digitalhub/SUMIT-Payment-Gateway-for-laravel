<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuySetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add default values for secure success page settings (v1.2.0)
        // These values will be used if not overridden in Admin Panel

        $defaults = [
            'success_enabled' => true,
            'success_token_ttl' => 24,
            'success_rate_limit_max' => 10,
            'success_rate_limit_decay' => 1,
        ];

        foreach ($defaults as $key => $value) {
            // Only insert if key doesn't exist (preserve user overrides)
            if (!OfficeGuySetting::has($key)) {
                OfficeGuySetting::set($key, $value);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove secure success settings
        $keys = [
            'success_enabled',
            'success_token_ttl',
            'success_rate_limit_max',
            'success_rate_limit_decay',
        ];

        foreach ($keys as $key) {
            OfficeGuySetting::where('key', $key)->delete();
        }
    }
};
