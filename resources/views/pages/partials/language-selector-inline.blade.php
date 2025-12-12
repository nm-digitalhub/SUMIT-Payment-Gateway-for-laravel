@php
    /**
     * Inline Language Selector for Checkout
     *
     * Designed to match checkout page aesthetic EXACTLY:
     * - Same button style as Accessibility button
     * - Consistent with Trust Badges design
     * - Uses checkout color palette (#3B82F6, #60A5FA, #F8F9FF) - Blue NM-DigitalHub Branding
     * - Alpine.js integrated with checkout state
     * - RTL-aware positioning
     *
     * Props: None (reads from config)
     */

    $availableLocales = config('app.available_locales', []);
    $currentLocale = app()->getLocale();
    $currentLocaleData = $availableLocales[$currentLocale] ?? null;
    $isRtl = in_array($currentLocale, ['he', 'ar']);
@endphp

<div
    x-data="{
        languageOpen: false,
        currentLocale: '{{ $currentLocale }}',
        locales: @js($availableLocales),
        switching: false,

        switchLanguage(locale) {
            console.log('🌍 switchLanguage called with locale:', locale);

            if (locale === this.currentLocale) {
                console.log('⚠️ Same locale, closing dropdown');
                this.languageOpen = false;
                return;
            }

            // Show loading state
            this.switching = true;
            console.log('🔄 Setting switching state to true');

            // Create form for POST request with CSRF
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('locale.change') }}';
            console.log('📝 Form action:', form.action);

            // CSRF token
            const csrfToken = '{{ csrf_token() }}';
            console.log('🔐 CSRF Token:', csrfToken.substring(0, 10) + '...');

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            // Locale
            const localeInput = document.createElement('input');
            localeInput.type = 'hidden';
            localeInput.name = 'locale';
            localeInput.value = locale;
            form.appendChild(localeInput);
            console.log('✅ Form inputs created');

            // Add to DOM and submit
            document.body.appendChild(form);
            console.log('✅ Form added to DOM');

            // Small delay to show loading state
            console.log('⏳ Submitting form in 100ms...');
            setTimeout(() => {
                console.log('🚀 Submitting form NOW!');
                form.submit();
            }, 100);
        }
    }"
    @click.away="languageOpen = false"
    @keydown.escape.window="languageOpen = false"
    class="relative"
