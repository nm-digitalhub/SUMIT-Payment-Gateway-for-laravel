<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the officeguy_crm_folder_fields table for storing field definitions per folder.
     * Defines the schema for each CRM entity type (text, email, phone, date, etc.).
     */
    public function up(): void
    {
        Schema::create('officeguy_crm_folder_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_folder_id')->constrained('officeguy_crm_folders')->onDelete('cascade')->comment('Parent folder');
            $table->unsignedBigInteger('sumit_field_id')->nullable()->comment('SUMIT field ID');
            $table->string('name')->comment('Field name (snake_case)');
            $table->string('label')->comment('Field label (display name)');
            $table->string('field_type', 50)->comment('Field type: text, number, email, phone, date, select, multiselect, boolean');
            $table->boolean('is_required')->default(false)->comment('Is field required');
            $table->boolean('is_unique')->default(false)->comment('Is field unique');
            $table->boolean('is_searchable')->default(true)->comment('Is field searchable');
            $table->text('default_value')->nullable()->comment('Default value');
            $table->json('validation_rules')->nullable()->comment('Validation rules JSON');
            $table->json('options')->nullable()->comment('Options for select/multiselect');
            $table->integer('display_order')->default(0)->comment('Display order');
            $table->timestamps();

            // Indexes
            $table->index('crm_folder_id');
            $table->index('field_type');
            $table->unique(['crm_folder_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_crm_folder_fields');
    }
};
