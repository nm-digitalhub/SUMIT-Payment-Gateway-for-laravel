@php
    $fieldId = 'og-hosted-token-form-' . uniqid();
    $tokenInputId = $fieldId . '-token';
@endphp

<div class="space-y-4" x-data="{
    status: 'idle',
    message: null,
    tokenGenerated: false,
    isGenerating: false
}">
    {{-- SDKs --}}
    <script src="https://code.jquery.com/jquery-3.6.4.min.js" crossorigin="anonymous"></script>
    <script src="https://app.sumit.co.il/scripts/payments.js"></script>

    {{-- Status Messages --}}
    <div x-show="message" x-transition class="rounded-lg p-4" :class="{
        'bg-green-50 border border-green-200 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-200': status === 'success',
        'bg-red-50 border border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-200': status === 'error',
        'bg-blue-50 border border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-200': status === 'loading'
    }">
        <div class="flex items-center gap-2">
            <svg x-show="status === 'loading'" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg x-show="status === 'success'" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg x-show="status === 'error'" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-text="message"></span>
        </div>
    </div>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header flex flex-col gap-2 px-6 py-4">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Enter card details</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">Your card details are encrypted and never stored on our servers.</p>
        </div>
        <div class="fi-section-content p-6">
            <form id="{{ $fieldId }}" class="space-y-4">
                <input type="hidden" id="{{ $tokenInputId }}" name="og-token" data-og="token" x-ref="tokenInput">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Card Number
                        <input type="text" data-og="cardnumber" autocomplete="cc-number"
                               class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        CVV
                        <input type="password" data-og="cvv" maxlength="4" autocomplete="cc-csc"
                               class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Expiry Month
                        <select data-og="expirationmonth"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Month</option>
                            @for ($i = 1; $i <= 12; $i++)
                                <option value="{{ sprintf('%02d', $i) }}">{{ sprintf('%02d', $i) }}</option>
                            @endfor
                        </select>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Expiry Year
                        <select data-og="expirationyear"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Year</option>
                            @for ($i = date('Y'); $i <= date('Y') + 15; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                    </label>
                </div>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Cardholder ID
                    <input type="text" data-og="citizenid"
                           class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </label>

                {{-- הסר את הכפתור הישן - עכשיו נשתמש בכפתור Create של Filament --}}
            </form>

            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <strong>Ready to save?</strong> Click the "Create" button below to securely save your card.
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('{{ $fieldId }}');
            const tokenInput = document.getElementById('{{ $tokenInputId }}');
            const livewireId = @js($livewireId);
            const companyId = @js($companyId);
            const publicKey = @js($publicKey);

            // Get Alpine context
            const alpineComponent = form.closest('[x-data]');
            const alpine = alpineComponent?._x_dataStack?.[0];

            // Verify SUMIT SDK loaded
            if (!window.OfficeGuy?.Payments) {
                console.error('❌ SUMIT SDK not loaded');
                if (alpine) {
                    alpine.status = 'error';
                    alpine.message = 'Payment system not loaded. Please refresh the page.';
                }
                return;
            }

            console.log('✅ SUMIT SDK loaded');

            // Flag to prevent double submission
            let isProcessing = false;

            // Intercept Filament "Create" button click
            document.addEventListener('click', (e) => {
                const createButton = e.target.closest('button[type="submit"]');
                if (!createButton || !createButton.closest('form.fi-form')) return;

                // Prevent double processing
                if (isProcessing) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }

                // Prevent default Filament submission
                e.preventDefault();
                e.stopPropagation();

                isProcessing = true;

                if (alpine) {
                    alpine.status = 'loading';
                    alpine.message = 'Generating secure token...';
                    alpine.isGenerating = true;
                }

                // Settings for SUMIT SDK (like WooCommerce)
                const settings = {
                    FormSelector: form,
                    CompanyID: companyId,
                    APIPublicKey: publicKey,
                    ResponseLanguage: 'he',
                    Callback: function(tokenValue) {
                        console.log('📝 Token callback received:', tokenValue ? '✓ Valid' : '✗ Invalid');

                        if (tokenValue && tokenValue.length > 0) {
                            // Success - token generated
                            if (alpine) {
                                alpine.status = 'success';
                                alpine.message = '✓ Secure token generated successfully';
                                alpine.tokenGenerated = true;
                            }

                            // Get Livewire component
                            const livewireComponent = window.Livewire?.find(livewireId);

                            if (livewireComponent) {
                                // Update Livewire data with token
                                livewireComponent.$wire.set('data.og-token', tokenValue);

                                // Wait briefly then submit Filament form
                                setTimeout(() => {
                                    if (alpine) {
                                        alpine.status = 'loading';
                                        alpine.message = 'Saving payment method...';
                                    }

                                    // Submit the Filament form
                                    const filamentForm = createButton.closest('form.fi-form');
                                    if (filamentForm) {
                                        // Create new submit event
                                        const submitEvent = new Event('submit', {
                                            bubbles: true,
                                            cancelable: true
                                        });
                                        filamentForm.dispatchEvent(submitEvent);
                                    }
                                }, 200);
                            } else {
                                console.error('❌ Livewire component not found:', livewireId);
                                if (alpine) {
                                    alpine.status = 'error';
                                    alpine.message = 'Failed to communicate with form system.';
                                    alpine.isGenerating = false;
                                }
                                isProcessing = false;
                            }
                        } else {
                            // Token generation failed
                            console.error('❌ Token generation failed');
                            if (alpine) {
                                alpine.status = 'error';
                                alpine.message = 'Failed to generate token. Please verify card details.';
                                alpine.isGenerating = false;
                            }
                            isProcessing = false;
                        }
                    }
                };

                // Call SUMIT SDK CreateToken (like WooCommerce)
                try {
                    const result = window.OfficeGuy.Payments.CreateToken(settings);
                    console.log('⏳ SUMIT SDK CreateToken called, result:', result);
                } catch (error) {
                    console.error('❌ SDK CreateToken error:', error);
                    if (alpine) {
                        alpine.status = 'error';
                        alpine.message = 'Failed to initialize token generation: ' + error.message;
                        alpine.isGenerating = false;
                    }
                    isProcessing = false;
                }

                return false;
            }, true); // Use capture phase
        });
    </script>
</div>
