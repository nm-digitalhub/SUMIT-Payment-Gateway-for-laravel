@php
/**
 * Payment Section Partial
 *
 * Unified payment interface for all checkout templates.
 * Supports: Credit Card, Bit, Saved Cards, Installments, Subscriptions
 *
 * Required Variables:
 * - $settings (array): Payment configuration from Admin Panel
 * - $maxPayments (int): Maximum installments allowed
 * - $bitEnabled (bool): Whether Bit payment is enabled
 * - $supportTokens (bool): Whether saved cards feature is enabled
 * - $savedTokens (Collection): User's saved payment methods
 * - $rtl (bool): RTL language direction
 * - $prefillCitizenId (string|null): Pre-filled ID number
 */
@endphp

{{-- Payment Method Tabs (if Bit is enabled) --}}
@if($bitEnabled)
<div class="flex gap-3 mb-4">
    <button
        type="button"
        @click="paymentMethod = 'card'"
        :class="paymentMethod === 'card' ? 'border-primary bg-[#DBEAFE] text-primary' : 'border-gray-200 hover:border-gray-300'"
        class="flex-1 p-3 border-2 rounded-lg transition-colors text-center"
    >
        <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
        </svg>
        <span class="text-sm font-medium">{{ __('Credit Card') }}</span>
    </button>

    <button
        type="button"
        @click="paymentMethod = 'bit'"
        :class="paymentMethod === 'bit' ? 'border-primary bg-[#DBEAFE] text-primary' : 'border-gray-200 hover:border-gray-300'"
        class="flex-1 p-3 border-2 rounded-lg transition-colors text-center"
    >
        <span class="text-blue-600 font-bold text-lg block mb-1">Bit</span>
        <span class="text-sm font-medium">Bit</span>
    </button>
</div>
@endif

<input type="hidden" name="payment_method" x-model="paymentMethod">
<input type="hidden" name="payment_token" x-model="selectedToken">

