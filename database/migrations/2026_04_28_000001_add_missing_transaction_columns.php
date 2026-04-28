<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('officeguy_transactions', 'transaction_type')) {
                $table->string('transaction_type')->nullable()->default('charge')->after('status');
            }
            if (! Schema::hasColumn('officeguy_transactions', 'payment_token')) {
                $table->string('payment_token')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('officeguy_transactions', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('officeguy_transactions', 'sumit_customer_id_used')) {
                $table->string('sumit_customer_id_used')->nullable()->after('client_id');
            }
            if (! Schema::hasColumn('officeguy_transactions', 'source')) {
                $table->string('source')->nullable()->after('environment');
            }
            if (! Schema::hasColumn('officeguy_transactions', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('is_test');
            }
            if (! Schema::hasColumn('officeguy_transactions', 'notes')) {
                $table->text('notes')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('officeguy_transactions', 'confirmed_by')) {
                $table->string('confirmed_by')->nullable()->after('is_webhook_confirmed');
            }
        });

        // Add indexes (safe to add even if columns were pre-existing)
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $existingIndexes = array_keys($sm->listTableIndexes('officeguy_transactions'));

            if (! in_array('idx_transaction_type', $existingIndexes, true)
                && Schema::hasColumn('officeguy_transactions', 'transaction_type')) {
                $table->index('transaction_type', 'idx_transaction_type');
            }
            if (! in_array('idx_payment_token', $existingIndexes, true)
                && Schema::hasColumn('officeguy_transactions', 'payment_token')) {
                $table->index('payment_token', 'idx_payment_token');
            }
            if (! in_array('idx_client_id', $existingIndexes, true)
                && Schema::hasColumn('officeguy_transactions', 'client_id')) {
                $table->index('client_id', 'idx_client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transaction_type');
            $table->dropIndex('idx_payment_token');
            $table->dropIndex('idx_client_id');
        });

        Schema::table('officeguy_transactions', function (Blueprint $table) {
            $columns = [
                'transaction_type',
                'payment_token',
                'client_id',
                'sumit_customer_id_used',
                'source',
                'completed_at',
                'notes',
                'confirmed_by',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('officeguy_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
