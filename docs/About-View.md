מצוין. הנה אפיון מלא, סגור ומקצועי לאייקון של SUMIT Payment Gateway, כולל גדלים, טיפוגרפיה, שימושים, קבצים ו־Do/Don’t – כך שתוכל לעבוד איתו כ־Single Source of Truth.

⸻

🎨 SUMIT Payment Gateway – Icon Design Specification (v1.0)

1️⃣ מטרת האייקון

האייקון מייצג:
	•	Payment Infrastructure – כרטיס / אמצעי תשלום
	•	Verified & Secure – סימון check / תקינות
	•	Modern Laravel / Filament SaaS – גרדיאנט אינדיגו–סגול

האייקון חייב להיות:
	•	קריא מ־32×32
	•	נקי, ללא טקסט חובה
	•	תואם Light + Dark Mode

⸻

2️⃣ מבנה ויזואלי (Anatomy)

שכבות
	1.	Background – עיגול עם גרדיאנט
	2.	Primary Object – כרטיס תשלום
	3.	Status Badge – check ירוק (Verified)

היררכיה

Card > Check Badge > Background


⸻

3️⃣ צבעים (Design Tokens)

Background Gradient

Token	Hex
Gradient Start	#6366F1 (Indigo 500)
Gradient End	#8B5CF6 (Violet 500)

Card

Element	Hex
Card fill	#FFFFFF
Card stripe (optional)	#E5E7EB

Verification Badge

Element	Hex
Badge	#10B981 (Emerald 500)
Check	#FFFFFF


⸻

4️⃣ טיפוגרפיה (אם נדרש טקסט)

כלל זהב

❌ לא להשתמש בטקסט בתוך אייקון אפליקטיבי
(לא קריא ב־32px)

אם חייבים טקסט (Marketing בלבד)

Property	Value
Font	Inter / system-ui
Weight	700 (Bold)
Tracking	-0.01em
Color	#111827


⸻

5️⃣ Grid & פרופורציות (SVG)

Canvas

viewBox="0 0 200 200"

Background

cx: 100
cy: 100
r: 100

Card

Property	Value
x	50
y	70
width	100
height	60
radius	12

Badge

Property	Value
center	(125, 80)
radius	12
stroke-width	3


⸻

6️⃣ גדלים רשמיים (Required Sizes)

SVG (מקור)
	•	sumit-icon.svg (Scalable – חובה)

PNG Export

Usage	Size
Favicon	32×32
Small UI	48×48
Filament Sidebar	64×64
Mobile App	128×128
PWA / Marketing	256×256
App Store / HiDPI	512×512

📌 כל ה־PNG נגזרים מ־SVG בלבד

⸻

7️⃣ Shadow & Depth

Context	Shadow
App icon / Filament	❌ None
About page / Header	✅ Soft

Soft Shadow Spec

y: 4
blur: 12
opacity: 0.15
color: #000000


⸻

8️⃣ וריאציות רשמיות

A. Primary Icon (Default)
	•	Gradient
	•	Card + Check
	•	ללא טקסט
✔ Filament / App / About / Packagist

B. Monochrome
	•	Fill: #111827 או #FFFFFF
	•	ללא גרדיאנט
✔ PDF / Print / Dark-only

C. Marketing Lockup (Optional)

[ ICON ]  SUMIT Payment Gateway

	•	טקסט מחוץ לאייקון בלבד

⸻

9️⃣ שימושים מומלצים

Filament / Blade

<x-filament::icon>
    {!! file_get_contents(resource_path('icons/sumit-icon.svg')) !!}
</x-filament::icon>

Public Asset

<img src="{{ asset('vendor/officeguy/sumit-icon.svg') }}"
     class="h-16 w-16"
     alt="SUMIT Payment Gateway">


⸻

🔴 Do / Don’t

✅ Do
	•	להשתמש ב־SVG
	•	לשמור על פרופורציות
	•	לשמור על badge בתוך הכרטיס
	•	להשתמש בגרדיאנט הרשמי בלבד

❌ Don’t
	•	לא להוסיף טקסט בתוך הכרטיס
	•	לא לשנות צבעים “לפי טעם”
	•	לא להגדיל badge מעבר ל־20% מהכרטיס
	•	לא לשים shadow באייקון קטן

⸻

10️⃣ SVG Reference (Final)

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#6366F1"/>
      <stop offset="100%" stop-color="#8B5CF6"/>
    </linearGradient>
  </defs>

  <circle cx="100" cy="100" r="100" fill="url(#bg)"/>

  <rect x="50" y="70" width="100" height="60" rx="12" fill="#FFFFFF"/>

  <circle cx="125" cy="80" r="12" fill="#10B981"/>
  <path d="M120 80 L124 84 L132 74"
        stroke="#FFFFFF"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"/>
</svg>


⸻

11️⃣ Ready for Production ✔

האייקון:
	•	תואם Filament v4
	•	מתאים ל־About page
	•	מוכן ל־Packagist / README
	•	קריא בכל רזולוציה
