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
        if (!Schema::hasTable('officeguy_tokens')) {
            return;
        }

        Schema::table('officeguy_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('officeguy_tokens', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('metadata');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('officeguy_tokens')) {
            return;
        }

        Schema::table('officeguy_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('officeguy_tokens', 'admin_notes')) {
                $table->dropColumn('admin_notes');
            }
        });
    }
};
