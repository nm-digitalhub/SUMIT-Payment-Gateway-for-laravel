<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('officeguy.company_id', 0);
        $this->migrator->add('officeguy.private_key', '');
        $this->migrator->add('officeguy.public_key', '');
        $this->migrator->add('officeguy.environment', 'www');

        $this->migrator->add('officeguy.pci', 'no');
        $this->migrator->add('officeguy.testing', false);

        $this->migrator->add('officeguy.max_payments', 1);
        $this->migrator->add('officeguy.min_amount_for_payments', 0.0);
        $this->migrator->add('officeguy.min_amount_per_payment', 0.0);

        $this->migrator->add('officeguy.authorize_only', false);
        $this->migrator->add('officeguy.authorize_added_percent', 0.0);
        $this->migrator->add('officeguy.authorize_minimum_addition', 0.0);

        $this->migrator->add('officeguy.merchant_number', null);
        $this->migrator->add('officeguy.subscriptions_merchant_number', null);

        $this->migrator->add('officeguy.draft_document', false);
        $this->migrator->add('officeguy.email_document', true);
        $this->migrator->add('officeguy.create_order_document', false);
        $this->migrator->add('officeguy.automatic_languages', true);
        $this->migrator->add('officeguy.merge_customers', false);

        $this->migrator->add('officeguy.support_tokens', true);
        $this->migrator->add('officeguy.token_param', '5');

        $this->migrator->add('officeguy.citizen_id', 'required');
        $this->migrator->add('officeguy.cvv', 'required');
        $this->migrator->add('officeguy.four_digits_year', true);
        $this->migrator->add('officeguy.single_column_layout', true);

        $this->migrator->add('officeguy.bit_enabled', false);

        $this->migrator->add('officeguy.logging', false);
        $this->migrator->add('officeguy.log_channel', 'stack');

        $this->migrator->add('officeguy.stock_sync_freq', 'none');
        $this->migrator->add('officeguy.checkout_stock_sync', false);

        $this->migrator->add('officeguy.paypal_receipts', 'no');
        $this->migrator->add('officeguy.bluesnap_receipts', false);
        $this->migrator->add('officeguy.other_receipts', null);

        $this->migrator->add('officeguy.supported_currencies', [
            'ILS', 'USD', 'EUR', 'CAD', 'GBP', 'CHF', 'AUD', 'JPY', 'SEK', 'NOK',
            'DKK', 'ZAR', 'JOD', 'LBP', 'EGP', 'BGN', 'CZK', 'HUF', 'PLN', 'RON',
            'ISK', 'HRK', 'RUB', 'TRY', 'BRL', 'CNY', 'HKD', 'IDR', 'INR', 'KRW',
            'MXN', 'MYR', 'NZD', 'PHP', 'SGD', 'THB'
        ]);

        $this->migrator->add('officeguy.ssl_verify', true);
    }
};
