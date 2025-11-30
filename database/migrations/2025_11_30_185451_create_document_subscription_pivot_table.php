<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Many-to-many relationship between documents and subscriptions.
     * Solves the problem of SUMIT consolidating multiple subscriptions into a single invoice.
     */
    public function up(): void
    {
        Schema::create('document_subscription', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('document_id')
                ->constrained('officeguy_documents')
                ->onDelete('cascade');

            $table->foreignId('subscription_id')
                ->constrained('officeguy_subscriptions')
                ->onDelete('cascade');

            // Pivot data: which item in the document belongs to this subscription
            $table->decimal('amount', 10, 2)->comment('Amount for this specific subscription in the document');
            $table->json('item_data')->nullable()->comment('The specific item(s) from the document that belong to this subscription');

            $table->timestamps();

            // Unique constraint: each document-subscription pair should exist only once
            $table->unique(['document_id', 'subscription_id']);

            // Indexes for performance
            $table->index('document_id');
            $table->index('subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_subscription');
    }
};
