# SUMIT Payment Gateway for Laravel

**Version:** 1.1.6
**License:** MIT
**Author:** NM-DigitalHub

## Overview

Official Laravel package for SUMIT payment gateway integration with Filament v4 admin panels.

### Key Features

- ✅ Credit card payments (3 PCI modes: no/redirect/yes)
- ✅ Bit payment integration
- ✅ Token management (J2/J5) for saved payment methods
- ✅ Authorize-only and installment payments (up to 36)
- ✅ Document generation (invoices/receipts/donations)
- ✅ Subscription/recurring billing support
- ✅ Multi-vendor support
- ✅ Webhook handling (incoming + outgoing)
- ✅ Full Filament v4 integration

## Quick Links

- [GitHub Repository](https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel)
- [SUMIT API Documentation](https://docs.sumit.co.il)

## Main Namespaces

- **OfficeGuy\LaravelSumitGateway\Services** - Core business logic
- **OfficeGuy\LaravelSumitGateway\Models** - Eloquent models
- **OfficeGuy\LaravelSumitGateway\Filament** - Filament v4 resources
- **OfficeGuy\LaravelSumitGateway\Http\Controllers** - HTTP controllers
- **OfficeGuy\LaravelSumitGateway\Events** - Event classes

## Core Services

- **PaymentService** - Process credit card payments
- **TokenService** - Manage payment tokens
- **DocumentService** - Generate invoices and receipts
- **BitPaymentService** - Bit payment processing
- **SubscriptionService** - Recurring billing
- **WebhookService** - Webhook handling

## Installation

```bash
composer require officeguy/laravel-sumit-gateway
```

## Configuration

Published configuration file: `config/officeguy.php`

Admin settings page: `/admin/office-guy-settings`

---

**© 2025 NM-DigitalHub. All rights reserved.**