>
    {{-- Language Selector Button - Matches Accessibility Button Style --}}
    <button
        type="button"
        @click="languageOpen = !languageOpen"
        class="bg-white p-3 rounded-xl shadow-sm hover:shadow-md transition-all duration-200
               flex items-center gap-2 min-w-[48px] justify-center relative"
        :class="{
            'ring-2 ring-[#3B82F6] ring-offset-2': languageOpen,
            'opacity-50 pointer-events-none': switching
        }"
        title="{{ __('Select language') }}"
        :aria-expanded="languageOpen"
        aria-haspopup="true"
        aria-label="{{ __('Language selector') }}"
        :disabled="switching"
    >
        {{-- Loading Spinner --}}
        <div x-show="switching" x-cloak class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-90 rounded-xl">
            <svg class="animate-spin h-5 w-5 text-[#3B82F6]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        {{-- Content (hidden when switching) --}}
        <div x-show="!switching" class="flex items-center gap-2">
            @if($currentLocaleData)
                {{-- Flag Emoji --}}
                <span class="text-xl leading-none" aria-hidden="true">{{ $currentLocaleData['flag'] }}</span>

                {{-- Language Code (Hidden on mobile, shown on tablet+) --}}
                <span class="hidden sm:inline-block text-sm font-medium text-[#111928] uppercase">
                    {{ $currentLocale }}
                </span>
            @endif

            {{-- Chevron --}}
            <svg
                class="w-4 h-4 text-gray-600 transition-transform duration-200"
                :class="{ 'rotate-180': languageOpen }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </button>

    {{-- Dropdown Menu --}}
    <div
        x-show="languageOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-[-10px]"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute {{ $isRtl ? 'left-0' : 'right-0' }} mt-2 w-52
               bg-white rounded-xl shadow-lg border border-[#E9E9E9]
               overflow-hidden z-50"
        role="menu"
        aria-orientation="vertical"
    >
        {{-- Menu Header --}}
        <div class="px-4 py-3 border-b border-[#E9E9E9] bg-gradient-to-{{ $isRtl ? 'l' : 'r' }} from-[#F8F9FF] to-white">
            <p class="text-xs font-semibold text-[#8890B1] uppercase tracking-wide {{ $isRtl ? 'text-right' : 'text-left' }}">
                {{ __('Select Language') }}
            </p>
        </div>

        {{-- Language Options --}}
        <div class="py-1">
            @foreach($availableLocales as $localeCode => $localeData)
                <button
                    type="button"
                    @click="switchLanguage('{{ $localeCode }}')"
                    data-locale-switch="{{ $localeCode }}"
                    class="w-full flex items-center gap-3 px-4 py-3
                           {{ $localeCode === $currentLocale
                              ? 'bg-gradient-to-r from-[#DBEAFE] to-[#EFF6FF] text-[#3B82F6] font-semibold'
                              : 'text-[#111928] hover:bg-[#F8F9FF]' }}
                           transition-all duration-150 group
                           {{ $isRtl && in_array($localeCode, ['he', 'ar']) ? 'text-right flex-row-reverse' : 'text-left' }}"
                    role="menuitem"
                    :aria-current="currentLocale === '{{ $localeCode }}' ? 'true' : 'false'"
                >
                    {{-- Flag --}}
                    <span class="text-2xl flex-shrink-0 transition-transform group-hover:scale-110" aria-hidden="true">
                        {{ $localeData['flag'] }}
                    </span>

                    {{-- Language Details --}}
                    <div class="flex-1 {{ in_array($localeCode, ['he', 'ar']) ? 'text-right' : 'text-left' }}">
                        <p class="text-sm font-medium {{ in_array($localeCode, ['he', 'ar']) ? 'font-[\'Heebo\',sans-serif]' : '' }}">
                            {{ $localeData['name'] }}
                        </p>
                        <p class="text-xs text-[#8890B1] {{ $localeCode === $currentLocale ? 'text-[#3B82F6]' : '' }}">
                            {{ strtoupper($localeCode) }}
                        </p>
                    </div>

                    {{-- Check Icon for Current Language --}}
                    @if($localeCode === $currentLocale)
                        <svg class="w-5 h-5 text-[#3B82F6] flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Footer Info --}}
        <div class="px-4 py-2 bg-[#F8F9FF] border-t border-[#E9E9E9]">
            <p class="text-xs text-[#8890B1] {{ $isRtl ? 'text-right' : 'text-left' }} flex items-center {{ $isRtl ? 'flex-row-reverse' : '' }} gap-1.5">
                <svg class="w-3.5 h-3.5 text-[#3B82F6]" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>{{ __('Language persists across sessions') }}</span>
            </p>
        </div>
    </div>
</div>

{{-- Style Block (Only Once) --}}
@once
<style>
    /* Ensure dropdown appears above all content */
    [x-show] {
        transition-property: opacity, transform;
    }

    /* Hide dropdown initially */
    [x-cloak] {
        display: none !important;
    }

    /* Hebrew font support */
    .font-hebrew,
    [lang="he"],
    [dir="rtl"] {
        font-family: 'Heebo', 'Assistant', 'Rubik', sans-serif;
    }
</style>

<script>
// Fallback: If Alpine.js fails to load, provide vanilla JS solution
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit for Alpine to initialize (reduced from 500ms to 100ms for faster fallback)
    setTimeout(function() {
        if (typeof Alpine === 'undefined') {
            console.warn('⚠️ Alpine.js not loaded, using fallback');

            // Add click handlers to language buttons
            document.querySelectorAll('[data-locale-switch]').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const locale = this.getAttribute('data-locale-switch');

                    console.log('🌍 Fallback: Switching to locale:', locale);

                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route('locale.change') }}';

                    // CSRF token
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    form.appendChild(csrfInput);

                    // Locale input
                    const localeInput = document.createElement('input');
                    localeInput.type = 'hidden';
                    localeInput.name = 'locale';
                    localeInput.value = locale;
                    form.appendChild(localeInput);

                    document.body.appendChild(form);
                    form.submit();
                });
            });
        } else {
            console.log('✅ Alpine.js loaded successfully');
        }
    }, 100); // Reduced from 500ms to 100ms for faster response
});
</script>
@endonce
