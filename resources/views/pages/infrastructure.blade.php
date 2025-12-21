@php
    /**
     * Infrastructure Checkout (Domains/Hosting/VPS) - Full Form with Address
     *
     * Optimized for infrastructure services requiring complete customer details:
     * - Required: Name, Email, Phone, Address, City, Postal Code, Country
     * - Domain-specific messaging
     * - WHOIS compliance requirements
     * - Company/VAT fields for business customers
     */
    $rtl = app()->getLocale() === 'he' || app()->getLocale() === 'ar';
    $amount = $payable->getPayableAmount();
    $items = $payable->getLineItems();
    $user = auth()->user();
    if (!$user && class_exists(\Filament\Facades\Filament::class)) {
        $user = \Filament\Facades\Filament::auth()->user();
    }
    $client = $user?->client;

    // Pre-fill all fields from user/client
    $customerName = $prefillName ?? $payable->getCustomerName() ?? ($client->name ?? null) ?? ($user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name : null);
    $customerEmail = $prefillEmail ?? $payable->getCustomerEmail() ?? ($client->email ?? null) ?? ($user->email ?? null);
    $customerPhone = $prefillPhone ?? $payable->getCustomerPhone() ?? ($client->phone ?? null) ?? ($user->phone ?? null);
    $customerCompany = $prefillCompany ?? $client->company ?? $user->company ?? null;
    $customerVat = $prefillVat ?? $client->vat_number ?? $user->vat_number ?? null;
    $customerAddress = $prefillAddress ?? $client->client_address ?? $client->address ?? null;
    $customerCity = $prefillCity ?? $client->client_city ?? $client->city ?? null;
    $customerState = $prefillState ?? $client->client_state ?? $client->state ?? null;
    $customerCountry = $prefillCountry ?? $client->client_country ?? $client->country ?? 'IL';
    $customerPostal = $prefillPostal ?? $client->client_postal_code ?? $client->postal_code ?? null;

    // Infrastructure theme
    $primaryColor = '#3B82F6';
    $currency = $payable->getPayableCurrency() ?? config('officeguy.currency', 'ILS');
    $currencySymbol = $currency === 'ILS' ? '₪' : '$';

    // Common countries
    $countries = [
        'IL' => __('Israel'),
        'US' => __('United States'),
        'GB' => __('United Kingdom'),
        'CA' => __('Canada'),
        'AU' => __('Australia'),
        'DE' => __('Germany'),
        'FR' => __('France'),
        'NL' => __('Netherlands'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Domain & Hosting Checkout') }} - {{ config('app.name') }}</title>
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
    <div class="py-8 px-4" x-data="infrastructureCheckout()">
        <div class="max-w-6xl mx-auto">

            {{-- Header with Domain Badge --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-700 px-4 py-2 rounded-full text-sm font-medium mb-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                    </svg>
                    {{ __('Domain & Hosting Services') }}
                </div>
                <h1 class="text-3xl font-bold text-gray-900">{{ __('Complete Your Purchase') }}</h1>
                <p class="text-gray-600 mt-2">{{ __('Secure your online presence') }}</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">

                {{-- Order Summary --}}
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl shadow-sm p-6 sticky top-6">
                        <h3 class="font-semibold text-lg mb-4 pb-4 border-b">{{ __('Order Summary') }}</h3>

                        @foreach($items as $item)
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">{{ $item['name'] }}</p>
                                @if(isset($item['sku']))
                                <p class="text-xs text-gray-500">{{ $item['sku'] }}</p>
                                @endif
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
                                {{ __('ICANN Accredited Registrar') }}
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                                {{ __('Domain Privacy Available') }}
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                                {{ __('24/7 Customer Support') }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Checkout Form --}}
                <div class="lg:col-span-2">
                    <form action="{{ $actionUrl ?? route('officeguy.public.checkout.process', $payable->getPayableId()) }}"
                          method="POST"
                          class="bg-white rounded-2xl shadow-sm p-8"
                          x-ref="form">
                        @csrf

                        {{-- Personal Information --}}
                        <div class="mb-8">
                            <h3 class="font-semibold text-lg mb-6 flex items-center gap-2">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                {{ __('Personal Information') }}
                            </h3>

                            <div class="grid md:grid-cols-2 gap-6">
                                {{-- Full Name --}}
                                <div>
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
                                <div>
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
                                </div>

                                {{-- Phone --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ __('Phone Number') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="tel"
                                           name="customer_phone"
                                           x-model="form.customer_phone"
                                           value="{{ $customerPhone }}"
                                           dir="ltr"
                                           required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition text-left">
                                </div>

                                {{-- Company (Optional) --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ __('Company Name') }} <span class="text-gray-400">({{ __('Optional') }})</span>
                                    </label>
                                    <input type="text"
                                           name="customer_company"
                                           x-model="form.customer_company"
                                           value="{{ $customerCompany }}"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition">
                                </div>

                                {{-- VAT Number (Optional) --}}
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ __('VAT Number') }} <span class="text-gray-400">({{ __('Optional') }})</span>
                                    </label>
                                    <input type="text"
                                           name="customer_vat"
                                           x-model="form.customer_vat"
                                           value="{{ $customerVat }}"
                                           dir="ltr"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition text-left">
                                </div>
                            </div>
                        </div>

                        {{-- Billing Address --}}
                        <div class="mb-8">
                            <h3 class="font-semibold text-lg mb-6 flex items-center gap-2">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ __('Billing Address') }}
                            </h3>

                            <div class="space-y-6">
                                {{-- Street Address --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ __('Street Address') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           name="customer_address"
                                           x-model="form.customer_address"
                                           value="{{ $customerAddress }}"
                                           required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition">
                                </div>

                                <div class="grid md:grid-cols-3 gap-6">
                                    {{-- City --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            {{ __('City') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text"
                                               name="customer_city"
                                               x-model="form.customer_city"
                                               value="{{ $customerCity }}"
                                               required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition">
                                    </div>

                                    {{-- State/Province (Optional) --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            {{ __('State/Province') }} <span class="text-gray-400">({{ __('Optional') }})</span>
                                        </label>
                                        <input type="text"
                                               name="customer_state"
                                               x-model="form.customer_state"
                                               value="{{ $customerState }}"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition">
                                    </div>

                                    {{-- Postal Code --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            {{ __('Postal Code') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text"
                                               name="customer_postal"
                                               x-model="form.customer_postal"
                                               value="{{ $customerPostal }}"
                                               dir="ltr"
                                               required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition text-left">
                                    </div>
                                </div>

                                {{-- Country --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ __('Country') }} <span class="text-red-500">*</span>
                                    </label>
                                    <select name="customer_country"
                                            x-model="form.customer_country"
                                            required
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition">
                                        @foreach($countries as $code => $name)
                                        <option value="{{ $code }}" {{ $customerCountry === $code ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm text-blue-800">
                                    <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                                    </svg>
                                    {{ __('This information is required for domain registration and WHOIS compliance') }}
                                </p>
                            </div>
                        </div>

                        {{-- Payment Method Section --}}
                        <div class="mb-8">
                            <h3 class="font-semibold text-lg mb-6 flex items-center gap-2">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                {{ __('Payment Method') }}
                            </h3>

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
                                    <a href="{{ route('legal.terms.he') }}" target="_blank" class="text-primary underline">{{ __('terms and conditions') }}</a>
                                    {{ __('and') }}
                                    <a href="{{ route('legal.privacy.he') }}" target="_blank" class="text-primary underline">{{ __('privacy policy') }}</a>
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
        function infrastructureCheckout() {
            return {
                form: {
                    customer_name: '{{ $customerName ?? '' }}',
                    customer_email: '{{ $customerEmail ?? '' }}',
                    customer_phone: '{{ $customerPhone ?? '' }}',
                    customer_company: '{{ $customerCompany ?? '' }}',
                    customer_vat: '{{ $customerVat ?? '' }}',
                    customer_address: '{{ $customerAddress ?? '' }}',
                    customer_city: '{{ $customerCity ?? '' }}',
                    customer_state: '{{ $customerState ?? '' }}',
                    customer_postal: '{{ $customerPostal ?? '' }}',
                    customer_country: '{{ $customerCountry ?? 'IL' }}',
                    accept_terms: false
                },
                isProcessing: false,

                get isFormValid() {
                    return this.form.customer_name.trim() &&
                           this.form.customer_email.trim() &&
                           this.form.customer_phone.trim() &&
                           this.form.customer_address.trim() &&
                           this.form.customer_city.trim() &&
                           this.form.customer_postal.trim() &&
                           this.form.customer_country.trim() &&
                           this.form.accept_terms;
                }
            }
        }
    </script>
</body>
</html>
