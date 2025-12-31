<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add Upay payment processor fields to officeguy_transactions table.
     * These fields capture technical payment processing data from Upay.
     *
     * Fields added:
     * - upay_transaction_id: Upay's internal transaction identifier
     * - upay_voucher_number: Upay receipt/voucher number
     *
     * Source: Webhook Type="Create" from SUMIT CRM
     * - Property_9 → upay_transaction_id
     * - Property_7 → upay_voucher_number
     * - Property_8 → auth_number (already exists)
     */
    public function up(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            // Upay Transaction ID - מזהה עסקה ב-Upay
            $table->string('upay_transaction_id', 50)
                ->nullable()
                ->after('sumit_entity_id')
                ->comment('Upay payment processor transaction ID (from webhook Property_9)');

            // Upay Voucher Number - מספר שובר
            $table->string('upay_voucher_number', 50)
                ->nullable()
                ->after('auth_number')
                ->comment('Upay voucher/receipt number (from webhook Property_7)');

            // Indexes for performance
            $table->index('upay_transaction_id', 'idx_upay_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_upay_transaction_id');
            $table->dropColumn(['upay_transaction_id', 'upay_voucher_number']);
        });
    }
};
