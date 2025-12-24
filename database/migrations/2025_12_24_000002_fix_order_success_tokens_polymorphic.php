<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix order_success_tokens table to use polymorphic relationship
     * instead of direct order_id column.
     */
    public function up(): void
    {
        // Check if payable_id and payable_type already exist
        $hasPayableColumns = Schema::hasColumns('order_success_tokens', ['payable_id', 'payable_type']);

        if (!$hasPayableColumns) {
            Schema::table('order_success_tokens', function (Blueprint $table) {
                // Add polymorphic columns
                $table->unsignedBigInteger('payable_id')->nullable()->after('id');
                $table->string('payable_type')->nullable()->after('payable_id');
            });
        }

        // Copy existing data from order_id to polymorphic columns (if not already done)
        DB::statement("
            UPDATE order_success_tokens
            SET payable_id = order_id,
                payable_type = 'App\\\\Models\\\\Order'
            WHERE order_id IS NOT NULL
            AND (payable_id IS NULL OR payable_type IS NULL)
        ");

        // Check if order_id still exists
        $hasOrderId = Schema::hasColumn('order_success_tokens', 'order_id');

        if ($hasOrderId) {
            Schema::table('order_success_tokens', function (Blueprint $table) {
                // Make columns non-nullable after data migration
                $table->unsignedBigInteger('payable_id')->nullable(false)->change();
                $table->string('payable_type')->nullable(false)->change();

                // Drop foreign key constraint first
                $table->dropForeign('order_success_tokens_order_id_foreign');

                // Drop old column
                $table->dropColumn('order_id');

                // Add index for polymorphic relationship
                $table->index(['payable_id', 'payable_type'], 'order_success_tokens_payable_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_success_tokens', function (Blueprint $table) {
            // Add back order_id column
            $table->unsignedBigInteger('order_id')->nullable()->after('id');
        });

        // Copy data back from polymorphic columns to order_id
        // (only for App\Models\Order type)
        DB::statement("
            UPDATE order_success_tokens
            SET order_id = payable_id
            WHERE payable_type = 'App\\\\Models\\\\Order'
        ");

        Schema::table('order_success_tokens', function (Blueprint $table) {
            // Drop polymorphic columns and index
            $table->dropIndex('order_success_tokens_payable_index');
            $table->dropColumn(['payable_id', 'payable_type']);

            // Make order_id non-nullable
            $table->unsignedBigInteger('order_id')->nullable(false)->change();

            // Restore foreign key constraint
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('cascade');
        });
    }
};
