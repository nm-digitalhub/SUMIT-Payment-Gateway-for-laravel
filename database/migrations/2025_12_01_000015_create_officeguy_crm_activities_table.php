<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the officeguy_crm_activities table for tracking entity activities.
     * Activities: calls, emails, meetings, notes, tasks, etc.
     */
    public function up(): void
    {
        Schema::create('officeguy_crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_entity_id')->constrained('officeguy_crm_entities')->onDelete('cascade')->comment('Related entity');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Activity owner user ID');
            $table->string('activity_type', 100)->comment('Type: call, email, meeting, note, task, sms, whatsapp');
            $table->string('subject')->comment('Activity subject');
            $table->text('description')->nullable()->comment('Activity description');

            // Activity details
            $table->string('status', 50)->default('completed')->comment('Status: planned, in_progress, completed, cancelled');
            $table->string('priority', 50)->default('normal')->comment('Priority: low, normal, high, urgent');

            // Scheduling
            $table->timestamp('start_at')->nullable()->comment('Start time');
            $table->timestamp('end_at')->nullable()->comment('End time');
            $table->timestamp('reminder_at')->nullable()->comment('Reminder time');

            // Related entities (for integration with main app)
            $table->foreignId('related_document_id')->nullable()->constrained('officeguy_documents')->onDelete('set null')->comment('Link to documents');
            $table->unsignedBigInteger('related_ticket_id')->nullable()->comment('Link to tickets table in main app');

            $table->timestamps();

            // Indexes
            $table->index('crm_entity_id');
            $table->index('user_id');
            $table->index('activity_type');
            $table->index('status');
            $table->index('start_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_crm_activities');
    }
};
