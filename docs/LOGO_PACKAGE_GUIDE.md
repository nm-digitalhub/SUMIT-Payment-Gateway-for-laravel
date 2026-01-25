# SUMIT Payment Gateway - Logo Package Guide

## Overview

Professional SVG logo package for SUMIT Payment Gateway, designed for Filament v4 integration and general use across web, mobile, and print applications.

**Version**: 1.0
**Created**: 2026-01-22
**Format**: Scalable Vector Graphics (SVG)

---

## File Contents

### Primary Logos

| File | Purpose | Colors |
|------|---------|--------|
| `sumit-icon-primary.svg` | Default icon with gradient | Indigo → Violet gradient |
| `sumit-icon-monochrome-dark.svg` | Light backgrounds | Dark gray (#111827) |
| `sumit-icon-light.svg` | Dark backgrounds | White outline |

### Specialized Versions

| File | Purpose | Size |
|------|---------|------|
| `sumit-favicon.svg` | Browser favicon, 32×32 | Optimized for small sizes |
| `sumit-logo-lockup.svg` | Marketing materials | 400×100 (icon + text) |

---

## Design Specifications

### Color Palette

**Primary Gradient**
```
Start: #6366F1 (Indigo 500)
End:   #8B5CF6 (Violet 500)
```

**Accent Colors**
```
Card Background: #FFFFFF
Card Stripe:     #E5E7EB (50% opacity)
Badge:           #10B981 (Emerald 500)
Checkmark:       #FFFFFF
```

**Monochrome**
```
Dark:  #111827 (Gray 900)
Light: #FFFFFF (White)
```

### Technical Specs

**Canvas Size**: 200×200px (except favicon: 32×32, lockup: 400×100)
**Grid System**: 200×200 coordinate system
**Border Radius**: 12px (card), 12px (badge)
**Stroke Width**: 2.5-3px (checkmark)

---

## Usage Examples

### Filament Integration

**In Resource Classes:**

```php
use Filament\Tables\Columns\ImageColumn;

ImageColumn::make('logo')
    ->label('Logo')
    ->svg(asset('vendor/officeguy/sumit-icon-primary.svg'))
    ->width(64),
```

**In Panel Providers:**

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->brandLogo(asset('vendor/officeguy/sumit-icon-primary.svg'))
        ->brandLogoHeight('3rem');
}
```

**In Blade Templates:**

```blade
{{-- Primary icon --}}
<img src="{{ asset('vendor/officeguy/sumit-icon-primary.svg') }}"
     alt="SUMIT Payment Gateway"
     class="h-16 w-16">

{{-- Monochrome for dark mode --}}
<picture>
    <source srcset="{{ asset('vendor/officeguy/sumit-icon-light.svg') }}"
            media="(prefers-color-scheme: dark)">
    <img src="{{ asset('vendor/officeguy/sumit-icon-primary.svg') }}"
         alt="SUMIT Payment Gateway"
         class="h-16 w-16">
</picture>

{{-- Inline SVG for direct control --}}
{!! file_get_contents(public_path('vendor/officeguy/sumit-icon-primary.svg')) !!}
```

### Package Asset Publishing

**Publish assets to application:**

```bash
php artisan vendor:publish --tag=officeguy-assets
```

**Access in views:**

```blade
<img src="{{ asset('vendor/officeguy/sumit-icon-primary.svg') }}" alt="SUMIT">
```

---

## Export to PNG

### Using Inkscape (Recommended)

```bash
# Install Inkscape
sudo apt-get install inkscape  # Linux
brew install --cask inkscape    # macOS

# Export PNG at various sizes
inkscape sumit-icon-primary.svg --export-png=sumit-32.png --export-width=32
inkscape sumit-icon-primary.svg --export-png=sumit-48.png --export-width=48
inkscape sumit-icon-primary.svg --export-png=sumit-64.png --export-width=64
inkscape sumit-icon-primary.svg --export-png=sumit-128.png --export-width=128
inkscape sumit-icon-primary.svg --export-png=sumit-256.png --export-width=256
inkscape sumit-icon-primary.svg --export-png=sumit-512.png --export-width=512
```

### Using ImageMagick

```bash
# Install ImageMagick
sudo apt-get install imagemagick  # Linux
brew install imagemagick          # macOS

# Export with transparency
convert -background none sumit-icon-primary.svg sumit-icon.png
convert -background none -resize 32x32 sumit-icon-primary.svg sumit-32.png
convert -background none -resize 64x64 sumit-icon-primary.svg sumit-64.png
```

### Online Conversion

Visit: https://cloudconvert.com/svg-to-png
1. Upload SVG file
2. Select PNG format
3. Set desired size (e.g., 512×512)
4. Download converted file

---

## Responsive Implementation

### CSS

```css
/* Base styles */
.sumit-icon {
    width: 100%;
    max-width: 200px;
    height: auto;
    display: inline-block;
}

/* Size variations */
.sumit-icon.sm { max-width: 32px; }
.sumit-icon.md { max-width: 64px; }
.sumit-icon.lg { max-width: 128px; }
.sumit-icon.xl { max-width: 256px; }

