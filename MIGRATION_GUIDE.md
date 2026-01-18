# Migration Guide: v1.x → v2.0.0

**Package**: `officeguy/laravel-sumit-gateway`
**Migration Date**: January 2026
**Breaking Changes**: Internal HTTP client architecture only

---

## 📊 Quick Summary

| Aspect | Changed? | Action Required |
|--------|----------|-----------------|
| **Service Layer** | ❌ No | ✅ **None** - 100% compatible |
| **Filament Resources** | ❌ No | ✅ **None** |
| **Database Schema** | ❌ No | ✅ **None** |
| **Configuration** | ❌ No | ✅ **None** |
| **Events** | ❌ No | ✅ **None** |
| **Models** | ❌ No | ✅ **None** |
| **HTTP Client** | ✅ Yes | ⚠️ Only if using `OfficeGuyApi::post()` directly |

**TL;DR**: If you're only using the **service layer** (`PaymentService`, `TokenService`, etc.), you can upgrade with **zero code changes**. Direct usage of `OfficeGuyApi::post()` is deprecated.

---

## 🎯 What Changed

### Breaking Changes

#### 1. `OfficeGuyApi::post()` Deprecated ⚠️

**Impact**: Only affects code that calls `OfficeGuyApi::post()` directly (not through services).

**Old Code** (v1.x):
```php
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

$response = OfficeGuyApi::post([
    'Credentials' => ['CompanyID' => $id, 'APIKey' => $key],
    'Amount' => 100,
    'Currency' => 'ILS',
], '/billing/payments/charge/');
```

**New Code** (v2.0 - Recommended):
```php
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

// Use the service layer instead
$response = PaymentService::chargePayment($order, $paymentData);
```

**Or** (if you must use the API client directly):
```php
use OfficeGuy\LaravelSumitGateway\Http\Connectors\SumitConnector;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;

$credentials = new CredentialsData($id, $key);
$connector = new SumitConnector();

$request = new class($credentials, $amount, $currency) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
    use \Saloon\Traits\Body\HasJsonBody;

    protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

    public function __construct(
        protected readonly CredentialsData $credentials,
        protected readonly float $amount,
        protected readonly string $currency
    ) {}

    public function resolveEndpoint(): string
    {
        return '/billing/payments/charge/';
    }

    protected function defaultBody(): array
    {
        return [
            'Credentials' => $this->credentials->toArray(),
            'Amount' => $this->amount,
            'Currency' => $this->currency,
        ];
    }

    protected function defaultConfig(): array
    {
        return ['timeout' => 180];
    }
};

$saloonResponse = $connector->send($request);
$response = $saloonResponse->json();
```

**Deprecation Timeline**:
- **v2.0.0** (current): `OfficeGuyApi::post()` marked as deprecated but still functional
- **v3.0.0** (future): `OfficeGuyApi::post()` will be removed entirely

### Non-Breaking Changes (100% Compatible)

#### ✅ All Services Unchanged

**These still work exactly the same**:
```php
// PaymentService
PaymentService::chargePayment($order, $paymentData);
PaymentService::getCustomerBalance($customerId);
PaymentService::setPaymentMethod($customerId, $token);

// TokenService
TokenService::createToken($user);

// DocumentService
DocumentService::createDocument($order, $documentType);
DocumentService::downloadDocument($documentId);

// SubscriptionService
SubscriptionService::createSubscription($subscription);
SubscriptionService::cancelSubscription($subscriptionId);

// BitPaymentService
BitPaymentService::chargeBitPayment($order);

// CustomerService
CustomerService::createCustomer($customerData);
CustomerService::updateCustomer($customerId, $customerData);

// DebtService
DebtService::getCustomerDebt($customerId);
DebtService::getOrderDebt($orderId);

// UpsellService
UpsellService::processUpsellCharge($upsellOrder, $token);

// MultiVendorPaymentService
MultiVendorPaymentService::chargeVendorItems($order, $items, $credentials);
```

**Method Signatures**: Identical - no parameter changes, no return type changes.

#### ✅ Filament Resources Unchanged

All admin and client panel resources work without modification:
- TransactionResource
- TokenResource
- DocumentResource
- SubscriptionResource
- VendorCredentialResource
- WebhookEventResource
- SumitWebhookResource
- All Client Resources

#### ✅ Database Schema Unchanged

No new migrations required. All existing tables work identically:
- `officeguy_transactions`
- `officeguy_tokens`
- `officeguy_documents`
- `officeguy_settings`
- `vendor_credentials`
- `subscriptions`
- `webhook_events`
- `sumit_incoming_webhooks`

#### ✅ Configuration Unchanged

All `config/officeguy.php` settings remain the same. The 3-layer priority system (Database → Config → .env) still works identically.

#### ✅ Events Unchanged

All events fire exactly as before:
- `PaymentCompleted`, `PaymentFailed`
- `BitPaymentCompleted`
- `SubscriptionCreated`, `SubscriptionCharged`, `SubscriptionChargesFailed`, `SubscriptionCancelled`
- `UpsellPaymentCompleted`, `UpsellPaymentFailed`
- `MultiVendorPaymentCompleted`, `MultiVendorPaymentFailed`
- `DocumentCreated`
- All webhook events

