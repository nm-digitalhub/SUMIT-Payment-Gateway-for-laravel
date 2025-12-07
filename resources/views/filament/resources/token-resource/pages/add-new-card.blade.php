<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Instructions Card --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100">How to Add a New Card</h3>
                    <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">
                        Enter the card details below. All information is securely processed through SUMIT's encrypted payment gateway.
                    </p>
                </div>
            </div>
        </div>

        {{-- Payment Form Card --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <form wire:submit.prevent="processNewCard" id="payment-form">
                <div class="space-y-4">
                    {{-- Card Number --}}
                    <div>
                        <label for="card-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Card Number
                        </label>
                        <div id="card-number" class="sumit-hosted-field"></div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        {{-- Expiry Month --}}
                        <div>
                            <label for="expiry-month" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Month
                            </label>
                            <div id="expiry-month" class="sumit-hosted-field"></div>
                        </div>

                        {{-- Expiry Year --}}
                        <div>
                            <label for="expiry-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Year
                            </label>
                            <div id="expiry-year" class="sumit-hosted-field"></div>
                        </div>

                        {{-- CVV --}}
                        <div>
                            <label for="cvv" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                CVV
                            </label>
                            <div id="cvv" class="sumit-hosted-field"></div>
                        </div>
                    </div>

                    {{-- Citizen ID --}}
                    <div>
                        <label for="citizen-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            ID Number (תעודת זהות)
                        </label>
                        <div id="citizen-id" class="sumit-hosted-field"></div>
                    </div>

                    {{-- Set as Default --}}
                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            id="set_as_default"
                            name="set_as_default"
                            checked
                            class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700"
                        >
                        <label for="set_as_default" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                            Set as default payment method
                        </label>
                    </div>

                    {{-- Hidden field for single-use token --}}
                    <input type="hidden" name="single_use_token" id="single_use_token">

                    {{-- Submit Button --}}
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a
                            href="{{ \OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource::getUrl('index') }}"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                        >
                            Cancel
                        </a>
                        <button
                            type="submit"
                            id="submit-button"
                            disabled
                            class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 active:bg-primary-900 focus:outline-none focus:border-primary-900 focus:ring focus:ring-primary-300 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white hidden" id="loading-spinner" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Add Payment Card
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Security Notice --}}
        <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
                <div class="flex-1">
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        🔒 Your card information is encrypted and securely transmitted to SUMIT.
                        This application never stores your full card number or CVV.
                    </p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://{{ $this->getEnvironment() === 'dev' ? 'dev.' : '' }}payments.sumit.co.il/content/scripts/payments.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const publicKey = '{{ $this->getPublicKey() }}';
            const submitButton = document.getElementById('submit-button');
            const loadingSpinner = document.getElementById('loading-spinner');
            const form = document.getElementById('payment-form');

            // Initialize SUMIT PaymentsJS
            const paymentsClient = new Payments(publicKey);

            // Define hosted fields configuration
            const hostedFieldsConfig = {
                fields: {
                    cardNumber: {
                        selector: '#card-number',
                        placeholder: '•••• •••• •••• ••••'
                    },
                    expirationMonth: {
                        selector: '#expiry-month',
                        placeholder: 'MM'
                    },
                    expirationYear: {
                        selector: '#expiry-year',
                        placeholder: 'YYYY'
                    },
                    cvv: {
                        selector: '#cvv',
                        placeholder: '•••'
                    },
                    citizenID: {
                        selector: '#citizen-id',
                        placeholder: '123456789'
                    }
                },
                styles: {
                    input: {
                        'font-size': '14px',
                        'font-family': 'system-ui, -apple-system, sans-serif',
                        'color': '#1f2937',
                        'padding': '8px 12px',
                        '::placeholder': {
                            'color': '#9ca3af'
                        }
                    },
                    '.invalid': {
                        'color': '#ef4444'
                    }
                }
            };

            // Create hosted fields instance
            const hostedFields = paymentsClient.hostedFields(hostedFieldsConfig);

            // Enable submit button when all fields are valid
            hostedFields.on('change', function(event) {
                const allFieldsValid = Object.values(event.fields).every(field => field.isValid);
                submitButton.disabled = !allFieldsValid;
            });

            // Handle form submission
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                submitButton.disabled = true;
                loadingSpinner.classList.remove('hidden');

                try {
                    // Tokenize card data
                    const result = await hostedFields.tokenize();

                    if (result.success && result.token) {
                        // Set the single-use token
                        document.getElementById('single_use_token').value = result.token;

                        // Submit form via Livewire
                        @this.call('processNewCard');
                    } else {
                        throw new Error(result.error || 'Tokenization failed');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Failed to process card: ' + error.message);
                    submitButton.disabled = false;
                    loadingSpinner.classList.add('hidden');
                }
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
