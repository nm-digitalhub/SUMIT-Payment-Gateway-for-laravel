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
     * Add missing refund_transaction_id field and indexes.
     * Note: parent_transaction_id, transaction_type, and payment_token already exist.
     */
    public function up(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            // Add refund_transaction_id (reverse link: charge -> refund)
            // Other fields (parent_transaction_id, transaction_type, payment_token) already exist
            if (! Schema::hasColumn('officeguy_transactions', 'refund_transaction_id')) {
                $table->foreignId('refund_transaction_id')
                    ->nullable()
                    ->after('parent_transaction_id')
                    ->constrained('officeguy_transactions')
                    ->onDelete('set null')
                    ->comment('Refund transaction ID (populated when charge is refunded)');
            }
        });

        Schema::table('officeguy_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('officeguy_transactions', 'transaction_type')) {
                $table->index('transaction_type', 'idx_transaction_type');
            }
            if (Schema::hasColumn('officeguy_transactions', 'payment_token')) {
                $table->index('payment_token', 'idx_payment_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transaction_type');
            $table->dropIndex('idx_payment_token');
        });

        Schema::table('officeguy_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('officeguy_transactions', 'refund_transaction_id')) {
                $table->dropForeign(['refund_transaction_id']);
                $table->dropColumn('refund_transaction_id');
            }
        });
    }
};
