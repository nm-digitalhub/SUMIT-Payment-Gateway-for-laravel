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
        if (Schema::hasTable('officeguy_documents')) {
            return;
        }

        Schema::create('officeguy_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_id')->unique(); // SUMIT document ID
            $table->string('order_id')->index(); // Related order/payable ID
            $table->string('order_type')->nullable(); // Morph type
            $table->string('customer_id')->nullable(); // SUMIT customer ID
            $table->string('document_type')->default('1'); // 1=invoice, 8=order, etc.
            $table->boolean('is_draft')->default(false);
            $table->string('language')->nullable(); // Hebrew, English, etc.
            $table->string('currency', 3);
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->boolean('emailed')->default(false);
            $table->json('raw_response')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_documents');
    }
};
