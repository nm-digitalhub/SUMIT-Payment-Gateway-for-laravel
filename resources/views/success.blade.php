@php
    /**
     * Payment Success Page
     *
     * Displayed after successful payment confirmation.
     * Shows order details and next steps.
     *
     * CRITICAL: This page is UI ONLY - no provisioning logic!
     * Provisioning happens via Webhook → PaymentConfirmed event.
     */
    $rtl = app()->getLocale() === 'he' || app()->getLocale() === 'ar';
    $amount = $payable->getPayableAmount();
    $currency = $payable->getPayableCurrency() ?? config('officeguy.currency', 'ILS');
    $currencySymbol = $currency === 'ILS' ? '₪' : '$';
    $customerEmail = $payable->getCustomerEmail();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Payment Successful') }} - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        @keyframes checkmark {
            0% { stroke-dashoffset: 100; }
            100% { stroke-dashoffset: 0; }
        }
        .checkmark-circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            animation: checkmark 0.6s ease-in-out 0.2s forwards;
        }
        .checkmark-check {
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: checkmark 0.3s ease-in-out 0.6s forwards;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="py-12 px-4">
        <div class="max-w-2xl mx-auto">

            {{-- Success Card --}}
            <div class="bg-white rounded-2xl shadow-lg p-8 text-center">

                {{-- Animated Success Icon --}}
                <div class="mx-auto w-24 h-24 mb-6">
                    <svg class="w-full h-full" viewBox="0 0 52 52">
                        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none" stroke="#10B981" stroke-width="2"/>
                        <path class="checkmark-check" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" d="M14 27l7 7 17-17"/>
                    </svg>
                </div>

                {{-- Success Title --}}
                <h1 class="text-3xl font-bold text-gray-900 mb-3">
                    {{ __('תשלום בוצע בהצלחה!') }}
                </h1>

                <p class="text-gray-600 mb-8">
                    {{ __('ההזמנה שלך התקבלה ונמצאת בטיפול') }}
                </p>

                {{-- Order Details --}}
                <div class="bg-gray-50 rounded-xl p-6 mb-6 text-{{ $rtl ? 'right' : 'left' }}">
                    <h3 class="font-semibold text-lg mb-4 text-gray-900">{{ __('פרטי ההזמנה') }}</h3>

                    <div class="space-y-3">
                        {{-- Order ID --}}
                        @if(method_exists($payable, 'getKey'))
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">{{ __('מספר הזמנה') }}:</span>
                                <span class="font-medium text-gray-900">#{{ $payable->getKey() }}</span>
                            </div>
                        @endif

                        {{-- Amount --}}
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">{{ __('סכום') }}:</span>
                            <span class="font-bold text-xl text-gray-900">{{ number_format($amount, 2) }} {{ $currencySymbol }}</span>
                        </div>

                        {{-- Payment Method --}}
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">{{ __('אמצעי תשלום') }}:</span>
                            <span class="font-medium text-gray-900">{{ __('כרטיס אשראי') }}</span>
                        </div>

                        {{-- Status --}}
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">{{ __('סטטוס') }}:</span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                <span class="w-2 h-2 bg-green-600 rounded-full"></span>
                                {{ __('שולם') }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Next Steps --}}
                <div class="bg-blue-50 rounded-xl p-6 mb-6 text-{{ $rtl ? 'right' : 'left' }}">
                    <h3 class="font-semibold text-lg mb-3 text-blue-900 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('מה קורה עכשיו?') }}
                    </h3>

                    <ul class="space-y-2 text-blue-800">
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span>{{ __('קיבלנו את התשלום שלך בהצלחה') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                            </svg>
                            <span>{{ __('אישור נשלח למייל') }}: <strong>{{ $customerEmail }}</strong></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                            </svg>
                            <span>{{ __('ההזמנה תטופל בהקדם האפשרי') }}</span>
                        </li>
                    </ul>
                </div>

                {{-- Actions --}}
                <div class="space-y-3">
                    @auth
                        <a href="{{ route('filament.client.pages.profile-page-upgraded') }}"
                           class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition-colors">
                            {{ __('צפה בהזמנות שלי') }}
                        </a>
                    @endauth

                    <a href="{{ url('/') }}"
                       class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-3 px-6 rounded-lg transition-colors">
                        {{ __('חזור לדף הבית') }}
                    </a>
                </div>

            </div>

            {{-- Footer Note --}}
            <div class="mt-8 text-center text-sm text-gray-500">
                <p>
                    {{ __('יש שאלה?') }}
                    <a href="mailto:{{ config('mail.from.address') }}" class="text-blue-600 hover:underline">
                        {{ __('צור קשר עם התמיכה') }}
                    </a>
                </p>
                <p class="mt-2">{{ config('app.name') }} © {{ date('Y') }}</p>
            </div>

        </div>
    </div>
</body>
</html>
