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
        if (Schema::hasTable('officeguy_subscriptions')) {
            return;
        }

        Schema::create('officeguy_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subscriber'); // polymorphic relation to User/Customer
            $table->string('name'); // Subscription name/description
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ILS');
            $table->unsignedInteger('interval_months')->default(1); // Duration in months between charges
            $table->unsignedInteger('total_cycles')->nullable(); // Total number of charges (null = unlimited)
            $table->unsignedInteger('completed_cycles')->default(0); // Number of completed charges
            $table->string('recurring_id')->nullable(); // SUMIT recurring payment ID
            $table->string('status')->default('pending'); // pending, active, paused, cancelled, expired, failed
            $table->string('payment_method_token')->nullable(); // Token ID for recurring charges
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_charge_at')->nullable(); // Next scheduled charge date
            $table->timestamp('last_charged_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'next_charge_at']);
            $table->index(['subscriber_type', 'subscriber_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_subscriptions');
    }
};
