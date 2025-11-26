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
        if (Schema::hasTable('officeguy_tokens')) {
            return;
        }

        Schema::create('officeguy_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner'); // polymorphic relation to User or Customer
            $table->string('token')->unique(); // SUMIT card token
            $table->string('gateway_id')->default('officeguy'); // officeguy or officeguybit
            $table->string('card_type')->default('card'); // card brand if available
            $table->string('last_four', 4);
            $table->string('citizen_id')->nullable(); // Israeli ID number
            $table->string('expiry_month', 2);
            $table->string('expiry_year', 4);
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();
            $table->softDeletes();

            // Index for finding default tokens per owner
            $table->index(['owner_type', 'owner_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officeguy_tokens');
    }
};
