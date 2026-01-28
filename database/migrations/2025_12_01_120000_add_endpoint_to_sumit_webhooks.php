<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('officeguy_sumit_webhooks', function (Blueprint $table) {
            if (! Schema::hasColumn('officeguy_sumit_webhooks', 'endpoint')) {
                $table->string('endpoint', 190)->nullable()->after('card_type')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('officeguy_sumit_webhooks', function (Blueprint $table) {
            if (Schema::hasColumn('officeguy_sumit_webhooks', 'endpoint')) {
                $table->dropColumn('endpoint');
            }
        });
    }
};
