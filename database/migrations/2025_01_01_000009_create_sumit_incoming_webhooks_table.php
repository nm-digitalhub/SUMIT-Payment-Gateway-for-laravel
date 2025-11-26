<?php

declare(strict_types=1);

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
        if (Schema::hasTable('officeguy_sumit_webhooks')) {
            return;
        }

        Schema::create('officeguy_sumit_webhooks', function (Blueprint $table) {
            $table->id();
            
            // Webhook identification
            $table->string('webhook_id')->nullable()->unique()->comment('SUMIT webhook/trigger ID');
            $table->string('event_type', 50)->index()->comment('card_created, card_updated, card_deleted, card_archived');
            $table->string('card_type', 50)->nullable()->index()->comment('customer, document, transaction, item, etc.');
            
            // Request information
            $table->string('source_ip')->nullable();
            $table->string('content_type')->nullable();
            $table->json('headers')->nullable();
            
            // Payload
            $table->json('payload')->nullable()->comment('Full webhook payload from SUMIT');
            
            // Card data extracted from payload
            $table->string('card_id')->nullable()->index()->comment('ID of the card in SUMIT');
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();
            
            // Processing status
            $table->string('status', 20)->default('received')->index()->comment('received, processed, failed, ignored');
            $table->text('processing_notes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            
            // Relations to local resources (populated after processing)
            $table->unsignedBigInteger('transaction_id')->nullable()->index();
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            
            $table->timestamps();
            
            // Indexes for filtering
            $table->index(['event_type', 'status']);
            $table->index(['card_type', 'card_id']);
            $table->index('created_at');
            
            // Foreign keys
            $table->foreign('transaction_id')
                ->references('id')
                ->on('officeguy_transactions')
                ->onDelete('set null');
            
            $table->foreign('document_id')
                ->references('id')
                ->on('officeguy_documents')
                ->onDelete('set null');
            
            $table->foreign('token_id')
                ->references('id')
                ->on('officeguy_tokens')
                ->onDelete('set null');
            
            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_sumit_webhooks');
    }
};
