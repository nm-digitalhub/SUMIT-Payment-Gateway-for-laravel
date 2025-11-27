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
        Schema::create('payable_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('model_class')->unique()->comment('Fully qualified model class name');
            $table->string('label')->nullable()->comment('User-friendly label for this mapping');
            $table->json('field_mappings')->comment('JSON mapping of Payable fields to model fields');
            $table->boolean('is_active')->default(true)->comment('Whether this mapping is active');
            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payable_field_mappings');
    }
};
