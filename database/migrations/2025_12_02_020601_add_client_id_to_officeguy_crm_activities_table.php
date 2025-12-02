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
        Schema::table('officeguy_crm_activities', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('crm_entity_id')
                ->constrained('clients')
                ->nullOnDelete();

            $table->index(['client_id', 'crm_entity_id'], 'officeguy_crm_activities_client_entity_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('officeguy_crm_activities', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropIndex('officeguy_crm_activities_client_entity_idx');
            $table->dropColumn('client_id');
        });
    }
};