#### ✅ Models Unchanged

All Eloquent models work identically:
- `OfficeGuyTransaction`
- `OfficeGuyToken`
- `OfficeGuyDocument`
- `OfficeGuySetting`
- `VendorCredential`
- `Subscription`
- `WebhookEvent`
- `SumitWebhook`

---

## 🚀 Upgrade Instructions

### Prerequisites

- **PHP**: ^8.2 (no change)
- **Laravel**: ^12.0 (no change)
- **Filament**: ^4.0 (no change)
- **Composer**: Latest version recommended

### Step-by-Step Upgrade

#### 1. Backup Your Application

```bash
# Backup database
mysqldump -u user -p database > backup_$(date +%Y%m%d).sql

# Backup .env file
cp .env .env.backup

# Commit all changes
git add .
git commit -m "Backup before SUMIT v2.0 upgrade"
```

#### 2. Update Composer Dependency

**Option A**: Update to v2.0.0 specifically
```bash
composer require officeguy/laravel-sumit-gateway:^2.0
```

**Option B**: Update to latest version
```bash
composer update officeguy/laravel-sumit-gateway
```

#### 3. Verify Installation

```bash
# Check installed version
composer show officeguy/laravel-sumit-gateway

# Should show: versions : * v2.0.0
```

#### 4. Clear Caches

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

#### 5. Search for Direct `OfficeGuyApi::post()` Usage

```bash
# Search your application code (not vendor)
grep -r "OfficeGuyApi::post" app/

# If found, refactor to use service layer instead
```

**Example Refactoring**:

**Before**:
```php
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

$response = OfficeGuyApi::post([
    'Credentials' => ['CompanyID' => $id, 'APIKey' => $key],
    'Items' => $items,
], '/billing/payments/charge/');
```

**After**:
```php
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

// Use the appropriate service method
$response = PaymentService::chargePayment($order, $paymentData);
```

#### 6. Run Tests

```bash
# Run your application tests
php artisan test

# Or if using PHPUnit directly
vendor/bin/phpunit
```

#### 7. Test in Staging/Development First

Before deploying to production:

1. **Test Payment Flow**:
   - Process a test payment
   - Verify token creation
   - Check document generation
   - Test subscription creation

2. **Test Bit Integration**:
   - Process Bit payment
   - Verify callback handling

3. **Test Webhooks**:
   - Trigger test webhook
   - Verify processing

4. **Check Admin Panel**:
   - View transactions
   - View documents
   - View tokens

5. **Check Client Panel** (if used):
   - Customer payment methods
   - Customer transactions

#### 8. Deploy to Production

```bash
# On production server
composer update officeguy/laravel-sumit-gateway
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan queue:restart  # If using queues
```

---

## 🧪 Testing Checklist

Use this checklist to verify everything works after upgrade:

### Core Functionality

- [ ] **Payment Processing**
  - [ ] Direct charge works
  - [ ] Redirect mode works
  - [ ] Authorize-only works
  - [ ] Installments work
  - [ ] Transaction logged correctly

- [ ] **Token Management**
  - [ ] Create token via PaymentsJS
  - [ ] Create token via direct PCI
  - [ ] Token saved to database
  - [ ] Recurring payments with token

- [ ] **Document Generation**
  - [ ] Invoice created automatically
  - [ ] Receipt generated
  - [ ] Document downloadable
  - [ ] Email delivery works

- [ ] **Bit Integration**
  - [ ] Bit charge initiates
  - [ ] Redirect to Bit app works
  - [ ] Webhook callback processes
  - [ ] Transaction updates correctly

- [ ] **Subscriptions**
  - [ ] Subscription creates successfully
  - [ ] Recurring charge processes
  - [ ] Cancellation works
  - [ ] Status updates correctly

- [ ] **Webhooks**
  - [ ] Incoming SUMIT webhooks process
  - [ ] Signature validation works
  - [ ] Outgoing webhooks send
  - [ ] Events fire correctly

### Admin Panel

- [ ] **Transaction Resource**
  - [ ] List view loads
  - [ ] Detail view loads
  - [ ] Filters work
  - [ ] Export works

- [ ] **Token Resource**
  - [ ] Tokens listed correctly
  - [ ] Customer association correct

- [ ] **Document Resource**
  - [ ] Documents listed
  - [ ] Download works
  - [ ] Email resend works

- [ ] **Settings Page**
  - [ ] Loads correctly
  - [ ] Saves changes
  - [ ] Database values persist

### Client Panel (If Applicable)

- [ ] **Customer Payment Methods**
  - [ ] List view works
  - [ ] Add payment method works

- [ ] **Customer Transactions**
  - [ ] Transaction history loads
  - [ ] Details viewable

### Performance

- [ ] **API Response Times**
  - [ ] Payment charges < 5s
  - [ ] Token creation < 3s
  - [ ] Document creation < 3s
  - [ ] Balance queries < 2s

- [ ] **No N+1 Queries**
  - [ ] Transaction list efficient
  - [ ] Document list efficient

---

## 🔄 Rollback Instructions

