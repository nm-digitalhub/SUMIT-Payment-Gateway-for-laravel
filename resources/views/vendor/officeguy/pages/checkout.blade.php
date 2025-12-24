@php
    /**
     * Public Checkout Page View - Branded Design v2.0
     *
     * Changes in v2.0 (Branded Design):
     * 1. Blue color scheme (#3B82F6) - NM-DigitalHub branding
     * 2. Added company logo and welcome greeting
     * 3. Enhanced saved cards UI with card-style boxes
     * 4. Improved spacing and visual hierarchy
     *
     * Previous changes:
     * 1. Added Progress Stepper (RTL)
     * 2. Added Trust Badges row
     * 3. Fixed RTL column order (Payment on right)
     * 4. Updated input styling
     * 5. Added trust section with checkmarks
     * 6. Improved Hebrew translations
     */
    $rtl = app()->getLocale() === 'he' || app()->getLocale() === 'ar';
    $amount = $payable->getPayableAmount();
    $items = $payable->getLineItems();
    $shipping = $payable->getShippingAmount();
    $fees = $payable->getFees();
    $user = auth()->user();
    if (!$user && class_exists(\Filament\Facades\Filament::class)) {
        $user = \Filament\Facades\Filament::auth()->user();
    }
    $client = $user?->client;

    $customerName = $prefillName ?? $payable->getCustomerName() ?? ($client->name ?? null) ?? ($user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name : null);
    $customerEmail = $prefillEmail ?? $payable->getCustomerEmail() ?? ($client->email ?? null) ?? ($user->email ?? null);
    $customerPhone = $prefillPhone ?? $payable->getCustomerPhone() ?? ($client->phone ?? null) ?? ($user->phone ?? null);
    $customerCitizenId = $prefillCitizenId ?? ($user->id_number ?? $user->vat_number ?? null) ?? ($client->id_number ?? $client->vat_number ?? null);
    $customerCompany = $prefillCompany ?? $client->company ?? $user->company ?? null;
    $customerVat = $prefillVat ?? $client->vat_number ?? $user->vat_number ?? null;
    $customerAddress = $prefillAddress ?? $client->client_address ?? $client->address ?? null;
    $customerAddress2 = $prefillAddress2 ?? $client->client_address2 ?? null;
    $customerCity = $prefillCity ?? $client->client_city ?? $client->city ?? null;
    $customerState = $prefillState ?? $client->client_state ?? $client->state ?? null;
    $customerCountry = $prefillCountry ?? $client->client_country ?? $client->country ?? 'IL';
    $customerPostal = $prefillPostal ?? $client->client_postal_code ?? $client->postal_code ?? null;

    // ========== DYNAMIC CHECKOUT THEME (NEW - v1.16.0) ==========
    // Get theme from product if it implements HasCheckoutTheme trait
    // NOTE: Brand name is always "NM-DigitalHub" - only colors and content vary
    $theme = method_exists($payable, 'getCheckoutTheme')
        ? $payable->getCheckoutTheme()
        : [
            'colors' => [
                'primary' => $payable->color ?? '#3B82F6',
                'secondary' => '#DBEAFE',
                'hover' => '#2563EB',
            ],
            'branding' => [
                'tagline' => $payable->brand_tagline ?? __('Secure Payment Gateway'),
            ],
            'trust_badges' => [
                ['icon' => 'lock', 'text' => __('SSL Encrypted')],
                ['icon' => 'check-circle', 'text' => __('PCI DSS Compliant')],
                ['icon' => 'cards', 'text' => 'VISA / MC / AMEX'],
            ],
            'progress_steps' => [
                ['number' => 1, 'label' => __('Customer')],
                ['number' => 2, 'label' => __('Payment')],
                ['number' => 3, 'label' => __('Terms')],
                ['number' => 4, 'label' => __('Submit')],
            ],
        ];

    // Extract theme variables for easier access
    $primaryColor = $theme['colors']['primary'] ?? '#3B82F6';
    $secondaryColor = $theme['colors']['secondary'] ?? '#DBEAFE';
    $hoverColor = $theme['colors']['hover'] ?? '#2563EB';
    $brandTagline = $theme['branding']['tagline'] ?? __('Secure Payment Gateway');
    $trustBadges = $theme['trust_badges'] ?? [];
    $progressSteps = $theme['progress_steps'] ?? [];
    $totalSteps = count($progressSteps);
    // =============================================================

    // Current step (can be dynamic based on form state)
    $currentStep = 1;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ __('Checkout') }} - {{ config('app.name') }}</title>
    
    {{-- Global Error Handler - MUST be FIRST to catch all errors --}}
    <script>
        /**
         * Suppress known external SDK errors and resource loading failures
         * These errors are non-critical and don't affect functionality:
         * - TypeError.match() from SUMIT payments.js formatJs function
         * - Resource loading failures (fonts, external scripts)
         * - Empty promise rejections
         */

        // Catch synchronous errors
        window.addEventListener('error', function(event) {
            const message = event.message || '';
            const filename = event.filename || '';

            // Suppress SUMIT SDK errors
            if (filename.includes('app.sumit.co.il') ||
                message.includes('match') ||
                message.includes('undefined is not a function')) {
                console.warn('⚠️ SUMIT SDK error suppressed:', message);
                event.preventDefault();
                return true;
            }

            // Suppress resource loading errors (fonts, external scripts)
            if (message.includes('Load failed') ||
                filename.includes('fonts.googleapis') ||
                filename.includes('fonts.gstatic')) {
                console.warn('⚠️ Resource load failure suppressed:', filename);
                event.preventDefault();
                return true;
            }
        }, true);

        // Catch unhandled promise rejections
        window.addEventListener('unhandledrejection', function(event) {
            if (!event.reason) {
                // Empty rejection
                console.warn('⚠️ Empty promise rejection suppressed');
                event.preventDefault();
                return true;
            }

            const message = event.reason.message || String(event.reason);

            // Suppress resource loading promise rejections
            if (message.includes('Load failed') ||
                message.includes('Failed to fetch') ||
                message === '[object ProgressEvent]') {
                console.warn('⚠️ Resource load promise rejection suppressed:', message);
                event.preventDefault();
                return true;
            }
        });
    </script>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- App styles via Vite --}}
    @vite(['resources/css/app.css'])

    {{-- jQuery + SUMIT PaymentsJS (PCI Mode = no) --}}
    @if($settings['pci_mode'] === 'no')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://app.sumit.co.il/scripts/payments.js"></script>
    @endif
    
    <style>
        [x-cloak] { display: none !important; }

        body {
            font-family: 'Heebo', sans-serif;
        }

        /* ========== DYNAMIC CSS VARIABLES (v1.16.0) ========== */
        :root {
            --primary-color: {{ $primaryColor }};
            --secondary-color: {{ $secondaryColor }};
            --hover-color: {{ $hoverColor }};
        }
        /* ==================================================== */

        /* Custom Focus Ring - Now uses CSS variable */
        *:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* ========== CUSTOM UTILITY CLASSES (v1.16.0) ========== */
        /* Background colors */
        .bg-primary { background-color: var(--primary-color) !important; }
        .bg-secondary { background-color: var(--secondary-color) !important; }
        .hover\:bg-primary-hover:hover { background-color: var(--hover-color) !important; }

        /* Text colors */
        .text-primary { color: var(--primary-color) !important; }
        .text-secondary { color: var(--secondary-color) !important; }

        /* Border colors */
        .border-primary { border-color: var(--primary-color) !important; }
        .border-secondary { border-color: var(--secondary-color) !important; }

        /* Gradients */
        .from-secondary { --tw-gradient-from: var(--secondary-color) !important; }
        .to-secondary-light { --tw-gradient-to: color-mix(in srgb, var(--secondary-color) 50%, white) !important; }

        /* Shadows */
        .shadow-primary { box-shadow: 0 10px 15px -3px color-mix(in srgb, var(--primary-color) 25%, transparent) !important; }
        /* ==================================================== */

        /* RTL Input Adjustments */
        input[dir="ltr"], 
        input[type="email"], 
        input[type="tel"],
        input[type="number"] {
            text-align: left;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #F8F9FF; }
        ::-webkit-scrollbar-thumb { background: #E9E9E9; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #8890B1; }
    </style>
    
    @stack('styles')
</head>
<body class="bg-[#F8F9FF] min-h-screen">
    <div class="py-8 px-4" x-data="checkoutForm()" :dir="rtl ? 'rtl' : 'ltr'">
        <div class="max-w-6xl mx-auto">
            
            {{-- ========================================= --}}
            {{-- NEW: Progress Stepper (RTL) - Tailwind v4 Optimized --}}
            {{-- ========================================= --}}
            <nav aria-label="Checkout progress" class="bg-white rounded-2xl shadow-sm p-4 sm:p-6 mb-6">
                <ol class="flex items-center justify-center" role="list">
                    @if($rtl)
                        {{-- RTL Order: 4 → 3 → 2 → 1 (right to left) --}}
                        {{-- Step 4: הגשה --}}
                        <li class="flex flex-col items-center gap-2" aria-current="{{ $currentStep === 4 ? 'step' : 'false' }}">
                            <div class="size-11 sm:size-12 rounded-full border-2 {{ $currentStep >= 4 ? 'bg-primary border-primary text-white' : 'border-gray-300 text-gray-400' }} flex items-center justify-center transition-all duration-300">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </div>
                            <span class="text-xs sm:text-sm {{ $currentStep >= 4 ? 'font-medium text-primary' : 'text-gray-400' }} transition-all duration-300">{{ __('Submit') }}</span>
                        </li>

                        <div class="w-8 sm:w-12 md:w-20 h-0.5 {{ $currentStep >= 4 ? 'bg-primary' : 'bg-gray-200' }} mx-1 sm:mx-2 transition-colors duration-200" aria-hidden="true"></div>

                        {{-- Step 3: תנאים --}}
                        <li class="flex flex-col items-center gap-2" aria-current="{{ $currentStep === 3 ? 'step' : 'false' }}">
                            <div class="size-11 sm:size-12 rounded-full {{ $currentStep >= 3 ? 'bg-primary text-white' : 'border-2 border-gray-300 text-gray-400' }} flex items-center justify-center text-sm font-medium transition-all duration-300">3</div>
                            <span class="text-xs sm:text-sm {{ $currentStep >= 3 ? 'font-medium text-primary' : 'text-gray-400' }} transition-all duration-300">{{ __('Terms') }}</span>
                        </li>

                        <div class="w-8 sm:w-12 md:w-20 h-0.5 {{ $currentStep >= 3 ? 'bg-primary' : 'bg-gray-200' }} mx-1 sm:mx-2 transition-colors duration-200" aria-hidden="true"></div>

                        {{-- Step 2: תשלום --}}
                        <li class="flex flex-col items-center gap-2" aria-current="{{ $currentStep === 2 ? 'step' : 'false' }}">
                            <div class="size-11 sm:size-12 rounded-full {{ $currentStep >= 2 ? 'bg-primary text-white' : 'border-2 border-gray-300 text-gray-400' }} flex items-center justify-center text-sm font-medium transition-all duration-300">2</div>
                            <span class="text-xs sm:text-sm {{ $currentStep >= 2 ? 'font-medium text-primary' : 'text-gray-400' }} transition-all duration-300">{{ __('Payment') }}</span>
                        </li>

                        <div class="w-8 sm:w-12 md:w-20 h-0.5 {{ $currentStep >= 2 ? 'bg-primary' : 'bg-gray-200' }} mx-1 sm:mx-2 transition-colors duration-200" aria-hidden="true"></div>

                        {{-- Step 1: פרטי לקוח (Active) --}}
                        <li class="flex flex-col items-center gap-2" aria-current="{{ $currentStep === 1 ? 'step' : 'false' }}">
                            <div class="size-11 sm:size-12 rounded-full {{ $currentStep >= 1 ? 'bg-primary text-white' : 'border-2 border-gray-300 text-gray-400' }} flex items-center justify-center text-sm font-medium transition-all duration-300">1</div>
                            <span class="text-xs sm:text-sm {{ $currentStep >= 1 ? 'font-medium text-primary' : 'text-gray-400' }} transition-all duration-300">{{ __('Customer') }}</span>
                        </li>
                    @else
                        {{-- LTR Order: 1 → 2 → 3 → 4 (left to right) --}}
                        <li class="flex flex-col items-center gap-2" aria-current="{{ $currentStep === 1 ? 'step' : 'false' }}">
                            <div class="size-11 sm:size-12 rounded-full bg-primary text-white flex items-center justify-center text-sm font-medium transition-all duration-300">1</div>
                            <span class="text-xs sm:text-sm font-medium text-primary transition-all duration-300">{{ __('Customer') }}</span>
                        </li>

                        <div class="w-8 sm:w-12 md:w-20 h-0.5 bg-primary mx-1 sm:mx-2 transition-all duration-300" aria-hidden="true"></div>

                        <li class="flex flex-col items-center gap-2" aria-current="{{ $currentStep === 2 ? 'step' : 'false' }}">
                            <div class="size-11 sm:size-12 rounded-full border-2 border-gray-300 text-gray-400 flex items-center justify-center text-sm font-medium transition-all duration-300">2</div>
                            <span class="text-xs sm:text-sm text-gray-400 transition-all duration-300">{{ __('Payment') }}</span>
                        </li>

                        <div class="w-8 sm:w-12 md:w-20 h-0.5 bg-gray-200 mx-1 sm:mx-2 transition-all duration-300" aria-hidden="true"></div>

                        <li class="flex flex-col items-center gap-2" aria-current="{{ $currentStep === 3 ? 'step' : 'false' }}">
                            <div class="size-11 sm:size-12 rounded-full border-2 border-gray-300 text-gray-400 flex items-center justify-center text-sm font-medium transition-all duration-300">3</div>
                            <span class="text-xs sm:text-sm text-gray-400 transition-all duration-300">{{ __('Terms') }}</span>
                        </li>

                        <div class="w-8 sm:w-12 md:w-20 h-0.5 bg-gray-200 mx-1 sm:mx-2 transition-all duration-300" aria-hidden="true"></div>

                        <li class="flex flex-col items-center gap-2" aria-current="{{ $currentStep === 4 ? 'step' : 'false' }}">
                            <div class="size-11 sm:size-12 rounded-full border-2 border-gray-300 text-gray-400 flex items-center justify-center transition-all duration-300">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </div>
                            <span class="text-xs sm:text-sm text-gray-400 transition-all duration-300">{{ __('Submit') }}</span>
                        </li>
                    @endif
                </ol>
                <p class="text-center text-xs sm:text-sm text-gray-400 mt-3 sm:mt-4" role="status" aria-live="polite">{{ __('Step :current of :total', ['current' => $currentStep, 'total' => 4]) }}</p>
            </nav>

            {{-- ========================================= --}}
            {{-- NEW: Trust Badges - Tailwind v4 Optimized --}}
            {{-- ========================================= --}}
            <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-6" role="list" aria-label="Security features">
                <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-full shadow-sm hover:shadow-md transition-shadow duration-200" role="listitem">
                    <svg class="size-4 text-primary shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('SSL Encrypted') }}</span>
                </div>

                <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-full shadow-sm hover:shadow-md transition-shadow duration-200" role="listitem">
                    <svg class="size-4 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('PCI DSS Compliant') }}</span>
                </div>

                <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-full shadow-sm hover:shadow-md transition-shadow duration-200" role="listitem" aria-label="Accepted payment cards">
                    <span class="text-blue-700 font-bold text-xs">VISA</span>
                    <span class="text-orange-500 font-bold text-xs">MC</span>
                    <span class="text-blue-500 font-bold text-xs">AMEX</span>
                </div>

                @if($bitEnabled)
                <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-full shadow-sm hover:shadow-md transition-shadow duration-200" role="listitem" aria-label="Bit payment available">
                    <span class="text-blue-600 font-bold text-sm">Bit</span>
                </div>
                @endif
            </div>
            
            {{-- Header --}}
            <div class="text-center mb-8">
                {{-- Logo Section (DYNAMIC COLORS - v1.16.0) --}}
                <div class="mb-6">
                    <div class="inline-flex items-center justify-center gap-3 bg-white px-6 py-4 rounded-2xl shadow-sm">
                        {{-- Logo Icon with Dynamic Color --}}
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl shadow-lg"
                             style="background: linear-gradient(to bottom right, var(--primary-color), var(--hover-color)); box-shadow: 0 10px 15px -3px color-mix(in srgb, var(--primary-color) 25%, transparent);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        {{-- Brand Name (Fixed) --}}
                        <div class="{{ $rtl ? 'text-right' : 'text-left' }}">
                            <h2 class="text-lg font-bold text-[#111928] leading-tight">NM-DigitalHub</h2>
                            <p class="text-xs text-[#8890B1]">{{ $brandTagline }}</p>
                        </div>
                    </div>
                </div>

                {{-- Welcome Greeting (for authenticated users) --}}
                @if($user && $customerName)
                <div class="mb-4 inline-flex items-center gap-2 bg-gradient-to-r from-secondary to-secondary-light px-5 py-3 rounded-full shadow-sm">
                    <span class="text-2xl">👋</span>
                    <span class="text-base font-medium text-[#111928]">
                        {{ $rtl ? "ברוך הבא, {$customerName}!" : "Welcome, {$customerName}!" }}
                    </span>
                </div>
                @endif

                <div class="flex items-center justify-between mb-4">
                    {{-- Accessibility Button --}}
                    <button class="bg-white p-3 rounded-xl shadow-sm hover:shadow-md transition-shadow" title="{{ __('Accessibility') }}">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </button>

                    <h1 class="text-2xl md:text-3xl font-bold text-[#111928]">{{ __('Complete your purchase') }}</h1>

                    {{-- Language Selector - Matches Accessibility Button Style --}}
                    @include('officeguy::pages.partials.language-selector-inline')
                </div>
                <p class="text-[#8890B1] text-sm md:text-base">
                    {{ __('Secure payment with instant delivery. Your card details are encrypted and never stored.') }}
                </p>
            </div>

            {{-- ========================================= --}}
            {{-- Laravel Errors & Flash Messages --}}
            {{-- ========================================= --}}

            {{-- Validation Errors --}}
            @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 mb-6 shadow-sm" role="alert">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div class="{{ $rtl ? 'mr-3' : 'ml-3' }}">
                        <h3 class="text-sm font-semibold text-red-800 mb-2">
                            {{ __('There were errors with your submission:') }}
                        </h3>
                        <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            @endif

            {{-- Runtime Error (from back()->with('error')) --}}
            @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 mb-6 shadow-sm" role="alert">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div class="{{ $rtl ? 'mr-3' : 'ml-3' }}">
                        <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
            @endif

            {{-- Success Message (from back()->with('success')) --}}
            @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4 mb-6 shadow-sm" role="alert">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div class="{{ $rtl ? 'mr-3' : 'ml-3' }}">
                        <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
            @endif

            {{-- ========================================= --}}
            {{-- FIXED: RTL Layout - Payment on Right --}}
            {{-- ========================================= --}}
            <form id="og-checkout-form" method="POST" action="{{ $checkoutUrl }}">
                @csrf
                <input type="hidden" name="payable_id" value="{{ $payable->getPayableId() }}">
                <input type="hidden" name="payable_type" value="{{ get_class($payable) }}">

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                
                {{-- ========================================= --}}
                {{-- Payment Method Card (Right in RTL) --}}
                {{-- ========================================= --}}
                <div class="xl:col-span-1 {{ $rtl ? 'xl:order-2' : 'xl:order-3' }}">
                    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                        {{-- Card Header with Gradient --}}
                        <div class="bg-gradient-to-{{ $rtl ? 'l' : 'r' }} from-secondary to-secondary-light p-4 border-b border-gray-100">
                            <div class="flex items-center {{ $rtl ? 'justify-between' : 'justify-between flex-row-reverse' }}">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-primary font-medium">{{ __('Secure') }}</span>
                                    <svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="flex items-center gap-3">
                                    <h2 class="text-lg font-semibold text-[#111928]">{{ __('Payment Method') }}</h2>
                                    <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-[#8890B1] mt-1 {{ $rtl ? 'text-right' : 'text-left' }}">{{ __('Choose your preferred payment option') }}</p>
                        </div>
                        
                        {{-- Card Body --}}
                        <div class="p-6 space-y-5">
                            {{-- Payment Method Tabs --}}
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

<input type="hidden"
    name="payment_token"
    x-model="selectedToken"
/>

                            
                            {{-- Credit Card Fields --}}
                            <div x-show="paymentMethod === 'card'" x-cloak class="space-y-4">
                                {{-- Saved Payment Methods - Enhanced Card-Style Design --}}
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
                                            {{-- Radio Button --}}
                                            <input
                                                type="radio"
                                                name="payment_token_choice"
                                                value="{{ $token->id }}"
                                                x-model="selectedToken"
                                                class="text-primary focus:ring-[#3B82F6] focus:ring-offset-2 w-5 h-5"
                                            >

                                            {{-- Card Icon & Details --}}
                                            <div class="{{ $rtl ? 'mr-4' : 'ml-4' }} flex-1 flex items-center gap-4">
                                                {{-- Dynamic Card Icon by Type --}}
                                                <div class="flex-shrink-0 w-14 h-10 rounded-lg flex items-center justify-center shadow-md">
                                                    @php
                                                        $cardType = strtolower($token->card_type ?? '');
                                                    @endphp

                                                    @if($cardType === 'visa')
                                                        {{-- Visa Icon --}}
                                                        <svg class="w-14 h-10" viewBox="0 0 48 32" fill="none">
                                                            <rect width="48" height="32" rx="4" fill="#1A1F71"/>
                                                            <path d="M20.5 11l-3.5 10h2.2l3.5-10h-2.2zm6.5 6.5l1.2-3.3.7 3.3h-1.9zm2.5 3.5h2l-1.8-10h-1.8c-.4 0-.8.2-.9.6l-3.2 9.4h2.3l.5-1.3h2.9l.3 1.3zm-7.8-3.2c0-2.6-3.6-2.8-3.6-4 0-.4.4-.7 1.2-.8.4 0 1.5-.1 2.7.5l.5-2.2c-.7-.2-1.5-.5-2.6-.5-2.5 0-4.2 1.3-4.2 3.2 0 1.4 1.2 2.2 2.2 2.6 1 .5 1.3.8 1.3 1.2 0 .6-.8.9-1.5.9-1.3 0-2-.2-3.1-.7l-.5 2.3c.7.3 2 .6 3.4.6 2.6-.1 4.3-1.3 4.3-3.1zm-9.7-6.8l-4 10h-2.3l-2-7.5c-.1-.4-.2-.6-.6-.7-.6-.3-1.7-.6-2.6-.8l.1-.4h4.4c.6 0 1.1.4 1.2 1l1.1 5.8 2.7-6.8h2.3z" fill="white"/>
                                                        </svg>
                                                    @elseif($cardType === 'mastercard')
                                                        {{-- Mastercard Icon --}}
                                                        <svg class="w-14 h-10" viewBox="0 0 48 32" fill="none">
                                                            <rect width="48" height="32" rx="4" fill="#F4F4F4"/>
                                                            <circle cx="18" cy="16" r="9" fill="#EB001B"/>
                                                            <circle cx="30" cy="16" r="9" fill="#F79E1B"/>
                                                            <path d="M24 8.5c-2 1.5-3.3 3.9-3.3 6.5s1.3 5 3.3 6.5c2-1.5 3.3-3.9 3.3-6.5s-1.3-5-3.3-6.5z" fill="#FF5F00"/>
                                                        </svg>
                                                    @elseif($cardType === 'american express' || $cardType === 'amex')
                                                        {{-- Amex Icon --}}
                                                        <svg class="w-14 h-10" viewBox="0 0 48 32" fill="none">
                                                            <rect width="48" height="32" rx="4" fill="#006FCF"/>
                                                            <path d="M14.5 11l-2 10h2.3l.3-1.5h1.5l.3 1.5h2.6V11h-2v6.7l-1.3-6.7h-1.7zm7.5 4.2l1.5-4.2h2.3l-2.5 6v4h-2.3v-4l-2.5-6h2.3l1.5 4.2zm9 1.8h-2.5v1.3h2.5v1.7h-5V11h5v1.7h-2.5v1.3h2.5v2zm7 4h-2.3l-.3-1h-2l-.3 1h-2.3l2.5-10h2.3l2.4 10zm-3.2-3.5l-.6-3-.6 3h1.2z" fill="white"/>
                                                        </svg>
                                                    @else
                                                        {{-- Generic Card Icon --}}
                                                        <div class="w-14 h-10 bg-gradient-to-br from-[#3B82F6] to-[#2563EB] rounded-lg flex items-center justify-center shadow-md">
                                                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                                            </svg>
                                                        </div>
                                                    @endif
                                                </div>

                                                {{-- Card Details --}}
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

                                            {{-- Selected Checkmark --}}
                                            <div
                                                x-show="selectedToken === '{{ $token->id }}'"
                                                x-cloak
                                                class="absolute top-2 {{ $rtl ? 'left-2' : 'right-2' }} w-6 h-6 bg-primary rounded-full flex items-center justify-center shadow-lg"
                                            >
                                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                        </label>
                                        @endforeach

                                        {{-- New Card Option - Enhanced Design --}}
                                        <label
                                            class="relative flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all duration-200 group"
                                            :class="selectedToken === 'new'
                                                ? 'border-primary bg-gradient-to-br from-secondary to-secondary-light shadow-md'
                                                : 'border-dashed border-[#E9E9E9] bg-white hover:border-primary hover:border-solid hover:shadow-sm'"
                                        >
                                            {{-- Radio Button --}}
                                            <input
                                                type="radio"
                                                name="payment_token_choice"
                                                value="new"
                                                x-model="selectedToken"
                                                class="text-primary focus:ring-[#3B82F6] focus:ring-offset-2 w-5 h-5"
                                            >

                                            {{-- Icon & Text --}}
                                            <div class="{{ $rtl ? 'mr-4' : 'ml-4' }} flex-1 flex items-center gap-4">
                                                {{-- Plus Icon --}}
                                                <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg flex items-center justify-center group-hover:from-secondary group-hover:to-secondary-light transition-colors">
                                                    <svg class="w-6 h-6 text-gray-500 group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                </div>

                                                {{-- Text --}}
                                                <div class="{{ $rtl ? 'text-right' : 'text-left' }}">
                                                    <span class="font-semibold text-[#111928] text-base">{{ __('Use a new card') }}</span>
                                                    <p class="text-sm text-[#8890B1] mt-0.5">{{ __('Add and save a new payment method') }}</p>
                                                </div>
                                            </div>

                                            {{-- Selected Checkmark --}}
                                            <div
                                                x-show="selectedToken === 'new'"
                                                x-cloak
                                                class="absolute top-2 {{ $rtl ? 'left-2' : 'right-2' }} w-6 h-6 bg-primary rounded-full flex items-center justify-center shadow-lg"
                                            >
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
                                            {{-- Dynamic Card Type Icon --}}
                                            <div class="absolute {{ $rtl ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2">
                                                {{-- Visa Icon --}}
                                                <svg x-show="cardType === 'visa'" x-cloak class="w-8 h-6" viewBox="0 0 48 32" fill="none">
                                                    <rect width="48" height="32" rx="4" fill="#1A1F71"/>
                                                    <path d="M20.5 11l-3.5 10h2.2l3.5-10h-2.2zm6.5 6.5l1.2-3.3.7 3.3h-1.9zm2.5 3.5h2l-1.8-10h-1.8c-.4 0-.8.2-.9.6l-3.2 9.4h2.3l.5-1.3h2.9l.3 1.3zm-7.8-3.2c0-2.6-3.6-2.8-3.6-4 0-.4.4-.7 1.2-.8.4 0 1.5-.1 2.7.5l.5-2.2c-.7-.2-1.5-.5-2.6-.5-2.5 0-4.2 1.3-4.2 3.2 0 1.4 1.2 2.2 2.2 2.6 1 .5 1.3.8 1.3 1.2 0 .6-.8.9-1.5.9-1.3 0-2-.2-3.1-.7l-.5 2.3c.7.3 2 .6 3.4.6 2.6-.1 4.3-1.3 4.3-3.1zm-9.7-6.8l-4 10h-2.3l-2-7.5c-.1-.4-.2-.6-.6-.7-.6-.3-1.7-.6-2.6-.8l.1-.4h4.4c.6 0 1.1.4 1.2 1l1.1 5.8 2.7-6.8h2.3z" fill="white"/>
                                                </svg>

                                                {{-- Mastercard Icon --}}
                                                <svg x-show="cardType === 'mastercard'" x-cloak class="w-8 h-6" viewBox="0 0 48 32" fill="none">
                                                    <rect width="48" height="32" rx="4" fill="#EB001B"/>
                                                    <rect x="16" width="16" height="32" fill="#F79E1B" fill-opacity="0.8"/>
                                                    <circle cx="20" cy="16" r="10" fill="#EB001B"/>
                                                    <circle cx="28" cy="16" r="10" fill="#F79E1B"/>
                                                </svg>

                                                {{-- Amex Icon --}}
                                                <svg x-show="cardType === 'amex'" x-cloak class="w-8 h-6" viewBox="0 0 48 32" fill="none">
                                                    <rect width="48" height="32" rx="4" fill="#006FCF"/>
                                                    <path d="M14.5 11l-2 10h2.3l.3-1.5h1.5l.3 1.5h2.6V11h-2v6.7l-1.3-6.7h-1.7zm7.5 4.2l1.5-4.2h2.3l-2.5 6v4h-2.3v-4l-2.5-6h2.3l1.5 4.2zm9 1.8h-2.5v1.3h2.5v1.7h-5V11h5v1.7h-2.5v1.3h2.5v2zm7 4h-2.3l-.3-1h-2l-.3 1h-2.3l2.5-10h2.3l2.4 10zm-3.2-3.5l-.6-3-.6 3h1.2z" fill="white"/>
                                                </svg>

                                                {{-- Generic Card Icon (default) --}}
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
                                            <select
                                                id="og-expmonth"
                                                name="exp_month"
                                                data-og="expirationmonth"
                                                x-model="expMonth"
                                                class="w-full bg-[#F2F4F7] border border-[#E9E9E9] rounded-lg px-3 py-3 text-[#383E53] focus:ring-2 focus:ring-[#3B82F6] focus:border-transparent transition-all"
                                            >
                                                <option value="">MM</option>
                                                @for($i = 1; $i <= 12; $i++)
                                                    <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-[#383E53] mb-2 {{ $rtl ? 'text-right' : 'text-left' }}">{{ __('Year') }} <span class="text-[#FF7878]">*</span></label>
                                            <select
                                                id="og-expyear"
                                                name="exp_year"
                                                data-og="expirationyear"
                                                x-model="expYear"
                                                class="w-full bg-[#F2F4F7] border border-[#E9E9E9] rounded-lg px-3 py-3 text-[#383E53] focus:ring-2 focus:ring-[#3B82F6] focus:border-transparent transition-all"
                                            >
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
                                            @if(!empty($customerCitizenId))
                                                value="{{ $customerCitizenId }}"
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
                                        <input type="checkbox" id="save_card" name="save_card" value="1" x-model="saveCard" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-[#3B82F6]">
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
                        </div>
                    </div>
                    
                    {{-- Trust Section + CTA --}}
                    <div class="bg-white rounded-2xl shadow-sm p-6 mt-6">
                        {{-- Trust Icons --}}
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-[#F8F9FF] rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-[#60A5FA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div class="space-y-2 text-sm text-[#8890B1]">
                                <p class="flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    {{ __('Bank-level encryption') }}
                                </p>
                                <p class="flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    {{ __('Card details never stored') }}
                                </p>
                                <p class="flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    {{ __('Instant eSIM delivery') }}
                                </p>
                            </div>
                        </div>
                        
                        {{-- FIXED: Green CTA Button (v1.15.0: disabled when user exists) --}}
                        <button
                            type="submit"
                            :disabled="processing || userExists"
                            class="w-full bg-primary hover:bg-[#2563EB] text-white font-semibold py-4 px-6 rounded-xl transition-all duration-200 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-blue-500/25"
                        >
                            <template x-if="!processing">
                                <span class="flex items-center gap-2">
                                    {{ __('Pay') }} {{ $currencySymbol }}{{ number_format($amount, 2) }}
                                    <svg class="w-5 h-5 {{ $rtl ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </span>
                            </template>
                            <template x-if="processing">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('Processing...') }}
                                </span>
                            </template>
                        </button>
                        
                        {{-- Security Badge --}}
                        <div class="flex items-center justify-center gap-2 mt-4 text-sm text-[#8890B1]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <span>{{ __('Secured by SUMIT') }}</span>
                        </div>
                    </div>
                </div>

                {{-- ========================================= --}}
                {{-- Customer Details Card (Center) --}}
                {{-- ========================================= --}}
                <div class="xl:col-span-1 {{ $rtl ? 'xl:order-3' : 'xl:order-1' }}">

                        {{-- Error Messages --}}
                        <div x-show="errors.length > 0" x-cloak class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
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
                        
                        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                            {{-- Card Header --}}
                            <div class="bg-gradient-to-{{ $rtl ? 'l' : 'r' }} from-[#F8F9FF] to-white p-4 border-b border-gray-100">
                                <div class="flex items-center gap-3 {{ $rtl ? 'justify-end' : 'justify-start' }}">
                                    <h2 class="text-lg font-semibold text-[#111928]">{{ __('Customer & Billing') }}</h2>
                                    <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shadow-sm">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Form Fields --}}
                            <div class="p-6 space-y-4">
                                @include('officeguy::pages.partials.input', ['id' => 'customer_name', 'label' => __('Full Name'), 'required' => true, 'value' => $customerName, 'type' => 'text', 'model' => 'customerName'])

                                {{-- Email field with existence check (v1.15.0+) --}}
                                <div>
                                    {{-- Custom email input with @blur event --}}
                                    <div class="w-full">
                                        <label for="customer_email" class="block text-sm font-medium text-[#383E53] mb-2 {{ $rtl ? 'text-right' : 'text-left' }}">
                                            {{ __('Email') }} <span class="text-[#FF7878]">*</span>
                                        </label>
                                        <input
                                            type="email"
                                            id="customer_email"
                                            name="customer_email"
                                            x-model="customerEmail"
                                            @blur="checkEmailExists()"
                                            value="{{ old('customer_email', $customerEmail ?? '') }}"
                                            dir="ltr"
                                            class="w-full bg-[#F2F4F7] border border-[#E9E9E9] rounded-lg px-4 py-3 text-[#383E53] text-left placeholder-[#8890B1] focus:ring-2 focus:ring-[#3B82F6] focus:border-transparent transition-all"
                                            required
                                        >
                                    </div>

                                    {{-- Loading indicator --}}
                                    <div x-show="emailCheckLoading" x-cloak class="mt-2 text-sm text-[#8890B1] {{ $rtl ? 'text-right' : 'text-left' }} flex items-center gap-2">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>{{ __('Checking email...') }}</span>
                                    </div>

                                    {{-- Error message (fail-safe) --}}
                                    <div x-show="emailCheckError" x-cloak class="mt-2 bg-yellow-50 border-{{ $rtl ? 'r' : 'l' }}-4 border-yellow-400 p-3 rounded-lg">
                                        <p class="text-sm text-yellow-700 {{ $rtl ? 'text-right' : 'text-left' }}" x-text="emailCheckError"></p>
                                    </div>

                                    {{-- User exists warning --}}
                                    <div x-show="userExists" x-cloak class="mt-3 bg-gradient-to-{{ $rtl ? 'r' : 'l' }} from-blue-50 to-blue-100 border-{{ $rtl ? 'r' : 'l' }}-4 border-blue-500 p-4 rounded-lg shadow-sm">
                                        <div class="flex items-start {{ $rtl ? 'flex-row-reverse' : '' }}">
                                            <svg class="w-6 h-6 text-blue-600 mt-0.5 {{ $rtl ? 'mr-3' : 'ml-3' }} flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                            <div class="flex-1">
                                                <p class="text-sm font-semibold text-blue-900 {{ $rtl ? 'text-right' : 'text-left' }} mb-1">
                                                    {{ __('User with this email already exists in the system') }}
                                                </p>
                                                <p class="text-sm text-blue-700 {{ $rtl ? 'text-right' : 'text-left' }} mb-3">
                                                    {{ __('To continue with the payment process, you must first log into the system') }}
                                                </p>
                                                <a
                                                    :href="loginUrl"
                                                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all shadow-md hover:shadow-lg {{ $rtl ? 'flex-row-reverse' : '' }}"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                                    </svg>
                                                    <span>{{ __('Login Now') }}</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @include('officeguy::pages.partials.input', ['id' => 'customer_phone', 'label' => __('Phone'), 'required' => true, 'value' => $customerPhone, 'type' => 'tel', 'model' => 'customerPhone'])
                                @include('officeguy::pages.partials.input', ['id' => 'customer_company', 'label' => __('Company'), 'required' => false, 'value' => $customerCompany ?? '', 'type' => 'text'])
                                @include('officeguy::pages.partials.input', ['id' => 'customer_address', 'label' => __('Street'), 'required' => true, 'value' => $customerAddress ?? '', 'type' => 'text'])
                                @include('officeguy::pages.partials.input', ['id' => 'customer_city', 'label' => __('City'), 'required' => true, 'value' => $customerCity ?? '', 'type' => 'text'])
                                @include('officeguy::pages.partials.input', ['id' => 'customer_postal', 'label' => __('Postal Code'), 'required' => false, 'value' => $customerPostal ?? '', 'type' => 'text'])
                                @include('officeguy::pages.partials.input', ['id' => 'customer_country', 'label' => __('Country'), 'required' => true, 'value' => $customerCountry ?? 'IL', 'type' => 'text'])
                            </div>
                        </div>
                </div>
                
                {{-- ========================================= --}}
                {{-- Order Summary Sidebar --}}
                {{-- ========================================= --}}
                <div class="xl:col-span-1 {{ $rtl ? 'xl:order-1' : 'xl:order-2' }}">
                    <div class="bg-white rounded-2xl shadow-sm p-6 sticky top-8">
                        <h2 class="text-lg font-semibold text-[#111928] mb-4">{{ __('Order Summary') }}</h2>

                        {{-- Product Details --}}
                        @php
                            $payableClass = get_class($payable);
                            $isPackage = str_contains($payableClass, 'Package');
                            $isEsim = str_contains($payableClass, 'Esim') || str_contains($payableClass, 'MayaNet');
                        @endphp

                        @if(method_exists($payable, 'getPayableDescription') && $payable->getPayableDescription())
                        <div class="mb-4 pb-4 border-b border-gray-100 bg-blue-50 p-3 rounded-lg">
                            <h3 class="text-sm font-semibold text-[#111928] mb-2">{{ __('Product Details') }}</h3>
                            <p class="text-sm text-gray-700 font-medium mb-2">{{ $payable->getPayableDescription() }}</p>

                            {{-- Package-specific details --}}
                            @if($isPackage)
                                {{-- Service Type --}}
                                @if(isset($payable->service_type) && $payable->service_type)
                                    <div class="flex items-center gap-2 text-xs text-[#8890B1] mt-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                        </svg>
                                        <span>{{ __('Type') }}: {{ ucfirst($payable->service_type->value ?? $payable->service_type) }}</span>
                                    </div>
                                @endif

                                {{-- Billing Cycle --}}
                                @if(isset($payable->billing_cycle) && $payable->billing_cycle)
                                    <div class="flex items-center gap-2 text-xs text-[#8890B1] mt-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>{{ __('Billing') }}: {{ ucfirst($payable->billing_cycle->value ?? $payable->billing_cycle) }}</span>
                                    </div>
                                @endif

                                {{-- Storage (if available) --}}
                                @if(isset($payable->storage_gb) && $payable->storage_gb > 0)
                                    <div class="flex items-center gap-2 text-xs text-[#8890B1] mt-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                        </svg>
                                        <span>{{ __('Storage') }}: {{ $payable->storage_gb }} GB</span>
                                    </div>
                                @endif

                                {{-- Bandwidth (if available) --}}
                                @if(isset($payable->bandwidth_gb) && $payable->bandwidth_gb > 0)
                                    <div class="flex items-center gap-2 text-xs text-[#8890B1] mt-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                        <span>{{ __('Bandwidth') }}: {{ $payable->bandwidth_gb }} GB</span>
                                    </div>
                                @endif

                                {{-- Max Domains (if available) --}}
                                @if(isset($payable->max_domains) && $payable->max_domains > 0)
                                    <div class="flex items-center gap-2 text-xs text-[#8890B1] mt-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                        </svg>
                                        <span>{{ __('Domains') }}: {{ $payable->max_domains == 999 ? __('Unlimited') : $payable->max_domains }}</span>
                                    </div>
                                @endif
                            @endif

                            {{-- eSIM-specific details --}}
                            @if($isEsim)
                                {{-- Data Quota --}}
                                @if(isset($payable->data_quota_mb) && $payable->data_quota_mb > 0)
                                    <div class="flex items-center gap-2 text-sm text-gray-700 mt-2 font-medium">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                        <span>{{ __('Data') }}: <strong class="text-blue-700">{{ number_format($payable->data_quota_mb / 1024, 2) }} GB</strong></span>
                                    </div>
                                @endif

                                {{-- Validity --}}
                                @if(isset($payable->validity_days) && $payable->validity_days > 0)
                                    <div class="flex items-center gap-2 text-sm text-gray-700 mt-2 font-medium">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>{{ __('Validity') }}: <strong class="text-green-700">{{ $payable->validity_days }} {{ __('days') }}</strong></span>
                                    </div>
                                @endif

                                {{-- Countries --}}
                                @if(isset($payable->countries_enabled) && !empty($payable->countries_enabled))
                                    @php
                                        $countries = is_array($payable->countries_enabled) ? $payable->countries_enabled : json_decode($payable->countries_enabled, true);
                                        $countryCount = is_array($countries) ? count($countries) : 0;
                                    @endphp
                                    @if($countryCount > 0)
                                        <div class="flex items-center gap-2 text-sm text-gray-700 mt-2 font-medium">
                                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span>{{ __('Coverage') }}: <strong class="text-purple-700">{{ $countryCount }} {{ $countryCount == 1 ? __('country') : __('countries') }}</strong></span>
                                        </div>
                                    @endif
                                @endif

                                {{-- Provider --}}
                                @if(isset($payable->provider) && $payable->provider)
                                    <div class="flex items-center gap-2 text-sm text-gray-700 mt-2 font-medium">
                                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        <span>{{ __('Network') }}: <strong class="text-orange-700">{{ ucfirst(str_replace('_', ' ', $payable->provider)) }}</strong></span>
                                    </div>
                                @endif
                            @endif
                        </div>
                        @endif

                        {{-- Items --}}
                        <div class="space-y-3 mb-4">
                            @foreach($items as $item)
                            <div class="flex justify-between text-sm">
                                <span class="text-[#8890B1]">
                                    {{ $item['name'] }}
                                    @if(($item['quantity'] ?? 1) > 1)
                                        <span class="text-gray-400">× {{ $item['quantity'] }}</span>
                                    @endif
                                </span>
                                <span class="font-medium text-[#383E53]">{{ $currencySymbol }}{{ number_format(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1), 2) }}</span>
                            </div>
                            @endforeach
                        </div>
                        
                        @if($shipping > 0)
                        <div class="flex justify-between text-sm py-2 border-t border-gray-100">
                            <span class="text-[#8890B1]">{{ __('Shipping') }}</span>
                            <span class="font-medium text-[#383E53]">{{ $currencySymbol }}{{ number_format($shipping, 2) }}</span>
                        </div>
                        @endif
                        
                        @foreach($fees as $fee)
                        <div class="flex justify-between text-sm py-2 border-t border-gray-100">
                            <span class="text-[#8890B1]">{{ $fee['name'] }}</span>
                            <span class="font-medium text-[#383E53]">{{ $currencySymbol }}{{ number_format($fee['amount'], 2) }}</span>
                        </div>
                        @endforeach
                        
                        @if($payable->isTaxEnabled() && $payable->getVatRate())
                        <div class="flex justify-between text-sm py-2 border-t border-gray-100">
                            <span class="text-[#8890B1]">{{ __('VAT') }} ({{ $payable->getVatRate() }}%)</span>
                            <span class="font-medium text-[#383E53]">{{ __('Included') }}</span>
                        </div>
                        @endif
                        
                        {{-- Total --}}
                        <div class="flex justify-between pt-4 border-t-2 border-gray-200 mt-4">
                            <span class="text-lg font-semibold text-[#111928]">{{ __('Total') }}</span>
                            <span class="text-lg font-bold text-primary">{{ $currencySymbol }}{{ number_format($amount, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            </form>
        </div>
    </div>

    <script>
        function checkoutForm() {
            return {
                rtl: @json($rtl),
                paymentMethod: 'card',
                selectedToken: @json(
    $savedTokens->isNotEmpty()
        ? (string) $savedTokens->first()->id
        : 'new'
),
                cardNumber: '',
                cardType: null, // 'visa', 'mastercard', 'amex', or null
                expMonth: '',
                expYear: '',
                cvv: '',
                citizenId: @json(old('citizen_id', $customerCitizenId ?? '')),
                singleUseToken: '',
                paymentsCount: '1',
                saveCard: false,
                acceptTerms: false,
                customerName: @json(old('customer_name', $customerName ?? '')),
                customerEmail: @json(old('customer_email', $customerEmail ?? '')),
                customerPhone: @json(old('customer_phone', $customerPhone ?? '')),
                processing: false,
                errors: [],
                // Email existence check (v1.15.0+)
                userExists: false,
                emailCheckLoading: false,
                emailCheckError: null,
                loginUrl: null,
                isAuthenticated: @json(auth()->check()),
                
                init() {
                    console.log('🚀 Alpine.js checkoutForm init() called');
                    console.log('⚙️ Settings DEBUG:', @json($settings));
                    console.log('🔍 pci_mode value:', @json($settings['pci_mode'] ?? 'UNDEFINED'));
                    console.log('🔍 company_id value:', @json($settings['company_id'] ?? 'UNDEFINED'));
                    console.log('🔍 public_key value:', @json($settings['public_key'] ?? 'UNDEFINED'));

                   

                    // Watch cardNumber for automatic card type detection
                    this.$watch('cardNumber', (value) => {
                        this.cardType = this.detectCardType(value);
                    });
                },

                detectCardType(number) {
                    // Remove spaces and non-digits
                    const cleaned = number.replace(/\D/g, '');

                    if (cleaned.length < 2) return null;

                    // Visa: starts with 4
                    if (/^4/.test(cleaned)) return 'visa';

                    // Mastercard: starts with 51-55 or 2221-2720
                    if (/^5[1-5]/.test(cleaned) || /^222[1-9]|^22[3-9]\d|^2[3-6]\d{2}|^27[01]\d|^2720/.test(cleaned)) {
                        return 'mastercard';
                    }

                    // American Express: starts with 34 or 37
                    if (/^3[47]/.test(cleaned)) return 'amex';

                    return null;
                },

                validate() {
                    this.errors = [];
                    if (!this.customerName.trim()) this.errors.push('{{ __("Full name is required") }}');
                    if (!this.customerEmail.trim()) this.errors.push('{{ __("Email is required") }}');
                    else if (!this.isValidEmail(this.customerEmail)) this.errors.push('{{ __("Please enter a valid email") }}');
                    if (!this.customerPhone.trim()) this.errors.push('{{ __("Phone number is required") }}');

                    // Block checkout if user exists and must login (v1.15.0+)
                    if (this.userExists) {
                        this.errors.push('{{ __("You must login to continue. Please use the login button below.") }}');
                    }

                    if (this.paymentMethod === 'card' && this.selectedToken === 'new') {
                        if (!this.cardNumber.trim()) this.errors.push('{{ __("Card number is required") }}');
                        if (!this.expMonth || !this.expYear) this.errors.push('{{ __("Expiration date is required") }}');
                    }

                    // Check terms acceptance
                    if (!this.acceptTerms) {
                        this.errors.push('{{ __("You must accept the terms and conditions") }}');
                    }

                    return this.errors.length === 0;
                },

                isValidEmail(email) {
                    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                },

                /**
                 * Check if user exists by email (v1.15.0+)
                 * Called on email field blur event
                 */
                async checkEmailExists() {
                    // Reset states
                    this.userExists = false;
                    this.emailCheckLoading = false;
                    this.emailCheckError = null;
                    this.loginUrl = null;

                    // Don't check if email is empty or invalid
                    if (!this.customerEmail || !this.isValidEmail(this.customerEmail)) {
                        return;
                    }

                    // Don't check if user is already authenticated
                    if (this.isAuthenticated) {
                        return;
                    }

                    this.emailCheckLoading = true;

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                        if (!csrfToken) {
                            console.error('CSRF token not found');
                            throw new Error('CSRF token missing');
                        }

                        const response = await Promise.race([
                            fetch('{{ route("officeguy.api.check-email") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({ email: this.customerEmail })
                            }),
                            new Promise((_, reject) =>
                                setTimeout(() => reject(new Error('Timeout')), 15000)
                            )
                        ]);

                        if (!response.ok) {
                            const errorData = await response.json().catch(() => ({}));
                            console.error('API Error:', response.status, errorData);
                            throw new Error(`HTTP ${response.status}: ${errorData.message || 'Network error'}`);
                        }

                        const data = await response.json();

                        if (data.exists) {
                            this.userExists = true;
                            this.loginUrl = data.login_url || '{{ route("filament.client.auth.login") }}';
                        }
                    } catch (error) {
                        console.error('Email check error:', error);
                        // Fail-safe: continue checkout on error
                        this.emailCheckError = '{{ __("Could not verify email. You may continue checkout.") }}';
                    } finally {
                        this.emailCheckLoading = false;
                    }
                },

            }
        }
    </script>

    {{-- Alpine.js - Load BEFORE DOMContentLoaded for proper reactivity --}}
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.OfficeGuy || !window.OfficeGuy.Payments) return;

    window.OfficeGuy.Payments.InitEditors();

    window.OfficeGuy.Payments.BindFormSubmit({
        FormSelector: '#og-checkout-form',
        CompanyID: '{{ $settings['company_id'] }}',
        APIPublicKey: '{{ $settings['public_key'] }}',
        ResponseLanguage: '{{ app()->getLocale() }}',

        IgnoreBind: function () {
            const checked = document.querySelector(
                'input[name="payment_token_choice"]:checked'
            );

            if (!checked) {
                // אין בחירה → SUMIT כן יתערב
                return false;
            }

            // אם הערך אינו "new" → טוקן שמור → SUMIT לא מתערב
            return checked.value !== 'new';
        }
    });

    // ✅ FIX: Add custom validation before form submission
    const form = document.getElementById('og-checkout-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Get Alpine.js component instance
            const alpineComponent = Alpine.$data(form.closest('[x-data]'));

            if (alpineComponent && typeof alpineComponent.validate === 'function') {
                // Run validation
                if (!alpineComponent.validate()) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    // Scroll to errors
                    const errorElement = document.querySelector('[x-show="errors.length > 0"]');
                    if (errorElement) {
                        errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    return false;
                }
            }
        }, true); // Use capture phase to run before SUMIT's handler
    }
});
</script>

    {{-- Alpine.js initialization fix - ensures components are initialized even if Alpine loads late --}}
    <script>
        (function() {
            console.log('🔧 Alpine.js initialization fix loaded');

            // Wait for Alpine to be available
            function waitForAlpine(callback, maxAttempts = 50) {
                let attempts = 0;
                const check = setInterval(() => {
                    attempts++;
                    if (typeof Alpine !== 'undefined') {
                        clearInterval(check);
                        console.log('✅ Alpine.js detected after', attempts, 'attempts');
                        callback();
                    } else if (attempts >= maxAttempts) {
                        clearInterval(check);
                        console.error('❌ Alpine.js not found after', maxAttempts, 'attempts');
                        activateVanillaFallback();
                    }
                }, 100);
            }

            // Force Alpine to reinitialize all components
            function reinitializeAlpine() {
                console.log('🔄 Forcing Alpine.js to reinitialize components...');
                try {
                    const xDataElements = document.querySelectorAll('[x-data]');
                    console.log(`Found ${xDataElements.length} elements with x-data`);

                    xDataElements.forEach((el, index) => {
                        if (!el.__x && Alpine && Alpine.initTree) {
                            console.log(`Initializing element ${index + 1}:`, el.tagName);
                            Alpine.initTree(el);
                        }
                    });
                    console.log('✅ Alpine.js reinitialization complete');
                } catch (error) {
                    console.error('❌ Error reinitializing Alpine:', error);
                }
            }

            // Vanilla JavaScript fallback
            function activateVanillaFallback() {
                console.log('🔄 Activating vanilla JavaScript fallback...');
                const buttons = document.querySelectorAll('[data-locale-switch]');

                buttons.forEach((button) => {
                    const locale = button.getAttribute('data-locale-switch');
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('🌍 Fallback: Switching to locale:', locale);

                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route('locale.change') }}';

                        const csrfToken = document.querySelector('meta[name="csrf-token"]');
                        if (csrfToken) {
                            const csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = '_token';
                            csrfInput.value = csrfToken.content;
                            form.appendChild(csrfInput);
                        }

                        const localeInput = document.createElement('input');
                        localeInput.type = 'hidden';
                        localeInput.name = 'locale';
                        localeInput.value = locale;
                        form.appendChild(localeInput);

                        document.body.appendChild(form);
                        form.submit();
                    });
                });
                console.log('✅ Vanilla fallback activated');
            }

            // Start initialization
            waitForAlpine(reinitializeAlpine);

            // Try immediate init if Alpine already loaded
            if (typeof Alpine !== 'undefined') {
                setTimeout(reinitializeAlpine, 100);
            }
        })();
    </script>

    @stack('scripts')
</body>
</html>
