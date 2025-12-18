<?php

/**
 * Hebrew (עברית) Translations for SUMIT Payment Gateway
 *
 * Usage in Filament:
 * protected static ?string $navigationLabel = __('officeguy::officeguy.nav.transactions');
 *
 * Usage in Blade:
 * {{ __('officeguy::officeguy.messages.payment_success') }}
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Labels
    |--------------------------------------------------------------------------
    */
    'nav' => [
        'group' => 'שער SUMIT',
        'transactions' => 'עסקאות',
        'tokens' => 'אמצעי תשלום',
        'documents' => 'מסמכים',
        'subscriptions' => 'מנויים',
        'vendor_credentials' => 'פרטי גישה לספקים',
        'webhook_events' => 'אירועי Webhook',
        'sumit_webhooks' => 'Webhooks מ-SUMIT',
        'settings' => 'הגדרות',
        'dashboard' => 'לוח בקרה',
        'my_payment_methods' => 'אמצעי התשלום שלי',
        'my_transactions' => 'העסקאות שלי',
        'my_documents' => 'המסמכים שלי',
        'payments_group' => 'תשלומים',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings Page - Sections
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'page_title' => 'הגדרות SUMIT',

        // API Credentials
        'api_credentials' => 'פרטי גישה ל-API',
        'company_id' => 'מזהה חברה',
        'private_key' => 'מפתח פרטי',
        'public_key' => 'מפתח ציבורי',

        // Environment
        'environment_settings' => 'הגדרות סביבה',
        'environment' => 'סביבה',
        'environment_production' => 'ייצור (www)',
        'environment_development' => 'פיתוח (dev)',
        'environment_testing' => 'בדיקות (test)',
        'pci_mode' => 'מצב PCI',
        'pci_simple' => 'פשוט (PaymentsJS)',
        'pci_redirect' => 'הפניה',
        'pci_advanced' => 'מתקדם (תואם PCI)',
        'testing_mode' => 'מצב בדיקות',
        'pci_mode_label' => 'מצב תאימות PCI',

        // Payment Configuration
        'payment_configuration' => 'הגדרות תשלום',
        'max_payments' => 'מספר תשלומים מקסימלי',
        'max_payments_help' => 'מקסימום תשלומים לחיוב (1-36)',
        'min_amount_for_payments' => 'סכום מינימלי לתשלומים',
        'min_amount_for_payments_help' => 'סכום מינימלי לאפשרות תשלומים (₪)',
        'min_amount_per_payment' => 'סכום מינימלי לתשלום בודד',
        'min_amount_per_payment_help' => 'סכום מינימלי לכל תשלום (₪)',
        'authorize_only' => 'אישור בלבד (ללא חיוב)',
        'authorize_only_help' => 'אשר תשלומים ללא חיוב מיידי',
        'authorize_added_percent' => 'אחוז הוספה לאישור',
        'authorize_added_percent_help' => 'אחוז נוסף לאישור (לדוגמה: 20%)',
        'authorize_minimum_addition' => 'תוספת מינימלית לאישור',
        'authorize_minimum_addition_help' => 'סכום מינימלי להוספה לאישור (₪)',

        // Merchant Numbers
        'merchant_numbers' => 'מספרי סוחר',
        'merchant_number' => 'מספר סוחר ראשי',
        'merchant_number_help' => 'מספר הסוחר לעסקאות רגילות',
        'subscriptions_merchant_number' => 'מספר סוחר למנויים',
        'subscriptions_merchant_number_help' => 'מספר סוחר ייעודי למנויים חוזרים',

        // Document Settings
        'document_settings' => 'הגדרות מסמכים',
        'draft_document' => 'מסמך טיוטה',
        'draft_document_help' => 'סוג מסמך לטיוטות',
        'email_document' => 'שליחת מסמך במייל',
        'email_document_help' => 'שלח מסמכים אוטומטית ללקוחות',
        'create_order_document' => 'יצירת מסמך בהזמנה',
        'create_order_document_help' => 'צור מסמך חשבונית/קבלה אוטומטית',

        // Language & Localization
        'language_localization' => 'שפה ולוקליזציה',
        'automatic_languages' => 'שפות אוטומטיות',
        'automatic_languages_help' => 'זיהוי אוטומטי של שפת הלקוח',

        // Customer Management
        'customer_management' => 'ניהול לקוחות',
        'merge_customers' => 'מיזוג לקוחות',
        'merge_customers_help' => 'מזג לקוחות כפולים אוטומטית',
        'customer_sync_enabled' => 'סנכרון לקוחות מופעל',
        'customer_model' => 'מודל לקוח',
        'customer_field_email' => 'שדה אימייל',
        'customer_field_name' => 'שדה שם',
        'customer_field_phone' => 'שדה טלפון',
        'customer_field_first_name' => 'שדה שם פרטי',
        'customer_field_last_name' => 'שדה שם משפחה',
        'customer_field_company' => 'שדה חברה',
        'customer_field_address' => 'שדה כתובת',
        'customer_field_city' => 'שדה עיר',
        'customer_field_sumit_id' => 'שדה מזהה SUMIT',

        // Token Configuration
        'token_configuration' => 'הגדרות טוקנים',
        'support_tokens' => 'תמיכה בטוקנים',
        'support_tokens_help' => 'אפשר שמירת אמצעי תשלום (J2/J5)',
        'token_param' => 'פרמטר טוקן',
        'token_param_help' => 'סוג טוקן: 2 (J2 - חד פעמי), 5 (J5 - רב פעמי)',
        'cvv' => 'דרישת CVV',
        'cvv_help' => 'האם שדה CVV חובה, אופציונלי או מוסתר',
        'cvv_required' => 'חובה (שדה CVV נדרש)',
        'cvv_optional' => 'אופציונלי (שדה CVV מוצג אך לא חובה)',
        'cvv_hidden' => 'מוסתר (שדה CVV לא מוצג)',
        'citizen_id' => 'דרישת מספר זהות',
        'citizen_id_help' => 'האם שדה ת.ז. חובה, אופציונלי או מוסתר',
        'citizen_id_required' => 'חובה (שדה ת.ז. נדרש)',
        'citizen_id_optional' => 'אופציונלי (שדה ת.ז. מוצג אך לא חובה)',
        'citizen_id_hidden' => 'מוסתר (שדה ת.ז. לא מוצג)',
        'four_digits_year' => 'שנה בת 4 ספרות',
        'four_digits_year_help' => 'השתמש בפורמט שנה בן 4 ספרות (2025)',

        // UI Settings
        'ui_settings' => 'הגדרות ממשק',
        'single_column_layout' => 'פריסת עמודה בודדת',
        'single_column_layout_help' => 'הצג שדות בעמודה אחת',

        // Bit Payment
        'bit_payment' => 'תשלום Bit',
        'bit_enabled' => 'אפשר Bit',
        'bit_enabled_help' => 'אפשר תשלומים באמצעות אפליקציית Bit',

        // Logging
        'logging' => 'רישום לוגים',
        'logging_enabled' => 'רישום מופעל',
        'logging_enabled_help' => 'רשום כל קריאות API ועסקאות',
        'log_channel' => 'ערוץ לוג',
        'log_channel_help' => 'ערוץ Laravel לרישום (stack, single, daily)',

        // SSL
        'ssl_settings' => 'הגדרות SSL',
        'ssl_verify' => 'אימות SSL',
        'ssl_verify_help' => 'אמת תעודות SSL בקריאות API (מומלץ בייצור)',

        // Stock Sync
        'stock_sync' => 'סנכרון מלאי',
        'stock_sync_freq' => 'תדירות סנכרון',
        'stock_sync_freq_help' => 'תדירות סנכרון מלאי אוטומטי',
        'stock_sync_none' => 'ללא',
        'stock_sync_12h' => 'כל 12 שעות',
        'stock_sync_24h' => 'יומי',
        'checkout_stock_sync' => 'סנכרון בקופה',
        'checkout_stock_sync_help' => 'סנכרן מלאי בתהליך התשלום',

        // Receipt Settings
        'receipt_settings' => 'הגדרות קבלות',
        'paypal_receipts' => 'קבלות PayPal',
        'paypal_receipts_help' => 'צור קבלות עבור תשלומי PayPal',
        'bluesnap_receipts' => 'קבלות BlueSnap',
        'bluesnap_receipts_help' => 'צור קבלות עבור תשלומי BlueSnap',
        'other_receipts' => 'קבלות אחרות',
        'other_receipts_help' => 'צור קבלות עבור שיטות תשלום אחרות',

        // Public Checkout
        'public_checkout' => 'עמוד תשלום ציבורי',
        'enable_public_checkout' => 'אפשר תשלום ציבורי',
        'enable_public_checkout_help' => 'אפשר עמוד תשלום ציבורי עם URL ייחודי',
        'public_checkout_path' => 'נתיב תשלום ציבורי',
        'public_checkout_path_help' => 'נתיב URL לעמוד התשלום הציבורי',
        'payable_model' => 'מודל לתשלום',
        'payable_model_help' => 'מודל Eloquent המיישם Payable',

        // Field Mapping
        'field_mapping' => 'מיפוי שדות',
        'field_map_amount' => 'שדה סכום',
        'field_map_currency' => 'שדה מטבע',
        'field_map_customer_name' => 'שדה שם לקוח',
        'field_map_customer_email' => 'שדה אימייל לקוח',
        'field_map_customer_phone' => 'שדה טלפון לקוח',
        'field_map_description' => 'שדה תיאור',

        // Webhooks (v1.2.0+)
        'webhook_configuration' => 'הגדרות Webhook (v1.2.0+)',
        'webhook_configuration_desc' => 'הגדר כיצד webhooks נשלחים. הגדרות אלה שולטות במערכת webhook מבוססת תורים שהוצגה ב-v1.2.0.',
        'webhook_async' => 'משלוח אסינכרוני (מבוסס תור)',
        'webhook_async_help' => 'אפשר משלוח webhook אסינכרוני דרך תורי Laravel (מומלץ לייצור)',
        'webhook_queue' => 'שם תור',
        'webhook_queue_help' => 'שם התור לשימוש עבור עבודות webhook',
        'webhook_max_tries' => 'ניסיונות חוזרים מקסימליים',
        'webhook_max_tries_help' => 'מספר פעמים לנסות שוב webhooks שנכשלו (backoff אקספוננציאלי: 10s, 100s, 1000s)',
        'webhook_timeout' => 'זמן קצוב לבקשה (שניות)',
        'webhook_timeout_help' => 'זמן קצוב לבקשת HTTP בשניות',
        'webhook_verify_ssl' => 'אמת תעודות SSL',
        'webhook_verify_ssl_help' => 'אמת תעודות SSL בעת שליחת webhooks (כבה רק לבדיקות)',

        // Custom Event Webhooks
        'custom_webhooks' => 'Webhooks מותאמים אישית',
        'webhook_payment_completed' => 'תשלום הושלם',
        'webhook_payment_completed_help' => 'URL ל-webhook כאשר תשלום הושלם',
        'webhook_payment_failed' => 'תשלום נכשל',
        'webhook_payment_failed_help' => 'URL ל-webhook כאשר תשלום נכשל',
        'webhook_document_created' => 'מסמך נוצר',
        'webhook_document_created_help' => 'URL ל-webhook כאשר מסמך נוצר',
        'webhook_subscription_created' => 'מנוי נוצר',
        'webhook_subscription_created_help' => 'URL ל-webhook כאשר מנוי נוצר',
        'webhook_subscription_charged' => 'מנוי חויב',
        'webhook_subscription_charged_help' => 'URL ל-webhook כאשר מנוי חויב',
        'webhook_bit_payment_completed' => 'תשלום Bit הושלם',
        'webhook_bit_payment_completed_help' => 'URL ל-webhook כאשר תשלום Bit הושלם',
        'webhook_stock_synced' => 'מלאי סונכרן',
        'webhook_stock_synced_help' => 'URL ל-webhook כאשר מלאי סונכרן',
        'webhook_secret' => 'סוד Webhook',
        'webhook_secret_help' => 'מפתח סודי לאימות חתימות webhook',

        // Customer Management (v1.2.4+)
        'customer_management' => 'ניהול לקוחות',
        'customer_management_desc' => 'הגדר כיצד לקוחות מסונכרנים ומנוהלים עם SUMIT',
        'customer_merging_enabled' => 'אפשר מיזוג לקוחות',
        'customer_merging_enabled_help' => 'כאשר מופעל, SUMIT ימזג אוטומטית לקוחות לפי אימייל/מזהה למניעת כפילויות',
        'customer_local_sync_enabled' => 'אפשר סנכרון לקוחות מקומי',
        'customer_local_sync_enabled_help' => 'סנכרן לקוחות SUMIT עם מודל הלקוחות המקומי שלך',
        'customer_model_class' => 'מחלקת מודל לקוח',
        'customer_model_class_help' => 'שם המחלקה המלא של מודל הלקוחות שלך (לדוגמה: App\\Models\\Client)',

        // Route Configuration
        'route_configuration' => 'הגדרות נתיבים',
        'routes_prefix' => 'קידומת נתיבים',
        'routes_prefix_help' => 'קידומת עבור כל נתיבי החבילה (ברירת מחדל: officeguy)',
        'routes_card_callback' => 'נתיב callback לכרטיס',
        'routes_card_callback_help' => 'נתיב ל-callback תשלומי כרטיס',
        'routes_bit_webhook' => 'נתיב webhook ל-Bit',
        'routes_bit_webhook_help' => 'נתיב ל-IPN webhooks של Bit',
        'routes_sumit_webhook' => 'נתיב webhook ל-SUMIT',
        'routes_sumit_webhook_help' => 'נתיב ל-webhooks נכנסים מ-SUMIT',
        'routes_enable_checkout_endpoint' => 'אפשר endpoint תשלום',
        'routes_enable_checkout_endpoint_help' => 'אפשר endpoint תשלום ציבורי',
        'routes_checkout_charge' => 'נתיב חיוב בקופה',
        'routes_checkout_charge_help' => 'נתיב לחיוב מיידי',
        'routes_document_download' => 'נתיב הורדת מסמך',
        'routes_document_download_help' => 'נתיב להורדת מסמכים',
        'routes_success' => 'נתיב הצלחה',
        'routes_success_help' => 'URL להפניה לאחר תשלום מוצלח',
        'routes_failed' => 'נתיב כישלון',
        'routes_failed_help' => 'URL להפניה לאחר תשלום שנכשל',

        // Subscriptions
        'subscriptions' => 'מנויים',
        'subscriptions_enabled' => 'מנויים מופעלים',
        'subscriptions_enabled_help' => 'אפשר תמיכה במנויים חוזרים',
        'subscriptions_default_interval' => 'מרווח ברירת מחדל (חודשים)',
        'subscriptions_default_interval_help' => 'מרווח חיוב ברירת מחדל (חודשי, שבועי)',
        'subscriptions_default_cycles' => 'מחזורים ברירת מחדל',
        'subscriptions_default_cycles_help' => 'מספר מחזורי חיוב (0 = אינסופי)',
        'subscriptions_allow_pause' => 'אפשר השהייה',
        'subscriptions_allow_pause_help' => 'אפשר ללקוחות להשהות מנויים',
        'subscriptions_retry_failed' => 'נסה שוב חיובים שנכשלו',
        'subscriptions_retry_failed_help' => 'נסה שוב אוטומטית חיובי מנויים שנכשלו',
        'subscriptions_max_retries' => 'ניסיונות חוזרים מקסימליים',
        'subscriptions_max_retries_help' => 'מספר ניסיונות חוזרים עבור מנויים שנכשלו',

        // Donations
        'donations' => 'תרומות',
        'donations_enabled' => 'תרומות מופעלות',
        'donations_enabled_help' => 'אפשר תמיכה בתרומות',
        'donations_allow_mixed' => 'אפשר מעורבות',
        'donations_allow_mixed_help' => 'אפשר תרומות מעורבות עם רכישות',
        'donations_default_document_type' => 'סוג מסמך ברירת מחדל',
        'donations_default_document_type_help' => 'סוג מסמך לתרומות (קבלה, חשבונית)',

        // Multi-Vendor
        'multivendor' => 'ריבוי ספקים',
        'multivendor_enabled' => 'ריבוי ספקים מופעל',
        'multivendor_enabled_help' => 'אפשר תמיכה בריבוי ספקים',
        'multivendor_validate_credentials' => 'אמת אישורים',
        'multivendor_validate_credentials_help' => 'אמת אישורי ספק בעת שמירה',
        'multivendor_allow_authorize' => 'אפשר אישור',
        'multivendor_allow_authorize_help' => 'אפשר מצב אישור בלבד לספקים',

        // Upsell / CartFlows
        'upsell' => 'Upsell / CartFlows',
        'upsell_enabled' => 'Upsell מופעל',
        'upsell_enabled_help' => 'אפשר תכונות upsell (CartFlows)',
        'upsell_require_token' => 'דרוש טוקן',
        'upsell_require_token_help' => 'דרוש טוקן תשלום עבור upsells',
        'upsell_max_per_order' => 'מקסימום להזמנה',
        'upsell_max_per_order_help' => 'מספר מקסימלי של upsells להזמנה',

        // Actions
        'save' => 'שמור הגדרות',
        'test_connection' => 'בדוק חיבור',
        'reset_to_defaults' => 'אפס לברירת מחדל',
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages & Notifications
    |--------------------------------------------------------------------------
    */
    'messages' => [
        'payment_success' => 'התשלום הושלם בהצלחה',
        'payment_failed' => 'התשלום נכשל',
        'payment_pending' => 'התשלום ממתין לאישור',
        'token_saved' => 'אמצעי התשלום נשמר בהצלחה',
        'token_deleted' => 'אמצעי התשלום נמחק',
        'document_created' => 'המסמך נוצר בהצלחה',
        'document_sent' => 'המסמך נשלח ללקוח',
        'subscription_created' => 'המנוי נוצר בהצלחה',
        'subscription_cancelled' => 'המנוי בוטל',
        'subscription_paused' => 'המנוי הושהה',
        'subscription_resumed' => 'המנוי חודש',
        'settings_saved' => 'ההגדרות נשמרו בהצלחה',
        'settings_reset' => 'ההגדרות אופסו לברירת מחדל',
        'connection_success' => 'החיבור ל-SUMIT API הצליח',
        'connection_failed' => 'החיבור ל-SUMIT API נכשל',
        'webhook_sent' => 'Webhook נשלח בהצלחה',
        'webhook_failed' => 'שליחת Webhook נכשלה',
        'stock_synced' => 'המלאי סונכרן בהצלחה',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Labels
    |--------------------------------------------------------------------------
    */
    'status' => [
        'pending' => 'ממתין',
        'processing' => 'מעבד',
        'completed' => 'הושלם',
        'failed' => 'נכשל',
        'cancelled' => 'בוטל',
        'refunded' => 'הוחזר',
        'active' => 'פעיל',
        'inactive' => 'לא פעיל',
        'paused' => 'מושהה',
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Labels (Common)
    |--------------------------------------------------------------------------
    */
    'fields' => [
        'id' => 'מזהה',
        'created_at' => 'נוצר ב',
        'updated_at' => 'עודכן ב',
        'amount' => 'סכום',
        'currency' => 'מטבע',
        'status' => 'סטטוס',
        'transaction_id' => 'מזהה עסקה',
        'token' => 'טוקן',
        'description' => 'תיאור',
        'customer_name' => 'שם לקוח',
        'customer_email' => 'אימייל לקוח',
        'customer_phone' => 'טלפון לקוח',
        'card_last_4' => '4 ספרות אחרונות',
        'card_type' => 'סוג כרטיס',
        'expiry_date' => 'תוקף',
        'document_number' => 'מספר מסמך',
        'document_type' => 'סוג מסמך',
        'invoice' => 'חשבונית',
        'receipt' => 'קבלה',
        'donation_receipt' => 'קבלה לתרומה',
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions & Buttons
    |--------------------------------------------------------------------------
    */
    'actions' => [
        'create' => 'צור',
        'edit' => 'ערוך',
        'delete' => 'מחק',
        'view' => 'צפה',
        'save' => 'שמור',
        'cancel' => 'ביטול',
        'back' => 'חזור',
        'download' => 'הורד',
        'send' => 'שלח',
        'retry' => 'נסה שוב',
        'refund' => 'החזר',
        'capture' => 'חייב',
        'pause' => 'השהה',
        'resume' => 'חדש',
        'test_connection' => 'בדוק חיבור',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'required' => 'שדה :attribute הוא שדה חובה.',
        'numeric' => 'שדה :attribute חייב להיות מספר.',
        'email' => 'שדה :attribute חייב להיות כתובת אימייל תקינה.',
        'url' => 'שדה :attribute חייב להיות URL תקין.',
        'min' => 'שדה :attribute חייב להיות לפחות :min.',
        'max' => 'שדה :attribute לא יכול להיות יותר מ-:max.',
        'between' => 'שדה :attribute חייב להיות בין :min ל-:max.',
    ],
];
