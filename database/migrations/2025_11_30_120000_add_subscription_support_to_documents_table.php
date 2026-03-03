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
        // First, make order_id nullable (for subscription documents)
        Schema::table('officeguy_documents', function (Blueprint $table) {
            $table->string('order_id')->nullable()->change();
        });

        Schema::table('officeguy_documents', function (Blueprint $table) {
            // Check if columns don't already exist before adding
            if (! Schema::hasColumn('officeguy_documents', 'subscription_id')) {
                // Add subscription_id foreign key
                $table->unsignedBigInteger('subscription_id')
                    ->nullable()
                    ->after('order_type')
                    ->comment('Link to officeguy_subscriptions table');
            }

            if (! Schema::hasColumn('officeguy_documents', 'external_reference')) {
                // Add external_reference from SUMIT (for linking)
                $table->string('external_reference')
                    ->nullable()
                    ->after('description')
                    ->comment('External reference from SUMIT (e.g., subscription_123)');
            }

            if (! Schema::hasColumn('officeguy_documents', 'document_download_url')) {
                // Add document URLs from SUMIT
                $table->string('document_download_url', 500)
                    ->nullable()
                    ->after('external_reference')
                    ->comment('Direct PDF download URL from SUMIT');
            }

            if (! Schema::hasColumn('officeguy_documents', 'document_payment_url')) {
                $table->string('document_payment_url', 500)
                    ->nullable()
                    ->after('document_download_url')
                    ->comment('Payment URL from SUMIT for open invoices');
            }

            if (! Schema::hasColumn('officeguy_documents', 'document_number')) {
                // Add document number and date from SUMIT
                $table->bigInteger('document_number')
                    ->nullable()
                    ->after('document_id')
                    ->comment('Document number from SUMIT');
            }

            if (! Schema::hasColumn('officeguy_documents', 'document_date')) {
                $table->timestamp('document_date')
                    ->nullable()
                    ->after('document_number')
                    ->comment('Document date from SUMIT');
            }

            if (! Schema::hasColumn('officeguy_documents', 'is_closed')) {
                $table->boolean('is_closed')
                    ->default(false)
                    ->after('is_draft')
                    ->comment('Whether document is closed/paid');
            }
        });

        // Add indexes via Schema Builder (portable: MySQL + PostgreSQL)
        Schema::table('officeguy_documents', function (Blueprint $table) {
            if (Schema::hasColumn('officeguy_documents', 'subscription_id') && Schema::hasColumn('officeguy_documents', 'created_at')) {
                $table->index(['subscription_id', 'created_at'], 'officeguy_documents_subscription_id_created_at');
            }
            if (Schema::hasColumn('officeguy_documents', 'customer_id') && Schema::hasColumn('officeguy_documents', 'document_date')) {
                $table->index(['customer_id', 'document_date'], 'officeguy_documents_customer_id_document_date');
            }
            if (Schema::hasColumn('officeguy_documents', 'external_reference')) {
                $table->index('external_reference', 'officeguy_documents_external_reference');
            }
        });

        // Foreign key (only if subscriptions table exists)
        if (Schema::hasTable('officeguy_subscriptions') && Schema::hasColumn('officeguy_documents', 'subscription_id')) {
            Schema::table('officeguy_documents', function (Blueprint $table) {
                $table->foreign('subscription_id')
                    ->references('id')
                    ->on('officeguy_subscriptions')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('officeguy_documents', function (Blueprint $table) {
            if (Schema::hasTable('officeguy_subscriptions') && Schema::hasColumn('officeguy_documents', 'subscription_id')) {
                $table->dropForeign(['subscription_id']);
            }
        });

        Schema::table('officeguy_documents', function (Blueprint $table) {
            $table->dropIndex('officeguy_documents_subscription_id_created_at');
            $table->dropIndex('officeguy_documents_customer_id_document_date');
            $table->dropIndex('officeguy_documents_external_reference');
        });

        Schema::table('officeguy_documents', function (Blueprint $table) {
            // Drop columns if they exist
            $columnsToCheck = [
                'subscription_id',
                'external_reference',
                'document_download_url',
                'document_payment_url',
                'document_number',
                'document_date',
                'is_closed',
            ];

            $columnsToDrop = [];
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('officeguy_documents', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
