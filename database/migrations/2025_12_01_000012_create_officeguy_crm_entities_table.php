<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the officeguy_crm_entities table for storing CRM entities (contacts, leads, companies, deals).
     * This is the main table for all CRM data with common fields for all entity types.
     */
    public function up(): void
    {
        Schema::create('officeguy_crm_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_folder_id')->constrained('officeguy_crm_folders')->onDelete('cascade')->comment('Entity type/folder');
            $table->unsignedBigInteger('sumit_entity_id')->unique()->nullable()->comment('SUMIT entity ID');
            $table->string('entity_type', 100)->comment('Entity type: contact, lead, company, deal');

            // Common fields
            $table->string('name')->comment('Full name / Company name');
            $table->string('email')->nullable()->comment('Email address');
            $table->string('phone', 50)->nullable()->comment('Phone number');
            $table->string('mobile', 50)->nullable()->comment('Mobile number');

            // Address
            $table->text('address')->nullable()->comment('Street address');
            $table->string('city', 100)->nullable()->comment('City');
            $table->string('state', 100)->nullable()->comment('State/Region');
            $table->string('postal_code', 20)->nullable()->comment('Postal code');
            $table->string('country', 100)->default('Israel')->comment('Country');

            // Business info
            $table->string('company_name')->nullable()->comment('Company name (for contacts)');
            $table->string('tax_id', 50)->nullable()->comment('Tax ID / VAT number');

            // Status
            $table->string('status', 50)->default('active')->comment('Status: active, inactive, archived');
            $table->string('source', 100)->nullable()->comment('Source: website, referral, import, manual');

            // Ownership
            $table->unsignedBigInteger('owner_user_id')->nullable()->comment('Owner user ID');
            $table->unsignedBigInteger('assigned_to_user_id')->nullable()->comment('Assigned to user ID');

            // SUMIT Customer ID (managed via API, no local customer table)
            $table->unsignedBigInteger('sumit_customer_id')->nullable()->comment('SUMIT customer ID');

            // Timestamps
            $table->timestamp('last_contact_at')->nullable()->comment('Last contact date');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('crm_folder_id');
            $table->index('entity_type');
            $table->index('status');
            $table->index('email');
            $table->index('phone');
            $table->index('sumit_customer_id');
            $table->index('owner_user_id');
            $table->fullText(['name', 'company_name', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_crm_entities');
    }
};
