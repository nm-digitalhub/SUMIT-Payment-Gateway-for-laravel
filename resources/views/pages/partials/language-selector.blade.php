@php
    /**
     * Language Selector Component
     *
     * Reusable, accessible language switcher for multi-locale applications
     *
     * Architecture:
     * - Leverages config('app.available_locales') as single source of truth
     * - Alpine.js for reactive UI (dropdown state management)
     * - Session-based locale persistence via SetLocaleMiddleware
     * - Full RTL/LTR support with automatic direction switching
     * - ARIA compliant for screen readers
     * - Mobile-first responsive design
     *
     * Props:
     * @param string $position Optional positioning: 'header'|'footer'|'inline' (default: 'header')
     * @param string $size Optional size: 'sm'|'md'|'lg' (default: 'md')
     * @param bool $showFlags Optional show country flags (default: true)
     * @param bool $showLabels Optional show language names (default: true)
     * @param string $variant Optional style variant: 'minimal'|'full' (default: 'full')
     *
     * Usage Examples:
     * Basic:
     *   @include('officeguy::pages.partials.language-selector')
     *
     * Minimal header:
     *   @include('officeguy::pages.partials.language-selector', ['variant' => 'minimal', 'position' => 'header'])
     *
     * Footer with labels only:
     *   @include('officeguy::pages.partials.language-selector', ['showFlags' => false, 'position' => 'footer'])
     */

    // Get available locales from config (single source of truth)
    $availableLocales = config('app.available_locales', []);
    $currentLocale = app()->getLocale();
    $currentLocaleData = $availableLocales[$currentLocale] ?? null;

    // Props with defaults
    $position = $position ?? 'header';
    $size = $size ?? 'md';
    $showFlags = $showFlags ?? true;
    $showLabels = $showLabels ?? true;
    $variant = $variant ?? 'full';

    // Size mappings (Tailwind classes)
    $sizeClasses = [
        'sm' => 'text-xs px-2 py-1',
        'md' => 'text-sm px-3 py-2',
        'lg' => 'text-base px-4 py-3',
    ];

    $buttonSize = $sizeClasses[$size] ?? $sizeClasses['md'];

    // Position-specific styles
    $positionClasses = [
        'header' => 'fixed top-4 z-50',
        'footer' => 'relative',
        'inline' => 'relative',
    ];

    $positionClass = $positionClasses[$position] ?? $positionClasses['inline'];

    // Direction-aware positioning (RTL vs LTR)
    $isRtl = in_array($currentLocale, ['he', 'ar']);
    $directionClass = $isRtl ? 'left-4' : 'right-4';

    // Variant styles
    $isMinimal = $variant === 'minimal';
@endphp

{{-- Language Selector Container --}}
<div
    class="{{ $position === 'header' ? $positionClass . ' ' . $directionClass : $positionClass }}"
    x-data="{
        open: false,
        currentLocale: '{{ $currentLocale }}',
        locales: @js($availableLocales),

        /**
         * Switch language by sending POST request with CSRF token
         * Preserves user context and redirects to same page with new locale
         */
        switchLanguage(locale) {
            if (locale === this.currentLocale) {
                this.open = false;
                return;
            }

            // Create form dynamically to handle CSRF
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('locale.switch') ?? url('/locale/switch') }}';

            // CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);

            // Locale input
            const localeInput = document.createElement('input');
            localeInput.type = 'hidden';
            localeInput.name = 'locale';
            localeInput.value = locale;
            form.appendChild(localeInput);

            // Redirect URL (preserve current page)
            const redirectInput = document.createElement('input');
            redirectInput.type = 'hidden';
            redirectInput.name = 'redirect';
            redirectInput.value = window.location.href;
            form.appendChild(redirectInput);

            document.body.appendChild(form);
            form.submit();
        },

        /**
         * Close dropdown when clicking outside
         */
        closeOnClickAway(event) {
            if (!this.$el.contains(event.target)) {
                this.open = false;
            }
        }
    }"
    @click.away="open = false"
    @keydown.escape.window="open = false"
    role="navigation"
    aria-label="{{ __('Language selector') }}"
