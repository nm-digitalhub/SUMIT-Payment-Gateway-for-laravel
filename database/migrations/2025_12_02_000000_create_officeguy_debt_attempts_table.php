<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('officeguy_debt_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('crm_entity_id')->nullable()->index();
            $table->unsignedBigInteger('sumit_customer_id')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officeguy_debt_attempts');
    }
};
