@php
    /**
     * Access Denied Page
     *
     * Displayed when success page validation fails.
     * Shows user-friendly error message based on failed validation layers.
     */
    $rtl = app()->getLocale() === 'he' || app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Access Denied') }} - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full">

        {{-- Error Card --}}
        <div class="bg-white rounded-2xl shadow-lg p-8 text-center">

            {{-- Error Icon --}}
            <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-6">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>

            {{-- Error Title --}}
            <h1 class="text-2xl font-bold text-gray-900 mb-3">
                {{ __('Access Denied') }}
            </h1>

            {{-- Error Message --}}
            <p class="text-gray-600 mb-6">
                {{ $error_message ?? __('אין אפשרות לגשת לדף זה.') }}
            </p>

            @if(config('app.debug') && isset($failures) && !empty($failures))
                {{-- Debug Info (only in dev) --}}
                <div class="bg-gray-50 rounded-lg p-4 mb-6 text-{{ $rtl ? 'right' : 'left' }}">
                    <p class="text-sm font-semibold text-gray-700 mb-2">{{ __('Validation Failures:') }}</p>
                    <ul class="text-sm text-gray-600 space-y-1">
                        @foreach($failures as $failure)
                            <li class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span>
                                {{ $failure }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Actions --}}
            <div class="space-y-3">
                <a href="{{ url('/') }}"
                   class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition-colors">
                    {{ __('חזור לדף הבית') }}
                </a>

                <a href="mailto:{{ config('mail.from.address') }}"
                   class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-3 px-6 rounded-lg transition-colors">
                    {{ __('צור קשר עם התמיכה') }}
                </a>
            </div>

        </div>

        {{-- Footer --}}
        <p class="text-center text-sm text-gray-500 mt-6">
            {{ config('app.name') }} © {{ date('Y') }}
        </p>

    </div>
</body>
</html>
