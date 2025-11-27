# Payable Field Mapping Wizard - Documentation

**Created**: 2025-01-27
**Package**: officeguy/laravel-sumit-gateway
**Feature**: Dynamic Wizard-Based Payable Field Mapping

---

## 📋 Overview

This feature provides a **wizard-based UI** for creating custom field mappings between any Laravel model and the `Payable` interface, without modifying the model or implementing the interface directly.

### Problem Solved

Models like `MayaNetEsimProduct` have different field names than what the `Payable` interface expects:
- Payable expects `total` → Model has `final_price_ils`
- Payable expects `customer_name` → Model has `order.client.name` (nested relationship)
- Payable expects `currency` → Model needs constant value `"ILS"`

### Solution

A 3-step wizard that lets developers:
1. **Select Model** - Choose the model class with validation
2. **Map Fields** - Map each of the 16 Payable fields to model fields
3. **Review & Save** - Preview the complete mapping before saving

---

## 🎨 Filament v4 Features Used

### Advanced Components
- ✅ **Wizard Steps** - Multi-step form with progress indicator
- ✅ **Live Validation** - `live(onBlur: true)` for instant feedback
- ✅ **Reactive Fields** - `afterStateUpdated()` with `$get`/`$set`
- ✅ **Dynamic Select Options** - `createOptionForm()` + `createOptionUsing()`
- ✅ **Collapsible Sections** - Organized layout for required vs optional fields
- ✅ **Suffix Icons** - Visual feedback (✓ for valid, ✗ for invalid)
- ✅ **Placeholder Components** - Dynamic content display
- ✅ **Fieldset Layouts** - Grouped form fields
- ✅ **Grid Layouts** - Responsive 2-column layouts
- ✅ **Notifications** - Success messages with icons
- ✅ **Table Widget** - CRUD interface for existing mappings
- ✅ **Modal Actions** - View, edit, delete mappings

### Performance Optimizations
- Client-side field lookup (no additional DB queries)
- Efficient caching of model reflection data
- Minimal re-renders with targeted state updates

---

## 📁 File Structure

### Backend (5 files)

```
src/
├── Models/
│   └── PayableFieldMapping.php               # 115 lines - Eloquent model
├── Services/
│   └── PayableMappingService.php             # 280 lines - Business logic
├── Support/
│   └── DynamicPayableWrapper.php             # 260 lines - Wrapper class
├── Filament/
│   ├── Actions/
│   │   └── CreatePayableMappingAction.php    # 380 lines - Wizard action
│   └── Widgets/
│       └── PayableMappingsTableWidget.php    # 145 lines - Table widget
database/migrations/
└── 2025_01_27_000001_create_payable_field_mappings_table.php
```

### Frontend (3 files)

```
resources/views/components/
├── model-info.blade.php                      # Model validation display
├── mapping-review.blade.php                  # Step 3 summary
└── mapping-details.blade.php                 # View mapping modal
```

### Integration (1 file)

```
src/Filament/Pages/
└── OfficeGuySettings.php                     # +10 lines only!
```

---

## 🚀 Usage Guide

### Step 1: Access the Wizard

1. Navigate to **Admin Panel → Office Guy Settings**
2. Click the **"הוסף מיפוי Payable חדש"** button (top-right, green)
3. Wizard modal opens (7xl width, 3 steps)

### Step 2: Select Model

**Field**: Model Class (with validation)
- Enter full class name: `App\Models\MayaNetEsimProduct`
- Live validation checks if class exists
- ✓ Success: Green icon, shows model info (file path, fillable fields)
- ✗ Error: Red icon, shows error message

**Optional**: Mapping Label
- User-friendly label for identifying this mapping later
- Example: "eSIM Product Mapping"

### Step 3: Map Fields

Two collapsible sections:

#### Required Fields (9 fields)
Must be mapped for Payable to work:
- `payable_id` → Unique ID (usually `id`)
- `amount` → Payment amount (`final_price_ils`)
- `currency` → Currency code (`ILS`)
- `customer_name` → Customer name (`order.client.name`)
- `line_items` → Order items (`[]` for empty array)
- `shipping_amount` → Shipping cost (`0`)
- `fees` → Additional fees (`[]`)
- `tax_enabled` → Tax calculation (`true`)
- (1 more required field)

#### Optional Fields (7 fields)
Can be left empty:
- `customer_email`, `customer_phone`, `customer_id`
- `customer_address`, `customer_company`, `customer_note`
- `shipping_method`, `vat_rate`

