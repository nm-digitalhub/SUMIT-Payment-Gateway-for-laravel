<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the officeguy_crm_views table for storing saved views/filters.
     * Views allow users to save custom filters and column configurations.
     */
    public function up(): void
    {
        Schema::create('officeguy_crm_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_folder_id')->constrained('officeguy_crm_folders')->onDelete('cascade')->comment('Folder');
            $table->unsignedBigInteger('sumit_view_id')->nullable()->comment('SUMIT view ID');
            $table->string('name')->comment('View name');
            $table->boolean('is_default')->default(false)->comment('Is default view for folder');
            $table->boolean('is_public')->default(false)->comment('Is public view (all users)');
            $table->unsignedBigInteger('user_id')->nullable()->comment('View owner user ID (NULL if public)');
            $table->json('filters')->nullable()->comment('Filter conditions JSON');
            $table->string('sort_by')->nullable()->comment('Sort field');
            $table->string('sort_direction', 4)->default('asc')->comment('Sort direction: asc, desc');
            $table->json('columns')->nullable()->comment('Visible columns JSON');
            $table->timestamps();

            // Indexes
            $table->index('crm_folder_id');
            $table->index('user_id');
            $table->index('is_default');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_crm_views');
    }
};
