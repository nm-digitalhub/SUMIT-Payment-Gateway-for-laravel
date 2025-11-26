<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This migration adds helper columns that applications may use
 * for donation and multi-vendor features.
 *
 * NOTE: These columns are examples. Your application should add
 * similar columns to your own order/product/item tables as needed.
 *
 * For donations: add 'is_donation' boolean to your products table
 * For multi-vendor: add 'vendor_id' to your order items table
 * For upsell/cartflows: add 'parent_order_id' to your orders table
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('officeguy_transactions')) {
            return;
        }

        // Add parent_order_id to transactions for upsell/cartflows support
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('officeguy_transactions', 'parent_transaction_id')) {
                $table->unsignedBigInteger('parent_transaction_id')->nullable()->after('order_type');
            }

            if (! Schema::hasColumn('officeguy_transactions', 'vendor_id')) {
                $table->string('vendor_id')->nullable()->after('parent_transaction_id');
            }

            if (! Schema::hasColumn('officeguy_transactions', 'is_upsell')) {
                $table->boolean('is_upsell')->default(false)->after('vendor_id');
            }

            if (! Schema::hasColumn('officeguy_transactions', 'is_donation')) {
                $table->boolean('is_donation')->default(false)->after('is_upsell');
            }

            if (! Schema::hasColumn('officeguy_transactions', 'subscription_id')) {
                $table->string('subscription_id')->nullable()->after('is_donation');
            }
        });

        // Add foreign key separately (only if column was added and foreign key doesn't exist)
        if (Schema::hasColumn('officeguy_transactions', 'parent_transaction_id')) {
            $foreignKeys = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableForeignKeys('officeguy_transactions');

            $hasParentFk = collect($foreignKeys)->contains(function ($fk) {
                return in_array('parent_transaction_id', $fk->getColumns());
            });

            if (! $hasParentFk) {
                Schema::table('officeguy_transactions', function (Blueprint $table) {
                    $table->foreign('parent_transaction_id')
                        ->references('id')
                        ->on('officeguy_transactions')
                        ->onDelete('set null');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            $table->dropForeign(['parent_transaction_id']);
            $table->dropColumn([
                'parent_transaction_id',
                'vendor_id',
                'is_upsell',
                'is_donation',
                'subscription_id',
            ]);
        });
    }
};
