<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Critical: Add SUMIT Entity ID for accurate transaction matching.
     *
     * This field is the ONLY reliable way to match SUMIT CRM Transaction cards
     * to local OfficeGuyTransaction records. Matching by amount+timestamp is unsafe
     * due to:
     * - Duplicate amounts (very common)
     * - Retries / double-clicks
     * - Recurring payments
     * - Refunds with same amount
     *
     * @see ADR-004: Handling Card Payments via SUMIT CRM Webhooks
     */
    public function up(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            // SUMIT CRM Entity ID (from Transaction Card in SUMIT)
            $table->unsignedBigInteger('sumit_entity_id')
                ->nullable()
                ->after('payment_id')
                ->comment('SUMIT CRM Entity ID from Transaction Card');

            // Unique index for fast lookups and preventing duplicates
            $table->unique('sumit_entity_id', 'officeguy_transactions_sumit_entity_id_unique');

            // Index for webhook processing queries
            $table->index(['sumit_entity_id', 'is_webhook_confirmed'], 'sumit_entity_webhook_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            $table->dropIndex('sumit_entity_webhook_idx');
            $table->dropUnique('officeguy_transactions_sumit_entity_id_unique');
            $table->dropColumn('sumit_entity_id');
        });
    }
};