{{-- Credit Card Fields --}}
<div x-show="paymentMethod === 'card'" x-cloak class="space-y-4">
    {{-- Saved Payment Methods --}}
    @if($supportTokens && $savedTokens->isNotEmpty())
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <label class="block text-sm font-semibold text-[#383E53]">{{ __('Saved Payment Methods') }}</label>
            <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-primary bg-[#DBEAFE] rounded-full">
                {{ $savedTokens->count() }} {{ $savedTokens->count() === 1 ? __('card') : __('cards') }}
            </span>
        </div>
        <div class="space-y-3">
            @foreach($savedTokens as $token)
            <label
                class="relative flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 group"
                :class="selectedToken === '{{ $token->id }}'
                    ? 'border-primary bg-gradient-to-br from-secondary to-secondary-light shadow-md'
                    : 'border-[#E9E9E9] bg-white hover:border-primary hover:shadow-sm'"
            >
                <input
                    type="radio"
                    name="payment_token_choice"
                    value="{{ $token->id }}"
                    x-model="selectedToken"
                    class="text-primary focus:ring-[#3B82F6] focus:ring-offset-2 w-5 h-5"
                >
                <div class="{{ $rtl ? 'mr-4' : 'ml-4' }} flex-1 flex items-center gap-4">
                    <div class="flex-shrink-0 w-14 h-10 rounded-lg flex items-center justify-center shadow-md">
                        @php $cardType = strtolower($token->card_type ?? ''); @endphp
                        @if($cardType === 'visa')
                        <svg class="w-14 h-10" viewBox="0 0 48 32" fill="none">
                            <rect width="48" height="32" rx="4" fill="#1A1F71"/>
                            <path d="M20.5 11l-3.5 10h2.2l3.5-10h-2.2zm6.5 6.5l1.2-3.3.7 3.3h-1.9zm2.5 3.5h2l-1.8-10h-1.8c-.4 0-.8.2-.9.6l-3.2 9.4h2.3l.5-1.3h2.9l.3 1.3zm-7.8-3.2c0-2.6-3.6-2.8-3.6-4 0-.4.4-.7 1.2-.8.4 0 1.5-.1 2.7.5l.5-2.2c-.7-.2-1.5-.5-2.6-.5-2.5 0-4.2 1.3-4.2 3.2 0 1.4 1.2 2.2 2.2 2.6 1 .5 1.3.8 1.3 1.2 0 .6-.8.9-1.5.9-1.3 0-2-.2-3.1-.7l-.5 2.3c.7.3 2 .6 3.4.6 2.6-.1 4.3-1.3 4.3-3.1zm-9.7-6.8l-4 10h-2.3l-2-7.5c-.1-.4-.2-.6-.6-.7-.6-.3-1.7-.6-2.6-.8l.1-.4h4.4c.6 0 1.1.4 1.2 1l1.1 5.8 2.7-6.8h2.3z" fill="white"/>
                        </svg>
                        @elseif($cardType === 'mastercard')
                        <svg class="w-14 h-10" viewBox="0 0 48 32" fill="none">
                            <rect width="48" height="32" rx="4" fill="#F4F4F4"/>
                            <circle cx="18" cy="16" r="9" fill="#EB001B"/>
                            <circle cx="30" cy="16" r="9" fill="#F79E1B"/>
                            <path d="M24 8.5c-2 1.5-3.3 3.9-3.3 6.5s1.3 5 3.3 6.5c2-1.5 3.3-3.9 3.3-6.5s-1.3-5-3.3-6.5z" fill="#FF5F00"/>
                        </svg>
                        @elseif($cardType === 'american express' || $cardType === 'amex')
                        <svg class="w-14 h-10" viewBox="0 0 48 32" fill="none">
                            <rect width="48" height="32" rx="4" fill="#006FCF"/>
                            <path d="M14.5 11l-2 10h2.3l.3-1.5h1.5l.3 1.5h2.6V11h-2v6.7l-1.3-6.7h-1.7zm7.5 4.2l1.5-4.2h2.3l-2.5 6v4h-2.3v-4l-2.5-6h2.3l1.5 4.2zm9 1.8h-2.5v1.3h2.5v1.7h-5V11h5v1.7h-2.5v1.3h2.5v2zm7 4h-2.3l-.3-1h-2l-.3 1h-2.3l2.5-10h2.3l2.4 10zm-3.2-3.5l-.6-3-.6 3h1.2z" fill="white"/>
                        </svg>
                        @else
                        <div class="w-14 h-10 bg-gradient-to-br from-[#3B82F6] to-[#2563EB] rounded-lg flex items-center justify-center shadow-md">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                        </div>
                        @endif
                    </div>
                    <div class="flex-1 {{ $rtl ? 'text-right' : 'text-left' }}">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-bold text-[#111928] text-base tracking-wider">{{ $token->getMaskedNumber() }}</span>
                            @if($token->is_default)
                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold text-white bg-gradient-to-r from-[#3B82F6] to-[#2563EB] rounded-full shadow-md">
                                ✓ {{ __('Default') }}
                            </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-sm text-[#8890B1]">
                            <span>{{ __('Expires') }}: {{ $token->expiry_month }}/{{ $token->expiry_year }}</span>
                        </div>
                    </div>
                </div>
                <div x-show="selectedToken === '{{ $token->id }}'" x-cloak class="absolute top-2 {{ $rtl ? 'left-2' : 'right-2' }} w-6 h-6 bg-primary rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </label>
            @endforeach

            {{-- New Card Option --}}
            <label
                class="relative flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 group"
                :class="selectedToken === 'new'
                    ? 'border-primary bg-gradient-to-br from-secondary to-secondary-light shadow-md'
                    : 'border-dashed border-[#E9E9E9] bg-white hover:border-primary hover:border-solid hover:shadow-sm'"
            >
                <input type="radio" name="payment_token_choice" value="new" x-model="selectedToken" class="text-primary focus:ring-[#3B82F6] focus:ring-offset-2 w-5 h-5">
                <div class="{{ $rtl ? 'mr-4' : 'ml-4' }} flex-1 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg flex items-center justify-center group-hover:from-secondary group-hover:to-secondary-light transition-colors">
                        <svg class="w-6 h-6 text-gray-500 group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <div class="{{ $rtl ? 'text-right' : 'text-left' }}">
                        <span class="font-semibold text-[#111928] text-base">{{ __('Use a new card') }}</span>
                        <p class="text-sm text-[#8890B1] mt-0.5">{{ __('Add and save a new payment method') }}</p>
                    </div>
                </div>
                <div x-show="selectedToken === 'new'" x-cloak class="absolute top-2 {{ $rtl ? 'left-2' : 'right-2' }} w-6 h-6 bg-primary rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </label>
        </div>
    </div>
    @endif

    {{-- New Card Fields --}}
    <div x-show="selectedToken === 'new'" x-cloak class="space-y-4">
        {{-- Card Number --}}
        <div>
            <label for="og-ccnum" class="block text-sm font-medium text-[#383E53] mb-2 {{ $rtl ? 'text-right' : 'text-left' }}">
                {{ __('Card Number') }} <span class="text-[#FF7878]">*</span>
            </label>
            <div class="relative">
                <input
                    type="text"
                    id="og-ccnum"
                    name="card_number"
                    data-og="cardnumber"
                    x-model="cardNumber"
                    dir="ltr"
                    maxlength="19"
                    inputmode="numeric"
                    autocomplete="cc-number"
                    placeholder="0000 0000 0000 0000"
                    class="w-full bg-[#F2F4F7] border border-[#E9E9E9] rounded-lg px-4 py-3 {{ $rtl ? 'pl-10' : 'pr-10' }} text-[#383E53] text-left placeholder-[#8890B1] focus:ring-2 focus:ring-[#3B82F6] focus:border-transparent transition-all"
                >
                <div class="absolute {{ $rtl ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2">
                    <svg x-show="!cardType" class="w-5 h-5 text-[#8890B1]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[#8890B1] text-xs mt-1 {{ $rtl ? 'text-right' : 'text-left' }}">{{ __('Enter 13-19 digits') }}</p>
        </div>

        {{-- Expiration & CVV --}}
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-[#383E53] mb-2 {{ $rtl ? 'text-right' : 'text-left' }}">{{ __('Month') }} <span class="text-[#FF7878]">*</span></label>
                <select id="og-expmonth" name="exp_month" data-og="expirationmonth" x-model="expMonth" class="w-full bg-[#F2F4F7] border border-[#E9E9E9] rounded-lg px-3 py-3 text-[#383E53] focus:ring-2 focus:ring-[#3B82F6] focus:border-transparent transition-all">
                    <option value="">MM</option>
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#383E53] mb-2 {{ $rtl ? 'text-right' : 'text-left' }}">{{ __('Year') }} <span class="text-[#FF7878]">*</span></label>
                <select id="og-expyear" name="exp_year" data-og="expirationyear" x-model="expYear" class="w-full bg-[#F2F4F7] border border-[#E9E9E9] rounded-lg px-3 py-3 text-[#383E53] focus:ring-2 focus:ring-[#3B82F6] focus:border-transparent transition-all">
                    <option value="">YY</option>
                    @for($i = date('Y'); $i <= date('Y') + 15; $i++)
                        <option value="{{ $i }}">{{ substr($i, -2) }}</option>
                    @endfor
                </select>
            </div>
            @if(in_array($settings['cvv_mode'], ['required', 'yes']))
            <div>
                <label class="block text-sm font-medium text-[#383E53] mb-2 {{ $rtl ? 'text-right' : 'text-left' }}">
                    CVV @if($settings['cvv_mode'] === 'required')<span class="text-[#FF7878]">*</span>@endif
                </label>
                <input
                    type="text"
                    id="og-cvv"
                    name="cvv"
                    data-og="cvv"
                    x-model="cvv"
                    dir="ltr"
                    maxlength="4"
                    inputmode="numeric"
                    placeholder="•••"
                    class="w-full bg-[#F2F4F7] border border-[#E9E9E9] rounded-lg px-3 py-3 text-[#383E53] text-center placeholder-[#8890B1] focus:ring-2 focus:ring-[#3B82F6] focus:border-transparent transition-all"
                >
            </div>
            @endif
        </div>
        <p class="text-[#8890B1] text-xs {{ $rtl ? 'text-right' : 'text-left' }}">{{ __('3-4 digits on back of card') }}</p>

        {{-- ID Number --}}
        @if(in_array($settings['citizen_id_mode'], ['required', 'yes']))
        <div>
            <label class="block text-sm font-medium text-[#383E53] mb-2 {{ $rtl ? 'text-right' : 'text-left' }}">
                {{ __('ID Number') }} @if($settings['citizen_id_mode'] === 'required')<span class="text-[#FF7878]">*</span>@endif
            </label>
            <input
                type="text"
                id="og-citizenid"
                name="citizen_id"
                data-og="citizenid"
                x-model="citizenId"
                dir="ltr"
                maxlength="9"
                inputmode="numeric"
                placeholder="000000000"
                @if(!empty($prefillCitizenId))
                    value="{{ $prefillCitizenId }}"
                    readonly
                    class="w-full bg-gray-100 border border-gray-200 rounded-lg px-4 py-3 text-gray-600 cursor-not-allowed"
                @else
                    class="w-full bg-[#F2F4F7] border border-[#E9E9E9] rounded-lg px-4 py-3 text-[#383E53] text-left placeholder-[#8890B1] focus:ring-2 focus:ring-[#3B82F6] focus:border-transparent transition-all"
                @endif
            >
        </div>
        @endif

        @if($settings['pci_mode'] === 'no')
        <input type="hidden" name="og-token" data-og="token" x-model="singleUseToken">
        @endif

        {{-- Save Card --}}
        @if($supportTokens && auth()->check())
        <div class="flex items-center gap-3">
            <input type="checkbox" id="save_card" name="save_card" x-model="saveCard" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-[#3B82F6]">
            <label for="save_card" class="text-sm text-[#383E53]">{{ __('Save card for future purchases') }}</label>
        </div>
        @endif
    </div>
</div>

{{-- Bit Payment --}}
@if($bitEnabled)
<div x-show="paymentMethod === 'bit'" x-cloak class="bg-blue-50 rounded-lg p-4">
    <p class="text-sm text-gray-700">{{ __('You will be redirected to complete your payment via Bit.') }}</p>
</div>
@endif

{{-- Installments --}}
@if($maxPayments > 1)
<div x-show="paymentMethod === 'card'" class="mt-4">
    <label class="block text-sm font-medium text-[#383E53] mb-2 {{ $rtl ? 'text-right' : 'text-left' }}">{{ __('Number of Payments') }}</label>
    <select name="payments_count" x-model="paymentsCount" class="w-full bg-[#F2F4F7] border border-[#E9E9E9] rounded-lg px-4 py-3 text-[#383E53] focus:ring-2 focus:ring-[#3B82F6] focus:border-transparent transition-all">
        @for($i = 1; $i <= $maxPayments; $i++)
            <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? __('payment') : __('payments') }}</option>
        @endfor
    </select>
</div>
@endif

{{-- Terms Checkbox --}}
<div class="flex items-start gap-3 pt-2">
    <input
        type="checkbox"
        id="payment-terms"
        name="accept_terms"
        x-model="acceptTerms"
        class="mt-1 w-4 h-4 text-primary border-gray-300 rounded focus:ring-[#3B82F6]"
        required
    >
    <label for="payment-terms" class="text-sm text-[#383E53] {{ $rtl ? 'text-right' : 'text-left' }}">
        {{ __('I agree to the') }} <a href="#" class="text-primary hover:underline">{{ __('Terms & Conditions') }}</a> {{ __('and') }} <a href="#" class="text-primary hover:underline">{{ __('Privacy Policy') }}</a>
    </label>
</div>
