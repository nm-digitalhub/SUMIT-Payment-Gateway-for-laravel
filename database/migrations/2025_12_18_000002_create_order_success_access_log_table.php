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
        if (Schema::hasTable('order_success_access_log')) {
            return;
        }

        Schema::create('order_success_access_log', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship to any Payable entity
            $table->morphs('payable'); // Creates payable_id + payable_type

            // Access metadata
            $table->string('ip_address', 45)->index()->comment('Client IP address');
            $table->text('user_agent')->nullable()->comment('Client user agent');
            $table->string('referer', 500)->nullable()->comment('HTTP referer header');

            // Validation results
            $table->boolean('is_valid')->default(false)->index()->comment('Whether access was valid');
            $table->json('validation_failures')->nullable()->comment('Failed validation layers if invalid');

            // Security tracking
            $table->string('token_hash', 64)->nullable()->index()->comment('Token hash used for access');
            $table->string('nonce', 64)->nullable()->comment('Nonce used for access');
            $table->boolean('signature_valid')->default(false)->comment('URL signature validation result');

            // Timing
            $table->timestamp('accessed_at')->useCurrent()->index()->comment('When access occurred');

            $table->timestamps();

            // Composite indexes for analytics
            $table->index(['payable_id', 'payable_type', 'accessed_at'], 'payable_accessed_idx');
            $table->index(['is_valid', 'accessed_at'], 'valid_accessed_idx');
            $table->index(['ip_address', 'accessed_at'], 'ip_accessed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_success_access_log');
    }
};
