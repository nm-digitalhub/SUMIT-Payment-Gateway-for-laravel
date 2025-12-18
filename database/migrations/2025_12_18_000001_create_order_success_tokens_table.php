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
        if (Schema::hasTable('order_success_tokens')) {
            return;
        }

        Schema::create('order_success_tokens', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship to any Payable entity
            $table->morphs('payable'); // Creates payable_id + payable_type

            // Token security
            $table->string('token_hash', 64)->unique()->comment('SHA256 hash of the token');
            $table->string('nonce', 64)->unique()->comment('Cryptographic nonce for replay protection');

            // TTL - Single source of truth
            $table->timestamp('expires_at')->index()->comment('Token expiration timestamp');

            // Consumption tracking
            $table->timestamp('consumed_at')->nullable()->index()->comment('When token was used');
            $table->string('consumed_by_ip', 45)->nullable()->comment('IP address that consumed the token');
            $table->text('consumed_by_user_agent')->nullable()->comment('User agent that consumed the token');

            $table->timestamps();

            // Composite indexes for performance
            $table->index(['payable_id', 'payable_type', 'consumed_at'], 'payable_consumed_idx');
            $table->index(['token_hash', 'consumed_at'], 'token_consumed_idx');
            $table->index(['expires_at', 'consumed_at'], 'expires_consumed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_success_tokens');
    }
};