>
    {{-- Dropdown Button --}}
    <button
        type="button"
        @click="open = !open"
        class="flex items-center gap-2 bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700
               rounded-lg {{ $buttonSize }} font-medium text-gray-700 dark:text-gray-300
               hover:border-[#3B82F6] hover:bg-gray-50 dark:hover:bg-gray-700
               focus:outline-none focus:ring-2 focus:ring-[#3B82F6] focus:ring-offset-2
               transition-all duration-200 shadow-sm hover:shadow-md"
        :aria-expanded="open"
        aria-haspopup="true"
        aria-controls="language-dropdown"
    >
        {{-- Current Language Display --}}
        <span class="flex items-center gap-1.5">
            @if($showFlags && $currentLocaleData)
                <span class="text-lg" aria-hidden="true">{{ $currentLocaleData['flag'] }}</span>
            @endif

            @if($showLabels && $currentLocaleData && !$isMinimal)
                <span class="{{ $isRtl ? 'font-hebrew' : '' }}">
                    {{ $currentLocaleData['name'] }}
                </span>
            @endif

            @if($isMinimal && $showLabels)
                <span class="uppercase font-semibold">{{ $currentLocale }}</span>
            @endif
        </span>

        {{-- Chevron Icon --}}
        <svg
            class="w-4 h-4 transition-transform duration-200"
            :class="{ 'rotate-180': open }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Dropdown Menu --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        id="language-dropdown"
        class="absolute {{ $isRtl ? 'left-0' : 'right-0' }} mt-2 w-48 bg-white dark:bg-gray-800
               rounded-xl shadow-lg border border-gray-200 dark:border-gray-700
               overflow-hidden z-50"
        role="menu"
        aria-orientation="vertical"
    >
        <div class="py-1">
            @foreach($availableLocales as $localeCode => $localeData)
                <button
                    type="button"
                    @click="switchLanguage('{{ $localeCode }}')"
                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm
                           {{ $localeCode === $currentLocale
                              ? 'bg-gradient-to-r from-[#DBEAFE] to-[#EFF6FF] text-[#3B82F6] font-semibold'
                              : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}
                           transition-colors duration-150
                           {{ $isRtl && in_array($localeCode, ['he', 'ar']) ? 'text-right' : 'text-left' }}"
                    role="menuitem"
                    :aria-current="currentLocale === '{{ $localeCode }}' ? 'true' : 'false'"
                >
                    {{-- Flag --}}
                    @if($showFlags)
                        <span class="text-xl flex-shrink-0" aria-hidden="true">
                            {{ $localeData['flag'] }}
                        </span>
                    @endif

                    {{-- Language Name --}}
                    <span class="flex-1 {{ in_array($localeCode, ['he', 'ar']) ? 'font-hebrew' : '' }}">
                        {{ $localeData['name'] }}
                    </span>

                    {{-- Check Icon for Current Language --}}
                    @if($localeCode === $currentLocale)
                        <svg class="w-4 h-4 text-[#3B82F6] flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Footer (Optional Info) --}}
        @if(!$isMinimal)
        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400 {{ $isRtl ? 'text-right' : 'text-left' }}">
                {{ __('Language will persist across sessions') }}
            </p>
        </div>
        @endif
    </div>
</div>

{{-- Styles for Hebrew Font (if needed) --}}
@once
<style>
    [x-cloak] { display: none !important; }

    .font-hebrew {
        font-family: 'Heebo', 'Assistant', 'Rubik', sans-serif;
    }

    /* Smooth dropdown animation */
    [x-show] {
        transition: opacity 150ms ease-in-out, transform 150ms ease-in-out;
    }
</style>
@endonce
