@php
    /**
     * Form Section Component - Updated Styling (Figma Design)
     * 
     * Props:
     * @param string $title - Section title
     * @param string $subtitle - Optional subtitle
     * @param string $icon - SVG icon HTML
     * @param string $iconBg - Icon background color (default: #4BD0CC)
     * @param bool $gradient - Use gradient header (default: true)
     */
    
    $rtl = app()->getLocale() === 'he' || app()->getLocale() === 'ar';
    $iconBgColor = $iconBg ?? '#4BD0CC';
    $useGradient = $gradient ?? true;
@endphp

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    {{-- Section Header --}}
    @if($useGradient)
        <div class="bg-gradient-to-{{ $rtl ? 'l' : 'r' }} from-[#E8F9F9] to-[#F0FDFD] p-4 border-b border-gray-100">
    @else
        <div class="p-4 border-b border-gray-100">
    @endif
        <div class="flex items-center gap-3 {{ $rtl ? 'flex-row-reverse justify-end' : 'justify-start' }}">
            {{-- Icon --}}
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: {{ $iconBgColor }}">
                @if(!empty($icon))
                    {!! $icon !!}
                @else
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                @endif
            </div>
            
            {{-- Title --}}
            <div class="{{ $rtl ? 'text-right' : 'text-left' }}">
                <h2 class="text-lg font-semibold text-[#111928]">{{ $title }}</h2>
                @if(!empty($subtitle))
                    <p class="text-sm text-[#8890B1]">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Section Content --}}
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{ $slot }}
        </div>
    </div>
</div>
