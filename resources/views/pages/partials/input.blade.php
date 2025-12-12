@php
    /**
     * Input Component - Updated Styling (Figma Design)
     * 
     * Design Tokens:
     * - Background: #F2F4F7
     * - Border: #E9E9E9
     * - Text: #383E53
     * - Placeholder: #8890B1
     * - Focus Ring: #4AD993
     * - Error: #FF7878
     * 
     * Props:
     * @param string $id - Input ID and name
     * @param string $label - Label text
     * @param string $type - Input type (text, email, tel, etc.)
     * @param string $value - Pre-filled value
     * @param bool $required - Is field required
     * @param string $model - Alpine.js x-model binding
     * @param string $placeholder - Custom placeholder
     * @param string $helper - Helper text below input
     * @param string $icon - SVG icon HTML
     * @param string $dir - Text direction (ltr/rtl)
     * @param bool $disabled - Disable input
     * @param bool $readonly - Readonly input
     * @param string $error - Error message
     * @param bool $lockFilled - Lock filled fields (default: true)
     */
    
    $hasValue = filled($value ?? null);
    $isLocked = $hasValue && ($lockFilled ?? true);
    $rtl = app()->getLocale() === 'he' || app()->getLocale() === 'ar';
    
    // Determine text direction based on input type
    $inputDir = $dir ?? (in_array($type ?? 'text', ['email', 'tel', 'number', 'url']) ? 'ltr' : null);
    
    // Build input classes
    $baseClasses = 'w-full px-4 py-3 rounded-lg transition-all duration-200';
    
    if ($isLocked || ($disabled ?? false) || ($readonly ?? false)) {
        $inputClasses = $baseClasses . ' bg-gray-100 border border-gray-200 text-gray-600 cursor-not-allowed';
    } elseif (!empty($error)) {
        $inputClasses = $baseClasses . ' bg-[#F2F4F7] border-2 border-[#FF7878] text-[#383E53] placeholder-[#8890B1] focus:ring-2 focus:ring-[#FF7878] focus:border-transparent';
    } else {
        $inputClasses = $baseClasses . ' bg-[#F2F4F7] border border-[#E9E9E9] text-[#383E53] placeholder-[#8890B1] focus:ring-2 focus:ring-[#4AD993] focus:border-transparent';
    }
    
    // Add text alignment for LTR inputs in RTL context
    if ($inputDir === 'ltr') {
        $inputClasses .= ' text-left';
    } elseif ($rtl) {
        $inputClasses .= ' text-right';
    }
    
    // Add icon padding
    if (!empty($icon)) {
        $inputClasses .= $rtl ? ' pl-10' : ' pr-10';
    }
@endphp

<div class="w-full">
    {{-- Label --}}
    <label 
        for="{{ $id }}" 
        class="block text-sm font-medium text-[#383E53] mb-2 {{ $rtl ? 'text-right' : 'text-left' }} flex items-center gap-2 flex-wrap"
    >
        <span>{{ $label }}</span>
        
        @if(!empty($required) && !$isLocked)
            <span class="text-[#FF7878]">*</span>
        @endif
        
        @if($isLocked)
            <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold text-[#3B82F6] bg-gradient-to-r from-[#DBEAFE] to-[#EFF6FF] rounded-full shadow-sm">
                <svg class="w-3.5 h-3.5 {{ $rtl ? 'ml-1' : 'mr-1' }}" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                {{ __('Saved') }}
            </span>
        @endif
    </label>
    
    {{-- Input Container --}}
    <div class="relative">
        @if(($type ?? 'text') === 'select')
            {{-- Select Input --}}
            <select
                id="{{ $id }}"
                name="{{ $id }}"
                @if(!empty($model)) x-model="{{ $model }}" @endif
                class="{{ $inputClasses }} appearance-none {{ $rtl ? 'pr-4 pl-10' : 'pl-4 pr-10' }}"
                @if($isLocked || ($disabled ?? false)) disabled @endif
                @if(!empty($required) && !$isLocked) required @endif
            >
                {{ $slot ?? '' }}
            </select>
            {{-- Select Arrow --}}
            <div class="absolute {{ $rtl ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2 pointer-events-none">
                <svg class="w-5 h-5 text-[#8890B1]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        @elseif(($type ?? 'text') === 'textarea')
            {{-- Textarea --}}
            <textarea
                id="{{ $id }}"
                name="{{ $id }}"
                @if(!empty($model)) x-model="{{ $model }}" @endif
                rows="{{ $rows ?? 3 }}"
                placeholder="{{ $placeholder ?? '' }}"
                class="{{ $inputClasses }} resize-none"
                @if($isLocked || ($disabled ?? false)) disabled @endif
                @if($readonly ?? false) readonly @endif
                @if(!empty($required) && !$isLocked) required @endif
            >{{ old($id, $value ?? '') }}</textarea>
        @else
            {{-- Standard Input --}}
            <input
                type="{{ $type ?? 'text' }}"
                id="{{ $id }}"
                name="{{ $id }}"
                @if(!empty($model)) x-model="{{ $model }}" @endif
                value="{{ old($id, $value ?? '') }}"
                placeholder="{{ $placeholder ?? '' }}"
                @if($inputDir) dir="{{ $inputDir }}" @endif
                class="{{ $inputClasses }}"
                @if($isLocked || ($disabled ?? false)) disabled @endif
                @if($readonly ?? false) readonly @endif
                @if(!empty($required) && !$isLocked) required @endif
                @if(!empty($maxlength)) maxlength="{{ $maxlength }}" @endif
                @if(!empty($minlength)) minlength="{{ $minlength }}" @endif
                @if(!empty($pattern)) pattern="{{ $pattern }}" @endif
                @if(!empty($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
                @if(in_array($type ?? 'text', ['tel', 'number'])) inputmode="numeric" @endif
            >
        @endif
        
        {{-- Icon (if provided) --}}
        @if(!empty($icon))
            <div class="absolute {{ $rtl ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2 pointer-events-none text-[#8890B1]">
                {!! $icon !!}
            </div>
        @endif
        
        {{-- Success Checkmark (for locked/filled fields) --}}
        @if($isLocked && empty($icon))
            <div class="absolute {{ $rtl ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2">
                <svg class="w-5 h-5 text-[#3B82F6]" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
        @endif
    </div>
    
    {{-- Helper Text or Error Message --}}
    @if(!empty($error))
        <p class="text-[#FF7878] text-xs mt-1.5 {{ $rtl ? 'text-right' : 'text-left' }} flex items-center gap-1">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $error }}
        </p>
    @elseif(!empty($helper))
        <p class="text-[#8890B1] text-xs mt-1.5 {{ $rtl ? 'text-right' : 'text-left' }}">
            {{ $helper }}
        </p>
    @endif
</div>