**Mapping Options**:

1. **Direct Field**: `final_price_ils`
2. **Dot Notation** (nested): `order.client.name`
3. **Constant String**: `"ILS"` (with quotes)
4. **Numeric**: `0`, `0.17`
5. **Boolean**: `true`, `false`
6. **JSON Array**: `[]`, `[1,2,3]`
7. **Null**: Leave empty or select `null`
8. **Custom Value**: Click "+ Create option" to enter any value

**Pre-defined Options**:
- Model fillable fields (blue)
- Common relationships (purple): `order.name`, `client.email`
- Constants (green): `"ILS"`, `0`, `0.17`, `true`, `false`, `[]`, `null`

### Step 4: Review & Save

**Summary displays**:
- Model class and label
- Stats: X mapped / Y empty / 16 total
- All non-empty mappings in a scrollable list
- Empty mappings in collapsed details

**Actions**:
- **Save**: Creates the mapping in database
- **Back**: Return to previous step
- **Cancel**: Close wizard without saving

---

## 💾 Database Schema

### Table: `payable_field_mappings`

```sql
CREATE TABLE payable_field_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_class VARCHAR(255) UNIQUE NOT NULL COMMENT 'Fully qualified model class',
    label VARCHAR(255) NULL COMMENT 'User-friendly label',
    field_mappings JSON NOT NULL COMMENT 'Payable field → model field mapping',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether mapping is active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_is_active (is_active)
);
```

### Example Record

```json
{
  "id": 1,
  "model_class": "App\\Models\\MayaNetEsimProduct",
  "label": "eSIM Product Mapping",
  "field_mappings": {
    "payable_id": "id",
    "amount": "final_price_ils",
    "currency": "ILS",
    "customer_name": "order.client.name",
    "customer_email": "order.client.email",
    "customer_phone": "order.client.phone",
    "customer_id": "order.client_id",
    "customer_address": null,
    "customer_company": null,
    "customer_note": "description",
    "line_items": "[]",
    "shipping_amount": "0",
    "shipping_method": null,
    "fees": "[]",
    "vat_rate": "0.17",
    "tax_enabled": "true"
  },
  "is_active": true,
  "created_at": "2025-01-27 10:30:00",
  "updated_at": "2025-01-27 10:30:00"
}
```

---

## 🔧 Backend API

### PayableMappingService

```php
use OfficeGuy\LaravelSumitGateway\Services\PayableMappingService;

$service = app(PayableMappingService::class);

// Get mapping for a model
$mappings = $service->getMappingForModel(MayaNetEsimProduct::class);
// Returns: ['amount' => 'final_price_ils', ...] or null

// Create or update mapping
$mapping = $service->upsertMapping(
    modelClass: 'App\\Models\\MayaNetEsimProduct',
    fieldMappings: ['amount' => 'final_price_ils', ...],
    label: 'eSIM Product Mapping'
);

// Delete mapping
$service->deleteMapping('App\\Models\\MayaNetEsimProduct');

// Get all active mappings
$all = $service->getAllMappings(); // Collection<PayableFieldMapping>

// Get field metadata (16 fields with descriptions)
$fields = $service->getPayableFields();
// Returns: [
//   'amount' => [
//     'key' => 'amount',
//     'label_he' => 'סכום לתשלום',
//     'label_en' => 'Payment Amount',
//     'method' => 'getPayableAmount()',
//     'return_type' => 'float',
//     'required' => true,
//     'example' => 'final_price_ils',
//     'category' => 'core',
//     'description_he' => '...',
//   ],
//   ...
// ]

// Get fields by category
$byCategory = $service->getPayableFieldsByCategory();
// Returns: ['core' => [...], 'customer' => [...], 'items' => [...], 'tax' => [...]]

// Get only required/optional fields
$required = $service->getRequiredPayableFields();
$optional = $service->getOptionalPayableFields();
```

### DynamicPayableWrapper

```php
use OfficeGuy\LaravelSumitGateway\Support\DynamicPayableWrapper;

// Wrap any model (automatically loads mapping from DB)
$esim = MayaNetEsimProduct::find(1);
$payable = new DynamicPayableWrapper($esim);

// Now use as Payable
$amount = $payable->getPayableAmount();      // → $esim->final_price_ils
$currency = $payable->getPayableCurrency();  // → 'ILS'
$name = $payable->getCustomerName();        // → $esim->order->client->name
$email = $payable->getCustomerEmail();      // → $esim->order->client->email

// Pass to SUMIT services
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
$payment = app(PaymentService::class)->createCharge($payable);
```

