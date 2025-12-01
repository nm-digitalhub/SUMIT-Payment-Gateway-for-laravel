<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the officeguy_crm_entity_relations table for storing relationships between entities.
     * Examples: Parent/Child, Related, Duplicate detection.
     */
    public function up(): void
    {
        Schema::create('officeguy_crm_entity_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_entity_id')->constrained('officeguy_crm_entities')->onDelete('cascade')->comment('Source entity');
            $table->foreignId('to_entity_id')->constrained('officeguy_crm_entities')->onDelete('cascade')->comment('Target entity');
            $table->string('relation_type', 100)->comment('Relation type: parent, child, related, duplicate, merged');
            $table->json('metadata')->nullable()->comment('Additional relation metadata');
            $table->timestamps();

            // Indexes
            $table->index('from_entity_id');
            $table->index('to_entity_id');
            $table->index('relation_type');
            $table->unique(['from_entity_id', 'to_entity_id', 'relation_type'], 'crm_relation_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_crm_entity_relations');
    }
};
