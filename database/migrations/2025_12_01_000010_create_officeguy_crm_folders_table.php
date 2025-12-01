<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the officeguy_crm_folders table for storing CRM entity type definitions.
     * Folders represent entity types like Contacts, Leads, Companies, Deals.
     */
    public function up(): void
    {
        Schema::create('officeguy_crm_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sumit_folder_id')->unique()->nullable()->comment('SUMIT folder ID');
            $table->string('name')->comment('Folder name (singular)');
            $table->string('name_plural')->comment('Folder name (plural)');
            $table->string('icon', 100)->nullable()->comment('Icon name');
            $table->string('color', 7)->nullable()->comment('Hex color code');
            $table->string('entity_type', 100)->comment('Entity type: contact, lead, company, deal');
            $table->boolean('is_system')->default(false)->comment('System folder (cannot be deleted)');
            $table->boolean('is_active')->default(true)->comment('Is folder active');
            $table->json('settings')->nullable()->comment('Folder settings JSON');
            $table->timestamps();

            // Indexes
            $table->index('entity_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_crm_folders');
    }
};