### PayableFieldMapping Model

```php
use OfficeGuy\LaravelSumitGateway\Models\PayableFieldMapping;

// Query mappings
$mapping = PayableFieldMapping::forModel('App\\Models\\MayaNetEsimProduct')
    ->active()
    ->first();

// Check if valid
$mapping->isModelValid(); // true if class exists

// Get specific mapping
$amountField = $mapping->getMapping('amount'); // 'final_price_ils'

// Update single field
$mapping->updateMapping('amount', 'new_field_name');

// Scopes
PayableFieldMapping::active()->get();
PayableFieldMapping::forModel($modelClass)->get();

// Attributes
$mapping->mapped_fields_count; // Count of non-null mappings
$mapping->short_model_name;    // class_basename()
```

---

## 🎯 Supported Value Types

| Type | Example | Use Case |
|------|---------|----------|
| **Direct Field** | `final_price_ils` | Model has this exact field |
| **Dot Notation** | `order.client.name` | Nested relationship |
| **Constant String** | `"ILS"` | Fixed value (must include quotes) |
| **Numeric** | `0`, `0.17`, `100` | Numbers without quotes |
| **Boolean** | `true`, `false` | Boolean values as strings |
| **JSON Array** | `[]`, `[1,2,3]` | Array literals |
| **Null** | `null` or empty | Field not mapped |

---

## 📊 Widget Features

### PayableMappingsTableWidget

Located at bottom of **Office Guy Settings** page.

**Columns**:
- **Label** - User-friendly name with tag icon
- **Model Class** - Short name (hover for full path), copyable
- **Active** - Boolean icon (green ✓ / red ✗)
- **Mapped Fields** - Badge showing "X / 16"
- **Created At** - Date/time
- **Updated At** - Relative time (hidden by default)

**Actions (per row)**:
1. **View** (👁️) - Opens modal with full mapping details:
   - Stats: ID, mapped count, total count, status
   - Model info (class, file path)
   - Mappings organized by category (Core, Customer, Items, Tax)
   - Color-coded sections
   - Creation/update timestamps

2. **Toggle Active** (⏸️/▶️) - Activate/deactivate mapping
   - Requires confirmation
   - Updates `is_active` flag
   - Shows success notification

3. **Delete** (🗑️) - Remove mapping
   - Requires confirmation
   - Permanent deletion
   - Shows success notification

**Bulk Actions**:
- **Activate** - Enable multiple mappings
- **Deactivate** - Disable multiple mappings
- **Delete** - Remove multiple mappings

**Empty State**:
- Icon: arrows-right-left
- Message: "אין מיפויים קיימים"
- Description: "צור מיפוי ראשון על ידי לחיצה על 'הוסף מיפוי Payable חדש' למעלה"

**Sorting**:
- Default: `created_at DESC` (newest first)
- All columns sortable
- Searchable: Label, Model Class

---

## 🔍 Example: MayaNetEsimProduct

### Model Structure

```php
class MayaNetEsimProduct extends Model
{
    protected $fillable = [
        'wholesale_price_usd',
        'price_ils',
        'markup_percentage',
        'final_price_ils',      // ← Amount
        'billing_cycle',
        'name',
        'description',
        'data_quota_mb',
        'validity_days',
        'countries_enabled',
    ];

    public function orders()
    {
        return $this->morphMany(Order::class, 'packageable');
    }
}
```

### Wizard Mapping Steps

**Step 1: Model Selection**
```
Model Class: App\Models\MayaNetEsimProduct
Mapping Label: eSIM Product Mapping
✓ Model valid - shows 11 fillable fields
```

**Step 2: Field Mapping**

Required fields:
- `payable_id` → `id`
- `amount` → `final_price_ils`
- `currency` → `"ILS"` (constant)
- `customer_name` → `order.client.name` (nested)
- `customer_email` → `order.client.email`
- `line_items` → `[]` (empty array)
- `shipping_amount` → `0`
- `fees` → `[]`
- `tax_enabled` → `true`

Optional fields:
- `customer_phone` → `order.client.phone`
- `customer_id` → `order.client_id`
- `customer_note` → `description`
- `vat_rate` → `0.17`
- Others → null (not mapped)

**Step 3: Review**
- Stats: 13 mapped / 3 empty / 16 total
- Preview all mappings
- Click "Save"

### Result

