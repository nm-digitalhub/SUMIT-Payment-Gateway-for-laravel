<?php

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
        Schema::table('officeguy_sumit_webhooks', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('clients')
                ->nullOnDelete();

            $table->index(['client_id', 'customer_id'], 'officeguy_sumit_webhooks_client_customer_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('officeguy_sumit_webhooks', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropIndex('officeguy_sumit_webhooks_client_customer_idx');
            $table->dropColumn('client_id');
        });
    }
};
