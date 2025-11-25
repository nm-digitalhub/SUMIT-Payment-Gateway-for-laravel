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
        Schema::create('officeguy_vendor_credentials', function (Blueprint $table) {
            $table->id();
            $table->morphs('vendor'); // polymorphic relation to User, Vendor, or any entity
            $table->string('company_id'); // SUMIT Company ID for the vendor
            $table->string('api_key'); // SUMIT API Key for the vendor
            $table->string('public_key')->nullable(); // SUMIT Public API Key
            $table->string('merchant_number')->nullable(); // Optional merchant number
            $table->boolean('is_active')->default(true);
            $table->string('validation_status')->nullable(); // Status of credential validation
            $table->text('validation_message')->nullable(); // Validation error message if any
            $table->timestamp('validated_at')->nullable();
            $table->json('metadata')->nullable(); // Additional vendor metadata
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_type', 'vendor_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_vendor_credentials');
    }
};
