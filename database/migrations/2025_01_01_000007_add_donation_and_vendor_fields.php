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
        // Add parent_order_id to transactions for upsell/cartflows support
        Schema::table('officeguy_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_transaction_id')->nullable()->after('order_type');
            $table->string('vendor_id')->nullable()->after('parent_transaction_id');
            $table->boolean('is_upsell')->default(false)->after('vendor_id');
            $table->boolean('is_donation')->default(false)->after('is_upsell');
            $table->string('subscription_id')->nullable()->after('is_donation');
            
            $table->foreign('parent_transaction_id')
                ->references('id')
                ->on('officeguy_transactions')
                ->onDelete('set null');
        });
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
