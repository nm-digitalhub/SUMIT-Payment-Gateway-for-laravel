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
        if (Schema::hasTable('officeguy_webhook_events')) {
            return;
        }

        Schema::create('officeguy_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('webhook_url')->nullable();
            $table->integer('http_status_code')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            
            // Relations to other resources for automation
            $table->unsignedBigInteger('transaction_id')->nullable()->index();
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            
            // Morphable order reference
            $table->string('order_type')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->index(['order_type', 'order_id']);
            
            // Additional metadata for automation
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_id')->nullable()->index();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();
            
            $table->timestamps();
            
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
        Schema::dropIfExists('officeguy_webhook_events');
    }
};
