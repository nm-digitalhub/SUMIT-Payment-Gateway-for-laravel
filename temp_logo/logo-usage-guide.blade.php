{{--
================================================================================
    NM-DigitalHub Logo - הנחיות שימוש בדף התשלום
================================================================================
--}}

{{--
    📐 מידות לפי האיפיון:
    - קונטיינר: 40x40px (w-10 h-10)
    - אייקון בתוך: 24x24px (w-6 h-6)
    - רקע: Gradient כחול from-[#3B82F6] to-[#2563EB]
    - צל: shadow-lg shadow-blue-500/25
--}}

{{-- ============================================
    אפשרות 1: שימוש בתמונת PNG (מומלץ)
    ============================================ --}}

{{-- העתק את nm-logo-white-24.png לתיקייה: public/images/ --}}

<div class="flex items-center gap-3">
    {{-- Logo Container --}}
    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-[#3B82F6] to-[#2563EB] shadow-lg shadow-blue-500/25">
        <img src="{{ asset('images/nm-logo-white-24.png') }}" 
             alt="NM-DigitalHub" 
             class="w-6 h-6">
    </div>
    {{-- Text --}}
    <div>
        <div class="text-lg font-bold text-gray-900">NM-DigitalHub</div>
        <div class="text-xs text-[#8890B1]">Secure Payment Gateway</div>
    </div>
</div>


{{-- ============================================
    אפשרות 2: שימוש בלוגו הצבעוני (ללא רקע כחול)
    ============================================ --}}

{{-- העתק את nm-logo-40x40.png לתיקייה: public/images/ --}}

<div class="flex items-center gap-3">
    <img src="{{ asset('images/nm-logo-40x40.png') }}" 
         alt="NM-DigitalHub" 
         class="w-10 h-10">
    <div>
        <div class="text-lg font-bold text-gray-900">NM-DigitalHub</div>
        <div class="text-xs text-[#8890B1]">Secure Payment Gateway</div>
    </div>
</div>


{{-- ============================================
    אפשרות 3: לוגו מלא עם דומיין
    ============================================ --}}

{{-- העתק את nm-logo-full-400w.png לתיקייה: public/images/ --}}

<div class="flex justify-center py-4">
    <img src="{{ asset('images/nm-logo-full-400w.png') }}" 
         alt="NM-DigitalHub" 
         class="h-12">
</div>


{{-- ============================================
    רשימת קבצים להעתקה ל-public/images/
    ============================================
    
    לשימוש באייקון:
    - nm-logo-white-24.png  (לרקע כחול, 24x24)
    - nm-logo-white-40.png  (לרקע כחול, 40x40)
    - nm-logo-40x40.png     (צבעוני, 40x40)
    - nm-logo-64x64.png     (צבעוני, 64x64)
    
    לשימוש בלוגו מלא:
    - nm-logo-full-400w.png (עם דומיין)
    - nm-logo-full.png      (גודל מקורי)
    
    לאיכות גבוהה / Retina:
    - nm-logo-128x128.png
    - nm-logo-256x256.png
    - nm-logo-white-128.png
--}}