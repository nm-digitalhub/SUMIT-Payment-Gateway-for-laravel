<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            // Webhook confirmation flag - critical for provisioning gatekeeper pattern
            $table->boolean('is_webhook_confirmed')
                ->default(false)
                ->after('status')
                ->index()
                ->comment('Whether transaction was confirmed via webhook (required for provisioning)');

            $table->timestamp('webhook_confirmed_at')
                ->nullable()
                ->after('is_webhook_confirmed')
                ->comment('When webhook confirmation was received');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            $table->dropColumn(['is_webhook_confirmed', 'webhook_confirmed_at']);
        });
    }
};
