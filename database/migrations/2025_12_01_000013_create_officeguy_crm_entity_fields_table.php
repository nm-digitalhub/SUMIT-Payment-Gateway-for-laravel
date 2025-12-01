<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the officeguy_crm_entity_fields table for storing dynamic field values.
     * Each entity can have custom fields defined by their folder.
     */
    public function up(): void
    {
        Schema::create('officeguy_crm_entity_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_entity_id')->constrained('officeguy_crm_entities')->onDelete('cascade')->comment('Parent entity');
            $table->foreignId('crm_folder_field_id')->constrained('officeguy_crm_folder_fields')->onDelete('cascade')->comment('Field definition');
            $table->text('value')->nullable()->comment('Field value (text)');
            $table->decimal('value_numeric', 15, 2)->nullable()->comment('Field value (numeric)');
            $table->date('value_date')->nullable()->comment('Field value (date)');
            $table->boolean('value_boolean')->nullable()->comment('Field value (boolean)');
            $table->timestamps();

            // Indexes
            $table->index('crm_entity_id');
            $table->index('crm_folder_field_id');
            $table->index('value_numeric');
            $table->index('value_date');
            $table->unique(['crm_entity_id', 'crm_folder_field_id'], 'crm_entity_field_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_crm_entity_fields');
    }
};
