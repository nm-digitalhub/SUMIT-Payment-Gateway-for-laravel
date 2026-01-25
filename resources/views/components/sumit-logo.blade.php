@props([
    'variant' => 'primary', // primary, monochrome-dark, light
    'size' => 'md',          // xs, sm, md, lg, xl, 2xl
    'class' => '',
    'alt' => 'SUMIT Payment Gateway',
])

@php
    $sizeClasses = match($size) {
        'xs' => 'h-8 w-8',
        'sm' => 'h-12 w-12',
        'md' => 'h-16 w-16',
        'lg' => 'h-24 w-24',
        'xl' => 'h-32 w-32',
        '2xl' => 'h-48 w-48',
        default => 'h-16 w-16',
    };

    $svgFiles = [
        'primary' => 'sumit-icon-primary.svg',
        'monochrome-dark' => 'sumit-icon-monochrome-dark.svg',
        'light' => 'sumit-icon-light.svg',
    ];

    $svgFile = $svgFiles[$variant] ?? $svgFiles['primary'];
    $svgPath = file_exists(public_path($svgFile))
        ? public_path($svgFile)
        : __DIR__ . '/../../../../../public/' . $svgFile;

    $svgContent = file_exists($svgPath)
        ? file_get_contents($svgPath)
        : '';
@endphp

@if(!empty($svgContent))
    <div class="sumit-logo {{ $sizeClasses }} {{ $class }}" {!! $attributes !!}>
        {!! $svgContent !!}
    </div>
@else
    <img
        src="{{ asset($svgFile) }}"
        alt="{{ $alt }}"
        class="{{ $sizeClasses }} {{ $class }}"
        {!! $attributes ?>
    >
@endif