If you encounter critical issues, you can rollback to v1.x:

### Option 1: Composer Downgrade

```bash
# Rollback to last v1.x version
composer require officeguy/laravel-sumit-gateway:^1.1

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### Option 2: Git Revert

```bash
# If you committed before upgrade
git revert HEAD
composer install
php artisan config:clear
```

### Option 3: Restore Backup

```bash
# Restore database backup
mysql -u user -p database < backup_YYYYMMDD.sql

# Restore .env
cp .env.backup .env

# Reinstall v1.x
composer require officeguy/laravel-sumit-gateway:^1.1
```

---

## 💡 Tips & Best Practices

### Recommended Upgrade Path

1. **Local Development** → Test thoroughly
2. **Staging Environment** → Verify with real-ish data
3. **Production** → Deploy during low-traffic window

### Monitoring After Upgrade

Monitor these metrics for 24-48 hours after upgrade:

```php
// Log API response times
Log::debug('SUMIT API Response', [
    'endpoint' => $endpoint,
    'duration_ms' => $duration,
    'status' => $response['Status'] ?? null,
]);

// Track success rates
$successRate = OfficeGuyTransaction::where('created_at', '>', now()->subDay())
    ->where('success', true)
    ->count() / OfficeGuyTransaction::where('created_at', '>', now()->subDay())->count();
```

### Common Issues & Solutions

#### Issue: "Class 'Saloon\Http\Connector' not found"

**Solution**: Clear Composer cache and reinstall
```bash
composer dump-autoload
composer install
```

#### Issue: Payment still works but logs show deprecation warnings

**Solution**: This is expected if using `OfficeGuyApi::post()` directly. Refactor to service layer.

#### Issue: Tests failing after upgrade

**Solution**: Update HTTP fakes to match Saloon
```php
// Old (v1.x)
Http::fake([
    'api.sumit.co.il/*' => Http::response(['Status' => 0], 200),
]);

// New (v2.0) - Same, no change needed!
Http::fake([
    'api.sumit.co.il/*' => Http::response(['Status' => 0], 200),
]);
```

---

## 📚 Additional Resources

### Documentation

- **REFACTORING_SUMMARY.md**: Complete technical details of all changes
- **CLAUDE.md**: Updated developer guide with Saloon patterns
- **README.md**: User documentation (Hebrew)
- **CHANGELOG.md**: Full version history

### Support

- **GitHub Issues**: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/issues
- **Email**: info@nm-digitalhub.com

### Saloon PHP Resources

- **Official Docs**: https://docs.saloon.dev/
- **GitHub**: https://github.com/saloonphp/saloon

---

## ❓ FAQ

### Q: Do I need to update my code?

**A**: Only if you're calling `OfficeGuyApi::post()` directly. If you're using service methods (`PaymentService::chargePayment()`, etc.), **no changes needed**.

### Q: Will this affect my existing payments?

**A**: No. All payment processing logic is identical. Only the internal HTTP client changed.

### Q: Can I still use v1.x?

**A**: Yes, v1.x will continue to receive critical security fixes. However, new features will only be added to v2.0+.

### Q: What about performance?

**A**: Saloon is actually **more efficient** than Laravel HTTP facade due to better connection pooling and retry logic. You may see slight performance improvements.

### Q: Is my data safe?

**A**: Yes. Zero database schema changes. All data remains exactly as it was.

### Q: Do I need to update my SUMIT API credentials?

**A**: No. Credentials remain unchanged. The package still uses CompanyID + APIKey authentication.

### Q: What if I encounter bugs?

**A**: Please report them immediately on GitHub Issues. We'll prioritize v2.0 bug fixes.

---

## 📊 Upgrade Checklist

Use this checklist to track your upgrade progress:

### Pre-Upgrade
- [ ] Read this entire migration guide
- [ ] Backup database
- [ ] Backup .env file
- [ ] Commit all current changes
- [ ] Identify direct `OfficeGuyApi::post()` usage

### Upgrade
- [ ] Update Composer dependency
- [ ] Verify correct version installed
- [ ] Clear all caches
- [ ] Refactor any direct API usage (if found)

### Testing
- [ ] Run automated tests
- [ ] Test payment flow manually
- [ ] Test token creation
- [ ] Test document generation
- [ ] Test Bit integration
- [ ] Test subscriptions
- [ ] Test webhooks
- [ ] Test admin panel
- [ ] Test client panel (if used)

### Deployment
- [ ] Deploy to staging first
- [ ] Verify staging functionality
- [ ] Deploy to production
- [ ] Monitor for 24-48 hours
- [ ] Check error logs
- [ ] Verify transaction success rate

### Post-Upgrade
- [ ] Document any custom changes
- [ ] Update internal documentation
- [ ] Inform team of upgrade
- [ ] Monitor performance metrics

---

**Migration Guide Version**: 1.0
**Package Version**: v2.0.0
**Last Updated**: 2026-01-18
**Maintained By**: NM-DigitalHub

---

**Need Help?** Contact us at info@nm-digitalhub.com or open an issue on GitHub.

**Upgrading went smoothly?** We'd love to hear about it! Share your experience on GitHub Discussions.
