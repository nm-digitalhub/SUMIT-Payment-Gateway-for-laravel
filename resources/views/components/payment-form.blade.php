<div class="officeguy-payment-form" x-data="officeGuyPayment">
    {{-- Error messages --}}
    <div x-show="errors.length > 0" class="og-errors bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" x-cloak aria-live="assertive">
        <template x-for="error in errors" :key="error">
            <div x-text="error"></div>
        </template>
    </div>

    {{-- Saved payment methods (if enabled) --}}
    @if($showSavedMethods && $savedTokens->isNotEmpty())
    <div class="og-saved-methods mb-4">
        <h3 class="text-lg font-medium mb-2">{{ __('Payment Method') }}</h3>
        
        @foreach($savedTokens as $token)
        <label class="block mb-2">
            <input 
                type="radio" 
                name="wc-officeguy-payment-token" 
                value="{{ $token->id }}"
                x-model="selectedToken"
                @change="togglePaymentFields"
            >
            <span class="ml-2">{{ $token->getMaskedNumber() }} ({{ __('Expires') }} {{ $token->expiry_month }}/{{ $token->expiry_year }})</span>
        </label>
        @endforeach

        <label class="block mb-2">
            <input 
                type="radio" 
                name="wc-officeguy-payment-token" 
                value="new"
                x-model="selectedToken"
                @change="togglePaymentFields"
            >
            <span class="ml-2">{{ __('Use a new payment method') }}</span>
        </label>
    </div>
    @endif

    {{-- New payment form --}}
    <div class="og-payment-form" x-show="selectedToken === 'new'" x-cloak :class="rtl ? 'text-right' : ''">
        {{-- Card number --}}
        <div class="mb-4" :class="rtl ? 'text-right' : ''">
            <label for="og-ccnum" class="block text-sm font-medium mb-1">
                {{ __('Card Number') }} <span class="text-red-500">*</span>
            </label>
            <input 
                type="text" 
                id="og-ccnum" 
                name="og-ccnum"
                x-model="cardNumber"
                maxlength="19"
                inputmode="numeric"
                autocomplete="cc-number"
                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                placeholder="•••• •••• •••• ••••"
                data-og-message="{{ __('Card number is required') }}"
                required
            >
        </div>

        {{-- Expiration date --}}
        <div class="grid {{ $singleColumn ? 'grid-cols-1' : 'grid-cols-2' }} gap-4 mb-4" :class="rtl ? 'text-right' : ''">
            <div :class="rtl ? 'rtl' : ''">
                <label for="og-expmonth" class="block text-sm font-medium mb-1">
                    {{ __('Expiration Month') }} <span class="text-red-500">*</span>
                </label>
                <select 
                    id="og-expmonth" 
                    name="og-expmonth"
                    x-model="expMonth"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md"
                    autocomplete="cc-exp-month"
                data-og-message="{{ __('Expiration date is required') }}"
                required
                >
                    <option value="">{{ __('Month') }}</option>
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</option>
                    @endfor
                </select>
            </div>
            <div :class="rtl ? 'rtl' : ''">
                <label for="og-expyear" class="block text-sm font-medium mb-1">
                    {{ __('Expiration Year') }} <span class="text-red-500">*</span>
                </label>
                <select 
                    id="og-expyear" 
                    name="og-expyear"
                    x-model="expYear"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md"
                    autocomplete="cc-exp-year"
                    required
                >
                    <option value="">{{ __('Year') }}</option>
                    @php
                        $currentYear = date('Y');
                        $yearRange = $fourDigitsYear ? 20 : 10;
                    @endphp
                    @for($i = 0; $i <= $yearRange; $i++)
                        @php
                            $year = $currentYear + $i;
                            $displayYear = $fourDigitsYear ? $year : substr($year, -2);
                        @endphp
                        <option value="{{ $year }}">{{ $displayYear }}</option>
                    @endfor
                </select>
            </div>
        </div>

        {{-- CVV (if required) --}}
        @if(in_array($cvvMode, ['required', 'yes']))
        <div class="mb-4" :class="rtl ? 'text-right' : ''">
            <label for="og-cvv" class="block text-sm font-medium mb-1">
                {{ __('Security Code (CVV)') }} 
                @if($cvvMode === 'required')<span class="text-red-500">*</span>@endif
            </label>
            <input 
                type="text" 
                id="og-cvv" 
                name="og-cvv"
                x-model="cvv"
                maxlength="4"
                inputmode="numeric"
                autocomplete="cc-csc"
                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                placeholder="•••"
                data-og-message="{{ __('Security code is required') }}"
                @if($cvvMode === 'required') required @endif
            >
        </div>
        @endif

        {{-- Citizen ID (if required) --}}
        @if(in_array($citizenIdMode, ['required', 'yes']))
        <div class="mb-4" :class="rtl ? 'text-right' : ''">
            <label for="og-citizenid" class="block text-sm font-medium mb-1">
                {{ __('ID Number') }}
                @if($citizenIdMode === 'required')<span class="text-red-500">*</span>@endif
            </label>
            <input 
                type="text" 
                id="og-citizenid" 
                name="og-citizenid"
                x-model="citizenId"
                maxlength="9"
                inputmode="numeric"
                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                data-og-message="{{ __('ID number is required') }}"
                @if($citizenIdMode === 'required') required @endif
            >
        </div>
        @endif

        {{-- Installments (if applicable) --}}
        @if($maxPayments > 1)
        <div class="mb-4">
            <label for="og-paymentscount" class="block text-sm font-medium mb-1">
                {{ __('Number of Payments') }}
            </label>
            <select 
                id="og-paymentscount" 
                name="og-paymentscount"
                x-model="paymentsCount"
                class="w-full px-3 py-2 border border-gray-300 rounded-md"
            >
                @for($i = 1; $i <= $maxPayments; $i++)
                    <option value="{{ $i }}">{{ $i }}</option>
                @endfor
            </select>
        </div>
        @endif

        {{-- Save payment method --}}
        @if($supportTokens && $isUserLoggedIn)
        <div class="mb-4">
            <label class="flex items-center">
                <input 
                    type="checkbox" 
                    name="wc-officeguy-new-payment-method"
                    x-model="savePaymentMethod"
                    class="mr-2"
                >
                <span class="text-sm">{{ __('Save payment information to my account for future purchases') }}</span>
            </label>
        </div>
        @endif

        {{-- Hidden token field (for PaymentsJS mode) --}}
        @if($pciMode === 'no')
        <input type="hidden" name="og-token" x-model="singleUseToken">
        @endif
    </div>

    {{-- Token form (when existing card selected) --}}
    @if($showSavedMethods && $savedTokens->isNotEmpty())
    <div class="og-token-form" x-show="selectedToken !== 'new'" x-cloak>
        {{-- CVV for saved card --}}
        @if(in_array($cvvMode, ['required', 'yes']))
        <div class="mb-4">
            <label for="og-cvv-token" class="block text-sm font-medium mb-1">
                {{ __('Security Code (CVV)') }}
                @if($cvvMode === 'required')<span class="text-red-500">*</span>@endif
            </label>
            <input 
                type="text" 
                id="og-cvv-token" 
                name="og-cvv"
                x-model="cvv"
                maxlength="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                placeholder="•••"
                @if($cvvMode === 'required') required @endif
            >
        </div>
        @endif
    </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('officeGuyPayment', () => ({
        selectedToken: '{{ $savedTokens->isEmpty() ? "new" : "" }}',
        cardNumber: '',
        expMonth: '',
        expYear: '',
        cvv: '',
        citizenId: '',
        paymentsCount: '1',
        savePaymentMethod: false,
        singleUseToken: '',
        errors: [],

        togglePaymentFields() {
            this.errors = [];
        },

        validate() {
            this.errors = [];

            if (this.selectedToken === 'new') {
                if (!this.cardNumber) {
                    this.errors.push('{{ __("Card number is required") }}');
                }
                if (!this.expMonth || !this.expYear) {
                    this.errors.push('{{ __("Expiration date is required") }}');
                }
                @if($cvvMode === 'required')
                if (!this.cvv) {
                    this.errors.push('{{ __("Security code is required") }}');
                }
                @endif
                @if($citizenIdMode === 'required')
                if (!this.citizenId) {
                    this.errors.push('{{ __("ID number is required") }}');
                }
                @endif
            } else if (this.selectedToken !== '') {
                @if($cvvMode === 'required')
                if (!this.cvv) {
                    this.errors.push('{{ __("Security code is required") }}');
                }
                @endif
            }

            return this.errors.length === 0;
        }
    }));
});
</script>
@endpush
