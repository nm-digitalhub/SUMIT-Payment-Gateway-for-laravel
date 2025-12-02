@php
    /**
     * Public Checkout Page View
     * 
     * This view can be customized to match your application's design.
     * Publish with: php artisan vendor:publish --tag=officeguy-views
     * 
     * Available variables:
     * - $payable: The Payable model instance
     * - $settings: Payment gateway settings
     * - $maxPayments: Maximum installments allowed
     * - $bitEnabled: Whether Bit payment is enabled
     * - $supportTokens: Whether token storage is supported
     * - $savedTokens: Collection of saved payment methods (if user is logged in)
     * - $currency: Currency code (e.g., 'ILS')
     * - $currencySymbol: Currency symbol (e.g., '₪')
     * - $checkoutUrl: URL to submit the payment form
     */
    $rtl = app()->getLocale() === 'he' || app()->getLocale() === 'ar';
    $amount = $payable->getPayableAmount();
    $items = $payable->getLineItems();
    $shipping = $payable->getShippingAmount();
    $fees = $payable->getFees();
    $customerName = $prefillName ?? $payable->getCustomerName();
    $customerEmail = $prefillEmail ?? $payable->getCustomerEmail();
    $customerPhone = $prefillPhone ?? $payable->getCustomerPhone();
    $customerCitizenId = $prefillCitizenId ?? null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ __('Checkout') }} - {{ config('app.name') }}</title>
    
    {{-- Tailwind CSS CDN (replace with your own CSS in production) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    
    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    {{-- SUMIT PaymentsJS SDK --}}
    @if($settings['pci_mode'] === 'no')
    <script src="https://app.sumit.co.il/scripts/payments.js"></script>
    @endif
    
    <style>
        [x-cloak] { display: none !important; }
        
        .og-checkout {
            --og-primary: #0284c7;
            --og-primary-hover: #0369a1;
            --og-success: #22c55e;
            --og-error: #ef4444;
        }
    </style>
    
    @stack('styles')
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="og-checkout py-8 px-4" x-data="checkoutPage()" :dir="rtl ? 'rtl' : 'ltr'">
        <div class="max-w-4xl mx-auto">
            {{-- Header --}}
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">{{ __('Checkout') }}</h1>
                <p class="text-gray-600 mt-2">{{ __('Complete your purchase securely') }}</p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- Main Form Section --}}
                <div class="lg:col-span-2">
                    <form 
                        id="og-checkout-form" 
                        method="POST" 
                        action="{{ $checkoutUrl }}"
                        @submit.prevent="submitForm"
                        class="space-y-6"
                    >
                        @csrf
                        <input type="hidden" name="payable_id" value="{{ $payable->getPayableId() }}">
                        <input type="hidden" name="payable_type" value="{{ get_class($payable) }}">
                        
                        {{-- Error Messages --}}
                        <div x-show="errors.length > 0" x-cloak class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-red-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                <div class="{{ $rtl ? 'mr-3' : 'ml-3' }}">
                                    <h3 class="text-sm font-medium text-red-800">{{ __('Please fix the following errors:') }}</h3>
                                    <ul class="mt-2 text-sm text-red-700 list-disc {{ $rtl ? 'pr-5' : 'pl-5' }}">
                                        <template x-for="error in errors" :key="error">
                                            <li x-text="error"></li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Customer Information --}}
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 {{ $rtl ? 'ml-2' : 'mr-2' }} text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                {{ __('Customer Information') }}
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ __('Full Name') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="customer_name" 
                                        name="customer_name"
                                        x-model="customerName"
                                        value="{{ old('customer_name', $customerName) }}"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ __('Email') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="email" 
                                        id="customer_email" 
                                        name="customer_email"
                                        x-model="customerEmail"
                                        value="{{ old('customer_email', $customerEmail) }}"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                        required
                                    >
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ __('Phone') }}
                                    </label>
                                    <input 
                                        type="tel" 
                                        id="customer_phone" 
                                        name="customer_phone"
                                        x-model="customerPhone"
                                        value="{{ old('customer_phone', $customerPhone) }}"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                    >
                                </div>
                            </div>
                        </div>
                        
                        {{-- Payment Method Selection --}}
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 {{ $rtl ? 'ml-2' : 'mr-2' }} text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                {{ __('Payment Method') }}
                            </h2>
                            
                            {{-- Payment Method Tabs --}}
                            @if($bitEnabled)
                            <div class="flex gap-3 mb-6">
                                <button 
                                    type="button"
                                    @click="paymentMethod = 'card'"
                                    :class="paymentMethod === 'card' ? 'border-sky-500 bg-sky-50 text-sky-700' : 'border-gray-200 hover:border-gray-300'"
                                    class="flex-1 p-4 border-2 rounded-lg transition-colors"
                                >
                                    <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                    </svg>
                                    <span class="text-sm font-medium">{{ __('Credit Card') }}</span>
                                </button>
                                
                                <button 
                                    type="button"
                                    @click="paymentMethod = 'bit'"
                                    :class="paymentMethod === 'bit' ? 'border-sky-500 bg-sky-50 text-sky-700' : 'border-gray-200 hover:border-gray-300'"
                                    class="flex-1 p-4 border-2 rounded-lg transition-colors"
                                >
                                    <svg class="w-8 h-8 mx-auto mb-2 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                    </svg>
                                    <span class="text-sm font-medium">Bit</span>
                                </button>
                            </div>
                            @endif
                            
                            <input type="hidden" name="payment_method" x-model="paymentMethod">
                            
                            {{-- Credit Card Form --}}
                            <div x-show="paymentMethod === 'card'" x-cloak>
                                {{-- Saved Payment Methods --}}
                                @if($supportTokens && $savedTokens->isNotEmpty())
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">{{ __('Saved Payment Methods') }}</label>
                                    <div class="space-y-2">
                                        @foreach($savedTokens as $token)
                                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                            <input 
                                                type="radio" 
                                                name="payment_token" 
                                                value="{{ $token->id }}"
                                                x-model="selectedToken"
                                                class="text-sky-600 focus:ring-sky-500"
                                            >
                                            <span class="{{ $rtl ? 'mr-3' : 'ml-3' }} flex-1">
                                                <span class="font-medium">{{ $token->getMaskedNumber() }}</span>
                                                <span class="text-gray-500 text-sm">
                                                    ({{ __('Expires') }} {{ $token->expiry_month }}/{{ $token->expiry_year }})
                                                </span>
                                            </span>
                                        </label>
                                        @endforeach
                                        
                                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                            <input 
                                                type="radio" 
                                                name="payment_token" 
                                                value="new"
                                                x-model="selectedToken"
                                                class="text-sky-600 focus:ring-sky-500"
                                            >
                                            <span class="{{ $rtl ? 'mr-3' : 'ml-3' }} font-medium">{{ __('Use a new card') }}</span>
                                        </label>
                                    </div>
                                </div>
                                @endif
                                
                                {{-- New Card Fields --}}
                                <div x-show="selectedToken === 'new'" x-cloak class="space-y-4">
                                    {{-- Card Number --}}
                                    <div>
                                        <label for="og-ccnum" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Card Number') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input 
                                            type="text" 
                                            id="og-ccnum" 
                                            name="card_number"
                                            data-og="cardnumber"
                                            x-model="cardNumber"
                                            maxlength="19"
                                            inputmode="numeric"
                                            autocomplete="cc-number"
                                            placeholder="•••• •••• •••• ••••"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                        >
                                    </div>
                                    
                                    {{-- Expiration & CVV --}}
                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <label for="og-expmonth" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Month') }} <span class="text-red-500">*</span>
                                            </label>
                                            <select 
                                                id="og-expmonth" 
                                                name="exp_month"
                                                data-og="expirationmonth"
                                                x-model="expMonth"
                                                autocomplete="cc-exp-month"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                            >
                                                <option value="">--</option>
                                                @for($i = 1; $i <= 12; $i++)
                                                    <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label for="og-expyear" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Year') }} <span class="text-red-500">*</span>
                                            </label>
                                            <select 
                                                id="og-expyear" 
                                                name="exp_year"
                                                data-og="expirationyear"
                                                x-model="expYear"
                                                autocomplete="cc-exp-year"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                            >
                                                <option value="">--</option>
                                                @for($i = date('Y'); $i <= date('Y') + 15; $i++)
                                                    <option value="{{ $i }}">{{ $i }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        
                                        @if(in_array($settings['cvv_mode'], ['required', 'yes']))
                                        <div>
                                            <label for="og-cvv" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('CVV') }} 
                                                @if($settings['cvv_mode'] === 'required')<span class="text-red-500">*</span>@endif
                                            </label>
                                            <input 
                                                type="text" 
                                                id="og-cvv" 
                                                name="cvv"
                                                data-og="cvv"
                                                x-model="cvv"
                                                maxlength="4"
                                                inputmode="numeric"
                                                autocomplete="cc-csc"
                                                placeholder="•••"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                            >
                                        </div>
                                        @endif
                                    </div>
                                    
                                    {{-- ID Number (Citizen ID) --}}
                                    @if(in_array($settings['citizen_id_mode'], ['required', 'yes']))
                                    <div>
                                        <label for="og-citizenid" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('ID Number') }}
                                            @if($settings['citizen_id_mode'] === 'required')<span class="text-red-500">*</span>@endif
                                        </label>
                                        <input 
                                            type="text" 
                                            id="og-citizenid" 
                                            name="citizen_id"
                                            data-og="citizenid"
                                            x-model="citizenId"
                                            maxlength="9"
                                            inputmode="numeric"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                        >
                                    </div>
                                    @endif
                                    
                                    {{-- Hidden token field for PaymentsJS --}}
                                    @if($settings['pci_mode'] === 'no')
                                    <input type="hidden" name="og-token" data-og="token" x-model="singleUseToken">
                                    @endif
                                    
                                    {{-- Save Card Option --}}
                                    @if($supportTokens && auth()->check())
                                    <div class="flex items-center">
                                        <input 
                                            type="checkbox" 
                                            id="save_card" 
                                            name="save_card"
                                            x-model="saveCard"
                                            class="text-sky-600 focus:ring-sky-500 rounded"
                                        >
                                        <label for="save_card" class="{{ $rtl ? 'mr-2' : 'ml-2' }} text-sm text-gray-600">
                                            {{ __('Save card for future purchases') }}
                                        </label>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            
                            {{-- Bit Payment Info --}}
                            @if($bitEnabled)
                            <div x-show="paymentMethod === 'bit'" x-cloak class="bg-blue-50 rounded-lg p-4">
                                <p class="text-sm text-gray-700">
                                    {{ __('You will be redirected to complete your payment via Bit after clicking the button below.') }}
                                </p>
                            </div>
                            @endif
                            
                            {{-- Installments --}}
                            @if($maxPayments > 1)
                            <div class="mt-6" x-show="paymentMethod === 'card'">
                                <label for="payments_count" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Number of Payments') }}
                                </label>
                                <select 
                                    id="payments_count" 
                                    name="payments_count"
                                    x-model="paymentsCount"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                                >
                                    @for($i = 1; $i <= $maxPayments; $i++)
                                        <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? __('payment') : __('payments') }}</option>
                                    @endfor
                                </select>
                            </div>
                            @endif
                        </div>
                        
                        {{-- Submit Button --}}
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <button 
                                type="submit"
                                :disabled="processing"
                                class="w-full bg-sky-600 hover:bg-sky-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <template x-if="!processing">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        {{ __('Pay') }} {{ $currencySymbol }}{{ number_format($amount, 2) }}
                                    </span>
                                </template>
                                <template x-if="processing">
                                    <span class="flex items-center gap-2">
                                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ __('Processing...') }}
                                    </span>
                                </template>
                            </button>
                            
                            {{-- Security Badge --}}
                            <div class="flex items-center justify-center gap-2 mt-4 text-sm text-gray-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span>{{ __('Secured by SUMIT Payment Gateway') }}</span>
                            </div>
                        </div>
                    </form>
                </div>
                
                {{-- Order Summary Sidebar --}}
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm p-6 sticky top-8">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Order Summary') }}</h2>
                        
                        {{-- Items --}}
                        <div class="space-y-3 mb-4">
                            @foreach($items as $item)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">
                                    {{ $item['name'] }}
                                    @if(($item['quantity'] ?? 1) > 1)
                                        <span class="text-gray-400">× {{ $item['quantity'] }}</span>
                                    @endif
                                </span>
                                <span class="font-medium">{{ $currencySymbol }}{{ number_format(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1), 2) }}</span>
                            </div>
                            @endforeach
                        </div>
                        
                        {{-- Shipping --}}
                        @if($shipping > 0)
                        <div class="flex justify-between text-sm py-2 border-t border-gray-100">
                            <span class="text-gray-600">{{ $payable->getShippingMethod() ?? __('Shipping') }}</span>
                            <span class="font-medium">{{ $currencySymbol }}{{ number_format($shipping, 2) }}</span>
                        </div>
                        @endif
                        
                        {{-- Fees --}}
                        @foreach($fees as $fee)
                        <div class="flex justify-between text-sm py-2 border-t border-gray-100">
                            <span class="text-gray-600">{{ $fee['name'] }}</span>
                            <span class="font-medium">{{ $currencySymbol }}{{ number_format($fee['amount'], 2) }}</span>
                        </div>
                        @endforeach
                        
                        {{-- Tax --}}
                        @if($payable->isTaxEnabled() && $payable->getVatRate())
                        <div class="flex justify-between text-sm py-2 border-t border-gray-100">
                            <span class="text-gray-600">{{ __('VAT') }} ({{ $payable->getVatRate() }}%)</span>
                            <span class="font-medium">{{ __('Included') }}</span>
                        </div>
                        @endif
                        
                        {{-- Total --}}
                        <div class="flex justify-between pt-4 border-t-2 border-gray-200 mt-4">
                            <span class="text-lg font-semibold text-gray-900">{{ __('Total') }}</span>
                            <span class="text-lg font-bold text-sky-600">{{ $currencySymbol }}{{ number_format($amount, 2) }} {{ $currency }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function checkoutPage() {
            return {
                rtl: @json($rtl),
                paymentMethod: 'card',
                selectedToken: @json($savedTokens->isEmpty() ? 'new' : ''),
                cardNumber: '',
                expMonth: '',
                expYear: '',
                cvv: '',
                citizenId: @json(old('citizen_id', $customerCitizenId ?? '')),
                singleUseToken: '',
                paymentsCount: '1',
                saveCard: false,
                customerName: @json(old('customer_name', $customerName ?? '')),
                customerEmail: @json(old('customer_email', $customerEmail ?? '')),
                customerPhone: @json(old('customer_phone', $customerPhone ?? '')),
                processing: false,
                errors: [],
                
                init() {
                    // Initialize SUMIT PaymentsJS SDK if in PaymentsJS mode
                    @if($settings['pci_mode'] === 'no' && !empty($settings['company_id']) && !empty($settings['public_key']))
                    if (window.OfficeGuy?.Payments) {
                        OfficeGuy.Payments.BindFormSubmit({
                            CompanyID: @json($settings['company_id']),
                            APIPublicKey: @json($settings['public_key'])
                        });
                    }
                    @endif
                },
                
                validate() {
                    this.errors = [];
                    
                    if (!this.customerName.trim()) {
                        this.errors.push('{{ __("Full name is required") }}');
                    }
                    
                    if (!this.customerEmail.trim()) {
                        this.errors.push('{{ __("Email is required") }}');
                    } else if (!this.isValidEmail(this.customerEmail)) {
                        this.errors.push('{{ __("Please enter a valid email address") }}');
                    }
                    
                    if (this.paymentMethod === 'card' && this.selectedToken === 'new') {
                        if (!this.cardNumber.trim()) {
                            this.errors.push('{{ __("Card number is required") }}');
                        }
                        if (!this.expMonth || !this.expYear) {
                            this.errors.push('{{ __("Expiration date is required") }}');
                        }
                        @if(($settings['cvv_mode'] ?? 'required') === 'required')
                        if (!this.cvv.trim()) {
                            this.errors.push('{{ __("CVV is required") }}');
                        }
                        @endif
                        @if(($settings['citizen_id_mode'] ?? 'required') === 'required')
                        if (!this.citizenId.trim()) {
                            this.errors.push('{{ __("ID number is required") }}');
                        }
                        @endif
                    }
                    
                    return this.errors.length === 0;
                },
                
                isValidEmail(email) {
                    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                },
                
                async submitForm() {
                    if (!this.validate()) {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return;
                    }
                    
                    this.processing = true;
                    
                    @if($settings['pci_mode'] === 'no')
                    // Wait for PaymentsJS to populate token
                    await new Promise(resolve => setTimeout(resolve, 200));
                    @endif
                    
                    // Submit the form
                    document.getElementById('og-checkout-form').submit();
                }
            }
        }
    </script>
    
    @stack('scripts')
</body>
</html>
