@php
    /**
     * Digital Product Checkout (eSIM) - Simplified 2-Field Form
     *
     * Optimized for digital products with instant delivery:
     * - Only 2 required fields: Name + Email
     * - No address fields required
     * - Instant delivery messaging
     * - Streamlined UI
     */
    $rtl = app()->getLocale() === 'he' || app()->getLocale() === 'ar';
    $amount = $payable->getPayableAmount();
    $items = $payable->getLineItems();
    $user = auth()->user();
    if (!$user && class_exists(\Filament\Facades\Filament::class)) {
        $user = \Filament\Facades\Filament::auth()->user();
    }
    $client = $user?->client;

    // Pre-fill from user/client (if available)
    $customerName = $prefillName ?? $payable->getCustomerName() ?? ($client->name ?? null) ?? ($user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name : null);
    $customerEmail = $prefillEmail ?? $payable->getCustomerEmail() ?? ($client->email ?? null) ?? ($user->email ?? null);

    // Digital product theme
    $primaryColor = '#3B82F6';
    $currency = $payable->getPayableCurrency() ?? config('officeguy.currency', 'ILS');
    $currencySymbol = $currency === 'ILS' ? '₪' : '$';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Secure Checkout') }} - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .bg-primary { background-color: {{ $primaryColor }}; }
        .text-primary { color: {{ $primaryColor }}; }
        .border-primary { border-color: {{ $primaryColor }}; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="py-8 px-4" x-data="digitalCheckout()">
        <div class="max-w-3xl mx-auto">

            {{-- Header with Instant Delivery Badge --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2 bg-green-50 text-green-700 px-4 py-2 rounded-full text-sm font-medium mb-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    {{ __('Instant Delivery') }}
                </div>
                <h1 class="text-3xl font-bold text-gray-900">{{ __('Complete Your Purchase') }}</h1>
                <p class="text-gray-600 mt-2">{{ __('Your eSIM will be delivered instantly to your email') }}</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">

                {{-- Order Summary (Right/Left depending on RTL) --}}
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl shadow-sm p-6 sticky top-6">
                        <h3 class="font-semibold text-lg mb-4 pb-4 border-b">{{ __('Order Summary') }}</h3>

                        @foreach($items as $item)
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">{{ $item['name'] }}</p>
                                <p class="text-sm text-gray-500">{{ __('Quantity') }}: {{ $item['quantity'] }}</p>
                            </div>
                            <span class="font-semibold">{{ number_format($item['unit_price'] * $item['quantity'], 2) }} {{ $currencySymbol }}</span>
                        </div>
                        @endforeach

                        <div class="border-t pt-4 mt-4">
                            <div class="flex justify-between items-center text-lg font-bold">
                                <span>{{ __('Total') }}</span>
                                <span class="text-primary">{{ number_format($amount, 2) }} {{ $currencySymbol }}</span>
                            </div>
                        </div>

                        {{-- Trust Badges --}}
                        <div class="mt-6 pt-6 border-t space-y-2">
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                                {{ __('Secure SSL Encryption') }}
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                                {{ __('Instant Email Delivery') }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Checkout Form (Left/Right depending on RTL) --}}
                <div class="lg:col-span-2">
                    <form action="{{ $actionUrl ?? route('officeguy.checkout.submit', $payable->getPayableId()) }}"
                          method="POST"
                          class="bg-white rounded-2xl shadow-sm p-8"
                          x-ref="form">
                        @csrf

                        {{-- Customer Information --}}
                        <div class="mb-8">
                            <h3 class="font-semibold text-lg mb-6">{{ __('Customer Information') }}</h3>

                            {{-- Full Name --}}
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Full Name') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="customer_name"
                                       x-model="form.customer_name"
                                       value="{{ $customerName }}"
                                       required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition">
                            </div>

                            {{-- Email --}}
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Email Address') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="email"
                                       name="customer_email"
                                       x-model="form.customer_email"
                                       value="{{ $customerEmail }}"
                                       dir="ltr"
                                       required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition text-left">
                                <p class="text-xs text-gray-500 mt-1">{{ __('Your eSIM activation details will be sent here') }}</p>
                            </div>
                        </div>

                        {{-- Payment Method Section --}}
                        <div class="mb-8">
                            <h3 class="font-semibold text-lg mb-6">{{ __('Payment Method') }}</h3>

                            {{-- Payment fields will be injected here by parent controller --}}
                            <div id="payment-fields">
                                {{ $slot ?? '' }}
                            </div>
                        </div>

                        {{-- Terms & Conditions --}}
                        <div class="mb-8">
                            <label class="flex items-start gap-3 cursor-pointer group">
                                <input type="checkbox"
                                       name="accept_terms"
                                       x-model="form.accept_terms"
                                       required
                                       class="mt-1 w-5 h-5 text-primary rounded border-gray-300 focus:ring-primary">
                                <span class="text-sm text-gray-700 group-hover:text-gray-900">
                                    {{ __('I agree to the') }}
                                    <a href="{{ route('terms') }}" target="_blank" class="text-primary underline">{{ __('terms and conditions') }}</a>
                                    {{ __('and') }}
                                    <a href="{{ route('privacy') }}" target="_blank" class="text-primary underline">{{ __('privacy policy') }}</a>
                                </span>
                            </label>
                        </div>

                        {{-- Submit Button --}}
                        <button type="submit"
                                class="w-full bg-primary text-white py-4 rounded-lg font-semibold text-lg hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="!isFormValid"
                                x-text="isProcessing ? '{{ __('Processing...') }}' : '{{ __('Complete Purchase') }}'">
                            {{ __('Complete Purchase') }}
                        </button>

                        <p class="text-center text-xs text-gray-500 mt-4">
                            🔒 {{ __('Your payment is secure and encrypted') }}
                        </p>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script>
        function digitalCheckout() {
            return {
                form: {
                    customer_name: '{{ $customerName ?? '' }}',
                    customer_email: '{{ $customerEmail ?? '' }}',
                    accept_terms: false
                },
                isProcessing: false,

                get isFormValid() {
                    return this.form.customer_name.trim() &&
                           this.form.customer_email.trim() &&
                           this.form.accept_terms;
                }
            }
        }
    </script>
</body>
</html>
