{{-- resources/views/vendor/officeguy/pages/subscription.blade.php --}}
{{-- Subscription Checkout Template - For recurring billing products (Business Email: 3 packages) --}}

@php
    $siteSettings = app(\App\Settings\SiteSettings::class);
@endphp

@extends('officeguy::layouts.checkout')

@section('checkout-form')
<div class="max-w-3xl mx-auto" x-data="{ processing: false, tokenizeCard: true }">

    {{-- Subscription Badge --}}
    <div class="bg-gradient-to-r from-purple-50 to-indigo-50 border-2 border-purple-400 rounded-2xl p-6 mb-8">
        <div class="flex items-center gap-4">
            <div class="flex-shrink-0">
                <div class="relative">
                    <div class="absolute inset-0 animate-pulse bg-purple-400 rounded-full opacity-50"></div>
                    <x-heroicon-o-arrow-path class="relative w-10 h-10 text-purple-600" />
                </div>
            </div>
            <div class="flex-1">
                <h3 class="text-xl font-bold text-purple-900 mb-1">
                    {{ __('Recurring Subscription') }}
                </h3>
                <p class="text-purple-700 text-sm">
                    {{ __('Your card will be charged automatically every billing cycle') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Product Summary with Billing Cycle --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4">{{ __('Subscription Details') }}</h3>

        <div class="flex items-center justify-between mb-4 p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl">
            <div class="flex items-center gap-4">
                <x-heroicon-o-server class="w-10 h-10 text-indigo-600" />
                <div>
                    <h4 class="text-lg font-bold text-gray-900">{{ $payable->name }}</h4>
                    <p class="text-sm text-gray-600">
                        @if($payable->billing_cycle === 'monthly')
                            {{ __('Billed Monthly') }}
                        @elseif($payable->billing_cycle === 'yearly')
                            {{ __('Billed Annually') }}
                        @else
                            {{ __('Recurring Billing') }}
                        @endif
                    </p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-3xl font-bold text-indigo-600">
                    ₪{{ number_format($payable->getPayableAmount(), 2) }}
                </div>
                <p class="text-xs text-gray-500">
                    @if($payable->billing_cycle === 'monthly')
                        {{ __('per month') }}
                    @elseif($payable->billing_cycle === 'yearly')
                        {{ __('per year') }}
                    @else
                        {{ __('per cycle') }}
                    @endif
                </p>
            </div>
        </div>

        {{-- Billing Cycle Info --}}
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex items-start gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-blue-800">
                    <strong>{{ __('Auto-Renewal') }}:</strong>
                    {{ __('Your subscription will automatically renew unless you cancel at least 24 hours before the next billing date.') }}
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('officeguy.public.checkout.process', $payable) }}" @submit="processing = true">
        @csrf

        {{-- Section 1: Billing Contact Information --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">{{ __('Billing Contact') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Full Name --}}
                <div class="col-span-2">
                    <label for="customer_name" class="block text-sm font-semibold text-gray-700 mb-2">
                        {{ __('Full Name') }} *
                    </label>
                    <input
                        type="text"
                        id="customer_name"
                        name="customer_name"
                        value="{{ $customerName ?? '' }}"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 transition-colors"
                        placeholder="{{ __('John Doe') }}"
                    >
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                        {{ __('Email Address') }} *
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ $customerEmail ?? '' }}"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 transition-colors"
                        placeholder="john@example.com"
                    >
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('Billing receipts will be sent here') }}
                    </p>
                </div>

                {{-- Phone --}}
                <div>
                    <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">
                        {{ __('Phone Number') }} *
                    </label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="{{ $customerPhone ?? '' }}"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 transition-colors"
                        placeholder="+972-50-123-4567"
                    >
                </div>
            </div>
        </div>

        {{-- Section 2: Billing Address --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">{{ __('Billing Address') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Street Address --}}
                <div class="col-span-2">
                    <label for="billing_address" class="block text-sm font-semibold text-gray-700 mb-2">
                        {{ __('Street Address') }} *
                    </label>
                    <input
                        type="text"
                        id="billing_address"
                        name="billing_address"
                        value="{{ $customerAddress ?? '' }}"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 transition-colors"
                        placeholder="{{ __('123 Main Street') }}"
                    >
                </div>

                {{-- City --}}
                <div>
                    <label for="billing_city" class="block text-sm font-semibold text-gray-700 mb-2">
                        {{ __('City') }} *
                    </label>
                    <input
                        type="text"
                        id="billing_city"
                        name="billing_city"
                        value="{{ $customerCity ?? '' }}"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 transition-colors"
                        placeholder="{{ __('Tel Aviv') }}"
                    >
                </div>

                {{-- Postal Code --}}
                <div>
                    <label for="billing_postal_code" class="block text-sm font-semibold text-gray-700 mb-2">
                        {{ __('Postal Code') }}
                    </label>
                    <input
                        type="text"
                        id="billing_postal_code"
                        name="billing_postal_code"
                        value="{{ $customerPostal ?? '' }}"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 transition-colors"
                        placeholder="12345"
                    >
                </div>

                {{-- Country --}}
                <div class="col-span-2">
                    <label for="billing_country" class="block text-sm font-semibold text-gray-700 mb-2">
                        {{ __('Country') }} *
                    </label>
                    <select
                        id="billing_country"
                        name="billing_country"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 transition-colors"
                    >
                        <option value="">{{ __('Select Country') }}</option>
                        <option value="IL" {{ ($customerCountry ?? '') === 'IL' ? 'selected' : '' }}>{{ __('Israel') }}</option>
                        <option value="US" {{ ($customerCountry ?? '') === 'US' ? 'selected' : '' }}>{{ __('United States') }}</option>
                        <option value="GB" {{ ($customerCountry ?? '') === 'GB' ? 'selected' : '' }}>{{ __('United Kingdom') }}</option>
                        {{-- Add more countries as needed --}}
                    </select>
                </div>
            </div>
        </div>

        {{-- Section 3: Payment Method with Tokenization --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">{{ __('Payment Method') }}</h3>

            {{-- Tokenization Notice --}}
            <div class="bg-purple-50 border-2 border-purple-300 rounded-xl p-4 mb-6">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-lock-closed class="w-6 h-6 text-purple-600 flex-shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <h4 class="font-bold text-purple-900 mb-1">{{ __('Secure Card Storage') }}</h4>
                        <p class="text-sm text-purple-800 mb-3">
                            {{ __('Your payment card will be securely stored for automatic recurring billing. We use industry-standard encryption.') }}
                        </p>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                name="save_card"
                                value="1"
                                x-model="tokenizeCard"
                                checked
                                class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-2 focus:ring-purple-500"
                            >
                            <span class="text-sm font-semibold text-purple-900">
                                {{ __('I authorize storing my payment card for recurring billing') }}
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- SUMIT Payment Form (Client model is owner of payment tokens) --}}
            <x-officeguy::payment-form
                :order-amount="$payable->getPayableAmount()"
                :owner="auth()->user()?->client"
            />
        </div>

        {{-- Section 4: Subscription Terms --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">{{ __('Subscription Terms') }}</h3>

            <div class="space-y-3">
                {{-- Auto-Renewal Terms --}}
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="auto_renewal_accepted"
                        required
                        class="mt-1 w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-2 focus:ring-indigo-500"
                    >
                    <span class="text-sm text-gray-700">
                        {{ __('I understand that my subscription will automatically renew and my payment method will be charged at the start of each billing cycle until I cancel.') }}
                    </span>
                </label>

                {{-- Cancellation Policy --}}
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="cancellation_policy_accepted"
                        required
                        class="mt-1 w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-2 focus:ring-indigo-500"
                    >
                    <span class="text-sm text-gray-700">
                        {{ __('I understand that I can cancel my subscription at any time by contacting support or through my account dashboard. Cancellation must be made at least 24 hours before the next billing date.') }}
                    </span>
                </label>

                {{-- General Terms --}}
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="terms_accepted"
                        required
                        class="mt-1 w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-2 focus:ring-indigo-500"
                    >
                    <span class="text-sm text-gray-700">
                        {{ __('I agree to the') }}
                        <a href="/terms" target="_blank" class="text-indigo-600 hover:underline font-semibold">{{ __('Terms of Service') }}</a>
                        {{ __('and') }}
                        <a href="/privacy" target="_blank" class="text-indigo-600 hover:underline font-semibold">{{ __('Privacy Policy') }}</a>
                    </span>
                </label>
            </div>
        </div>

        {{-- Submit Button --}}
        <div class="text-center">
            <button
                type="submit"
                :disabled="processing || !tokenizeCard"
                class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 disabled:from-gray-400 disabled:to-gray-500 text-white font-bold py-5 px-10 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-200 transform hover:scale-105 disabled:scale-100 disabled:cursor-not-allowed"
            >
                <span x-show="!processing" class="flex items-center justify-center gap-3">
                    <x-heroicon-o-arrow-path class="w-6 h-6" />
                    {{ __('Start Subscription - ₪:amount/:cycle', [
                        'amount' => number_format($payable->getPayableAmount(), 2),
                        'cycle' => $payable->billing_cycle === 'monthly' ? __('month') : __('year')
                    ]) }}
                </span>
                <span x-show="processing" class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Processing...') }}
                </span>
            </button>

            <p class="text-xs text-gray-500 mt-4 flex items-center justify-center gap-2">
                <x-heroicon-o-lock-closed class="w-4 h-4" />
                {{ __('Your payment is secure and encrypted. You can cancel anytime.') }}
            </p>

            {{-- Next Billing Date --}}
            @if($payable->billing_cycle === 'monthly')
                <p class="text-sm text-gray-600 mt-2">
                    {{ __('Next billing date: :date', ['date' => now()->addMonth()->format('F j, Y')]) }}
                </p>
            @elseif($payable->billing_cycle === 'yearly')
                <p class="text-sm text-gray-600 mt-2">
                    {{ __('Next billing date: :date', ['date' => now()->addYear()->format('F j, Y')]) }}
                </p>
            @endif
        </div>
    </form>

    {{-- Cancellation Info --}}
    <div class="mt-8 bg-gray-50 border border-gray-200 rounded-xl p-6">
        <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
            <x-heroicon-o-x-circle class="w-5 h-5 text-gray-600" />
            {{ __('How to Cancel Your Subscription') }}
        </h4>
        <div class="space-y-3">
            <div class="flex items-start gap-3">
                <x-heroicon-o-envelope class="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-gray-700">
                    <span class="font-semibold">{{ __('Email Support') }}:</span>
                    <a href="mailto:{{ $siteSettings->contact_email }}" class="text-indigo-600 hover:underline">
                        {{ $siteSettings->contact_email }}
                    </a>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <x-heroicon-o-phone class="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-gray-700">
                    <span class="font-semibold">{{ __('Phone Support') }}:</span>
                    <a href="tel:{{ $siteSettings->contact_phone }}" class="text-indigo-600 hover:underline" dir="ltr">
                        {{ $siteSettings->contact_phone }}
                    </a>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <x-heroicon-o-chat-bubble-left-right class="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
                <span class="text-sm text-gray-700">{{ __('Use the live chat in your account dashboard') }}</span>
            </div>
            <div class="flex items-start gap-3">
                <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
                <span class="text-sm text-gray-700">{{ __('Cancel directly from your Subscription Management page') }}</span>
            </div>
            <div class="flex items-start gap-3">
                <x-heroicon-o-ticket class="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-gray-700">
                    <span class="font-semibold">{{ __('Support Ticket') }}:</span>
                    <a href="{{ route('filament.client.resources.tickets.create') }}" class="text-indigo-600 hover:underline">
                        {{ __('Open a cancellation ticket') }}
                    </a>
                </div>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-300">
            <p class="text-xs text-gray-600 flex items-start gap-2">
                <x-heroicon-o-exclamation-circle class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                <span>{{ __('Remember: Cancellations must be made at least 24 hours before your next billing date to avoid being charged.') }}</span>
            </p>
        </div>
    </div>
</div>
@endsection
