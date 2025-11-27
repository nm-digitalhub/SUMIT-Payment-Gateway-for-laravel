<?php

/**
 * Final translation pass - translate remaining strings
 */

$filePath = __DIR__ . '/../src/Filament/Pages/OfficeGuySettings.php';
$content = file_get_contents($filePath);

$translations = [
    // Subscriptions
    "'Default Interval (Months)'" => "__('officeguy::officeguy.settings.subscriptions_default_interval')",
    "'Retry Failed Charges'" => "__('officeguy::officeguy.settings.subscriptions_retry_failed')",
    "'Leave empty for unlimited'" => "'השאר ריק ללא הגבלה'",
    "'Unlimited'" => "'ללא הגבלה'",

    // Donations
    "'Allow Mixed Cart'" => "__('officeguy::officeguy.settings.donations_allow_mixed')",
    "'Allow donations with regular products'" => "'אפשר תרומות יחד עם מוצרים רגילים'",

    // Upsell
    "'Require Saved Token'" => "__('officeguy::officeguy.settings.upsell_require_token')",
    "'Max Upsells Per Order'" => "__('officeguy::officeguy.settings.upsell_max_per_order')",

    // Additional Features section
    "Section::make('Additional Features')" => "Section::make('תכונות נוספות')",

    // Public Checkout
    "'Checkout Path'" => "__('officeguy::officeguy.settings.public_checkout_path')",
    "'Custom path for checkout page (default: checkout/{id})'" => "'נתיב מותאם אישית לעמוד התשלום (ברירת מחדל: checkout/{id})'",
    "'Payable Model Class'" => "__('officeguy::officeguy.settings.payable_model')",
    "'Full class name of your model (e.g., App\\\\Models\\\\Order). Model can implement Payable interface OR use field mapping below.'" => "'שם המחלקה המלא של המודל (לדוגמה: App\\\\Models\\\\Order). המודל יכול ליישם את ממשק Payable או להשתמש במיפוי שדות למטה.'",

    // Field Mapping section
    "Section::make('Field Mapping (Optional)')" => "Section::make('מיפוי שדות (אופציונלי)')",
    "'Map your model fields to payment fields. Only fill these if your model does NOT implement the Payable interface.'" => "'מפה את שדות המודל לשדות תשלום. מלא רק אם המודל שלך לא מיישם את ממשק Payable.'",
    "'Field name for payment amount'" => "'שם שדה לסכום התשלום'",
    "'Field name for currency (or leave empty for ILS)'" => "'שם שדה למטבע (או השאר ריק עבור ₪)'",
    "'Field name for customer name'" => "'שם שדה לשם לקוח'",
    "'Field name for customer email'" => "'שם שדה לאימייל לקוח'",
    "'Field name for customer phone'" => "'שם שדה לטלפון לקוח'",
    "'Field name for item description'" => "'שם שדה לתיאור פריט'",
];

foreach ($translations as $old => $new) {
    $content = str_replace($old, $new, $content);
}

file_put_contents($filePath, $content);

echo "✅ Final translations applied\n";
echo "Total: " . count($translations) . " replacements\n";