```php
// Usage
$esim = MayaNetEsimProduct::find(1);
$payable = new DynamicPayableWrapper($esim);

// These now work automatically:
$payable->getPayableAmount();     // → $esim->final_price_ils (99.90)
$payable->getPayableCurrency();   // → 'ILS'
$payable->getCustomerName();      // → $esim->order->client->name ('John Doe')
$payable->getCustomerEmail();     // → $esim->order->client->email ('john@example.com')
$payable->getLineItems();         // → []
$payable->isTaxEnabled();         // → true
$payable->getVatRate();           // → 0.17

// Pass to payment service
$payment = app(PaymentService::class)->createCharge($payable);
```

---

## 🎨 UI/UX Features

### Visual Hierarchy
- **Blue** - Core payment info
- **Purple** - Customer information
- **Green** - Items & costs
- **Yellow** - Tax settings

### Responsive Design
- 7xl modal width for wizard
- 5xl modal width for view action
- Grid layouts adapt to screen size
- Scrollable mapping lists (max-height: 96)

### Dark Mode Support
- All components support dark mode
- Proper contrast ratios
- Color-coded badges work in both modes

### Icons (Heroicons)
- ✓ Check circle - Success
- ✗ X circle - Error
- 👁️ Eye - View
- ⏸️ Pause - Deactivate
- ▶️ Play - Activate
- 🗑️ Trash - Delete
- ➡️ Arrow - Mapping direction
- 📦 Cube - Model
- ↔️ Arrows - Field mapping

### Accessibility
- ARIA labels on all interactive elements
- Keyboard navigation support
- Screen reader friendly
- Color is not the only indicator

---

## ⚠️ Important Notes

### Limitations
1. **Single Global Mapping** - One mapping per model class (unique constraint)
2. **No Validation of Mapped Fields** - System doesn't verify field exists on model
3. **Runtime Errors** - Invalid dot notation paths return null (no exception)
4. **No Type Checking** - No validation that mapped field returns correct type

### Best Practices
1. **Test Mappings** - Always test in Tinker after creating:
   ```php
   $model = App\Models\MayaNetEsimProduct::find(1);
   $payable = new DynamicPayableWrapper($model);
   $payable->getPayableAmount(); // Test it works
   ```

2. **Use Descriptive Labels** - Makes identification easier in the table
3. **Map All Required Fields** - System will use defaults for unmapped fields
4. **Use Dot Notation Carefully** - Ensure relationships are loaded
5. **Keep Constants in Quotes** - `"ILS"` not `ILS` (unless it's a field name)

### Troubleshooting

**Problem**: "Model not found"
- **Solution**: Check namespace is correct (backslashes must be escaped in PHP strings)

**Problem**: Null returned for nested field
- **Solution**: Ensure relationship exists and is eager-loaded

**Problem**: Wrong value type
- **Solution**: Check if you need quotes (strings) or not (numbers/booleans)

**Problem**: Mapping not used
- **Solution**: Check `is_active` flag is true

---

## 🔄 Migration Path

### From Current System → New System

1. **Backup** current settings from `officeguy` config
2. **Run Migration**: `php artisan migrate`
3. **Create Mappings** via wizard for each model
4. **Update Code** to use `DynamicPayableWrapper`:
   ```php
   // Before
   $order = Order::find(1);
   if ($order instanceof Payable) {
       $payment = $service->createCharge($order);
   }

   // After
   $order = Order::find(1);
   $payable = new DynamicPayableWrapper($order);
   $payment = $service->createCharge($payable);
   ```
5. **Test Thoroughly** in staging environment
6. **Deploy** to production

---

## 📚 Resources

### Filament v4 Documentation
- [Wizards](https://filamentphp.com/docs/4.x/schemas/wizards)
- [Actions with Steps](https://filamentphp.com/docs/4.x/actions/modals)
- [Live Validation](https://filamentphp.com/docs/4.x/forms/validation)
- [Dynamic Select Options](https://filamentphp.com/docs/4.x/forms/select)
- [Table Widgets](https://filamentphp.com/docs/4.x/widgets/table-widgets)
- [Notifications](https://filamentphp.com/docs/4.x/notifications/overview)

### Related Documentation
- `CLAUDE_FILAMENT_V4.md` - Filament v4 migration guide
- `SUMIT_PACKAGES_ANALYSIS_2025-11-20.md` - Package analysis
- Package README - Installation & basic usage

---

**Version**: 1.0.0
**Last Updated**: 2025-01-27
**Author**: Claude Code with Filament v4 Advanced Features
