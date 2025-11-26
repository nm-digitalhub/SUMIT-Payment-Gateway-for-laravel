@php
    $fieldId = 'og-hosted-token-form-' . uniqid();
    $tokenInputId = $fieldId . '-token';
@endphp

<div x-data="paymentTokenForm()" x-init="init()" class="space-y-4">
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
            <form id="{{ $fieldId }}" class="space-y-4" x-ref="cardForm">
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
            </form>

            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <strong>Ready to save?</strong> Click the "Create" button below to securely save your card.
                </p>
            </div>
        </div>
    </div>

    <script nonce="{{ $csp_nonce ?? '' }}">
        function paymentTokenForm() {
            return {
                status: 'idle',
                message: null,
                tokenGenerated: false,
                isGenerating: false,
                createButton: null,
                originalClickHandler: null,

                init() {
                    console.log('[OG Token Form] Alpine component initialized');

                    // Wait for Livewire to be ready
                    if (window.Livewire) {
                        this.setupCreateButton();
                    } else {
                        document.addEventListener('livewire:init', () => {
                            this.setupCreateButton();
                        });
                    }

                    // Verify SUMIT SDK loaded
                    if (!window.OfficeGuy?.Payments) {
                        console.error('[OG Token Form] SUMIT SDK not loaded');
                        this.status = 'error';
                        this.message = 'Payment system not loaded. Please refresh the page.';
                    } else {
                        console.log('[OG Token Form] SUMIT SDK loaded successfully');
                    }
                },

                setupCreateButton() {
                    console.log('[OG Token Form] Setting up Create button');

                    // Find Filament's Create button
                    // It can be: button[type="submit"], button with wire:click, or form submit
                    this.createButton = document.querySelector('button[type="submit"][wire\\:click*="create"]') ||
                                       document.querySelector('button[wire\\:click*="create"]') ||
                                       document.querySelector('form button[type="submit"]');

                    if (!this.createButton) {
                        console.warn('[OG Token Form] Create button not found, will try form submit');
                        // Fallback: listen to any form submit in the page
                        const forms = document.querySelectorAll('form');
                        forms.forEach(form => {
                            form.addEventListener('submit', (e) => this.handleSubmit(e));
                        });
                        return;
                    }

                    console.log('[OG Token Form] Create button found, attaching click listener');

                    // Attach click listener with high priority (capture phase)
                    this.createButton.addEventListener('click', (e) => this.handleButtonClick(e), true);
                },

                handleButtonClick(event) {
                    console.log('[OG Token Form] Create button clicked, token status:', this.tokenGenerated);

                    // If token already generated, allow normal submission
                    if (this.tokenGenerated) {
                        console.log('[OG Token Form] Token exists, allowing submission');
                        return true;
                    }

                    // If currently generating, ignore
                    if (this.isGenerating) {
                        console.log('[OG Token Form] Token generation in progress, blocking click');
                        event.preventDefault();
                        event.stopPropagation();
                        event.stopImmediatePropagation();
                        return false;
                    }

                    // Check if token already exists in the hidden field
                    const existingToken = this.$refs.tokenInput.value;
                    if (existingToken && existingToken.length > 0) {
                        console.log('[OG Token Form] Token already in field, allowing submission');
                        this.tokenGenerated = true;
                        return true;
                    }

                    // No token - need to generate it first
                    console.log('[OG Token Form] No token, preventing submission and generating token');
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();

                    this.generateToken();
                    return false;
                },

                handleSubmit(event) {
                    console.log('[OG Token Form] Form submit intercepted');
                    return this.handleButtonClick(event);
                },

                generateToken() {
                    console.log('[OG Token Form] Starting token generation');

                    this.isGenerating = true;
                    this.status = 'loading';
                    this.message = 'Generating secure token...';

                    const settings = {
                        FormSelector: this.$refs.cardForm,
                        CompanyID: @js($companyId),
                        APIPublicKey: @js($publicKey),
                        ResponseLanguage: 'he',
                        Callback: (tokenValue) => {
                            console.log('[OG Token Form] Callback received');

                            if (tokenValue && tokenValue.length > 0) {
                                // Success!
                                console.log('[OG Token Form] Token generated:', tokenValue.substring(0, 10) + '...');

                                this.status = 'success';
                                this.message = 'Token generated successfully';
                                this.tokenGenerated = true;
                                this.isGenerating = false;

                                // Update hidden field
                                this.$refs.tokenInput.value = tokenValue;

                                // Trigger Livewire data update
                                if (window.Livewire) {
                                    // Find the Livewire component
                                    const livewireEl = this.$el.closest('[wire\\:id]');
                                    if (livewireEl) {
                                        const component = window.Livewire.find(livewireEl.getAttribute('wire:id'));
                                        if (component) {
                                            component.$wire.set('data.og-token', tokenValue);
                                        }
                                    }
                                }

                                // Wait a moment, then click the button again
                                setTimeout(() => {
                                    console.log('[OG Token Form] Triggering Create button click');
                                    this.status = 'loading';
                                    this.message = 'Saving payment method...';

                                    if (this.createButton) {
                                        this.createButton.click();
                                    }
                                }, 300);
                            } else {
                                // Token generation failed
                                console.error('[OG Token Form] Token generation failed');

                                this.status = 'error';
                                this.message = 'Failed to generate token. Please verify card details.';
                                this.isGenerating = false;
                                this.tokenGenerated = false;
                            }
                        }
                    };

                    // Call SUMIT SDK to create token
                    try {
                        console.log('[OG Token Form] Calling SUMIT SDK CreateToken');
                        const result = window.OfficeGuy.Payments.CreateToken(settings);
                        console.log('[OG Token Form] CreateToken called, result:', result);
                    } catch (error) {
                        console.error('[OG Token Form] CreateToken error:', error);

                        this.status = 'error';
                        this.message = 'Failed to initialize token generation: ' + error.message;
                        this.isGenerating = false;
                        this.tokenGenerated = false;
                    }
                }
            }
        }
    </script>
</div>
