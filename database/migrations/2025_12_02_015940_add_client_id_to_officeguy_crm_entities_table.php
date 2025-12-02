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
        Schema::table('officeguy_crm_entities', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('assigned_to_user_id')
                ->constrained('clients')
                ->nullOnDelete();

            $table->index(['client_id', 'sumit_entity_id'], 'officeguy_crm_entities_client_sumit_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('officeguy_crm_entities', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropIndex('officeguy_crm_entities_client_sumit_idx');
            $table->dropColumn('client_id');
        });
    }
};