/* Mobile responsive */
@media (max-width: 768px) {
    .sumit-icon {
        max-width: 64px;
    }
}
```

### HTML

```html
<!-- With size classes -->
<img src="sumit-icon-primary.svg"
     alt="SUMIT Payment Gateway"
     class="sumit-icon md">

<!-- Dark mode support -->
<picture>
    <source srcset="sumit-icon-light.svg"
            media="(prefers-color-scheme: dark)">
    <img src="sumit-icon-primary.svg"
         alt="SUMIT Payment Gateway"
         class="sumit-icon">
</picture>
```

---

## Favicon Implementation

### HTML Head

```html
<!-- Add to <head> section -->
<link rel="icon" type="image/svg+xml" href="/sumit-favicon.svg">
<link rel="apple-touch-icon" href="/sumit-256.png"> <!-- Export PNG version -->
```

### Laravel Blade

```blade
<!-- In layouts/app.blade.php -->
<link rel="icon" type="image/svg+xml" href="{{ asset('sumit-favicon.svg') }}">
```

---

## Do's and Don'ts

### ✅ Do

- Use SVG format for scalability
- Maintain aspect ratio (1:1 for icons)
- Provide sufficient clear space around logo
- Use appropriate contrast ratios
- Test at actual sizes before deployment
- Use semantic HTML with `alt` attributes

### ❌ Don't

- Stretch or distort the logo
- Change colors outside approved palette
- Add effects (drop shadows, glows, gradients overlay)
- Rotate or skew the logo
- Place on busy backgrounds without clear space
- Recreate logo from scratch

---

## Clear Space & Minimum Size

### Clear Space

Maintain minimum clear space equal to the badge radius (12px) around the icon:
```
┌─────────────────────────┐
│    ↖️ 12px clear space ↗️    │
│  ┌───────────────────┐  │
│  │                   │  │
│  │   [SUMIT LOGO]    │  │
│  │                   │  │
│  └───────────────────┘  │
│    ↙️ 12px clear space ↘️    │
└─────────────────────────┘
```

### Minimum Sizes

| Context | Minimum Size |
|---------|--------------|
| Digital (screen) | 32px width |
| Print | 0.5 inch (12.7mm) width |
| Favicon | 16×16px (use favicon.svg) |
| App Icon | 48×48px |

---

## Accessibility

### ARIA Labels

```html
<!-- With ARIA for screen readers -->
<img src="sumit-icon-primary.svg"
     alt="SUMIT Payment Gateway - Verified secure payment icon"
     role="img"
     aria-labelledby="sumit-logo">
```

### SVG with Accessibility

The provided SVG files include:
- `role="img"` - Identifies as image
- `aria-labelledby` - Links to title and desc
- `<title>` - Short name
- `<desc>` - Detailed description

---

## Color Mode Support

### Automatic Dark Mode

```blade
<!-- Tailwind CSS approach -->
<div class="dark:hidden">
    <img src="{{ asset('sumit-icon-primary.svg') }}" alt="SUMIT">
</div>
<div class="hidden dark:block">
    <img src="{{ asset('sumit-icon-light.svg') }}" alt="SUMIT">
</div>
```

### CSS Approach

```css
/* Auto-switch based on color scheme */
.sumit-logo {
    content: url('sumit-icon-primary.svg');
}

@media (prefers-color-scheme: dark) {
    .sumit-logo {
        content: url('sumit-icon-light.svg');
    }
}
```

---

## File Organization

```
public/
├── sumit-icon-primary.svg          # Main gradient icon
├── sumit-icon-monochrome-dark.svg  # For light backgrounds
├── sumit-icon-light.svg            # For dark backgrounds
├── sumit-favicon.svg               # 32×32 favicon
└── sumit-logo-lockup.svg           # Marketing lockup

resources/
└── views/
    └── components/
        └── sumit-logo.blade.php    # Reusable component
```

---

## Troubleshooting

### SVG Not Displaying

**Issue**: SVG shows as broken image or code
**Solution**:
1. Check file path: `asset('vendor/officeguy/sumit-icon-primary.svg')`
2. Verify file exists in `public/` directory
3. Check MIME type in `.htaccess`:
   ```apache
   AddType image/svg+xml .svg
   ```

### Colors Look Wrong

**Issue**: Colors appear different than expected
**Solution**:
1. Ensure no CSS overrides affecting SVG
2. Check for color profiles in image editors
3. Verify hex codes match specification

### Small Size Blurriness

**Issue**: Icon blurry at small sizes (favicon)
**Solution**:
1. Use `sumit-favicon.svg` for sizes under 48px
2. Export PNG at 2x resolution for retina displays
3. Test at actual display size

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-22 | Initial release based on About-View.md specification |

---

## Support

For issues or questions about the SUMIT Payment Gateway logo package:
- **Documentation**: See `docs/About-View.md` for design specification
- **Package**: `officeguy/laravel-sumit-gateway`
- **GitHub**: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel

---

## License

MIT License - Part of the SUMIT Payment Gateway for Laravel package.

---

**Last Updated**: 2026-01-22
**Package Version**: v2.3.0
