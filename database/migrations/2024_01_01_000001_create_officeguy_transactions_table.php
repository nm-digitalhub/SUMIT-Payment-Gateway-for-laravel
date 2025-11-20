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
        Schema::create('officeguy_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->index(); // Can be any payable entity ID
            $table->string('order_type')->nullable(); // Morph type for flexible order relationships
            $table->string('payment_id')->nullable()->index(); // SUMIT payment ID
            $table->string('document_id')->nullable(); // SUMIT document ID
            $table->string('customer_id')->nullable(); // SUMIT customer ID
            $table->string('auth_number')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('first_payment_amount', 10, 2)->nullable();
            $table->decimal('non_first_payment_amount', 10, 2)->nullable();
            $table->string('currency', 3);
            $table->integer('payments_count')->default(1);
            $table->string('status')->default('pending'); // pending, completed, failed, refunded
            $table->string('payment_method')->default('card'); // card, bit
            $table->string('last_digits', 4)->nullable();
            $table->string('expiration_month', 2)->nullable();
            $table->string('expiration_year', 4)->nullable();
            $table->string('card_type')->nullable();
            $table->text('status_description')->nullable();
            $table->text('error_message')->nullable();
            $table->json('raw_request')->nullable(); // Store full request for debugging
            $table->json('raw_response')->nullable(); // Store full response for debugging
            $table->string('environment')->default('www'); // www, dev, test
            $table->boolean('is_test')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_transactions');
    }
};
