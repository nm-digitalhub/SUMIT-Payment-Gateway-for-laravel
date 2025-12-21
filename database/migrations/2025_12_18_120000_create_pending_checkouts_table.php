<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates pending_checkouts table for temporary checkout storage.
     *
     * CRITICAL: This is DB-first temporary storage (not Session-based).
     * - Survives server restarts
     * - Works with webhooks and redirects
     * - Auto-cleanup via scheduled job
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('pending_checkouts', function (Blueprint $table) {
            $table->id();

            // Payable reference (polymorphic)
            $table->string('payable_type'); // App\Models\Order, App\Models\Package, etc.
            $table->unsignedBigInteger('payable_id');

            // CheckoutIntent data (serialized)
            $table->json('customer_data'); // CustomerData DTO
            $table->json('payment_preferences'); // PaymentPreferences DTO

            // Service-specific data (WHOIS, cPanel config, etc.)
            // ⚠️ This is stored SEPARATELY from CheckoutIntent (Intent is immutable)
            $table->json('service_data')->nullable();

            // Session tracking (for cleanup and debugging)
            $table->string('session_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Auto-expiration
            $table->timestamp('expires_at');

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index(['payable_type', 'payable_id']);
            $table->index('expires_at'); // For cleanup job
            $table->index('session_id'); // For session-based retrieval
            $table->index('created_at'); // For analytics
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_checkouts');
    }
};
