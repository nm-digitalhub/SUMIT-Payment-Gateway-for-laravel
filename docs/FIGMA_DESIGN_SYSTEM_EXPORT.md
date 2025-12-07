# SUMIT Payment Gateway - Figma Design System Export Guide

> **מטרה**: מסמך זה מספק מפרט מלא לייבוא מערכת התשלומים ל-Figma, כולל כל התצוגות, המצבים, והרכיבים.

---

## 📋 תוכן עניינים

1. [סקירה כללית](#סקירה-כללית)
2. [מערכת עיצוב - Design Tokens](#מערכת-עיצוב---design-tokens)
3. [רכיבים מודולריים](#רכיבים-מודולריים)
4. [תצוגות מלאות](#תצוגות-מלאות)
5. [מצבי משתמש](#מצבי-משתמש)
6. [רספונסיביות - Breakpoints](#רספונסיביות---breakpoints)
7. [אינטראקציות ואנימציות](#אינטראקציות-ואנימציות)
8. [מפרט טכני ליישום](#מפרט-טכני-ליישום)

---

## 🎨 סקירה כללית

### מבנה המערכת

המערכת כוללת **3 תצוגות עיקריות**:

1. **Checkout Page** (דף תשלום ציבורי) - `checkout.blade.php`
2. **Add New Card** (הוספת כרטיס באדמין) - `add-new-card.blade.php`
3. **Payment Form Component** (רכיב תשלום מודולרי) - `payment-form.blade.php`

### תרחישי שימוש

| תרחיש | משתמש | מכשיר | אמצעי תשלום |
|-------|-------|-------|-------------|
| 1 | אורח (לא מחובר) | Desktop | כרטיס חדש |
| 2 | אורח | Mobile | כרטיס חדש |
| 3 | אורח | Tablet | כרטיס חדש |
| 4 | מחובר (עם טוקן שמור) | Desktop | כרטיס שמור |
| 5 | מחובר (עם טוקן שמור) | Mobile | כרטיס שמור |
| 6 | מחובר (עם טוקן שמור) | Tablet | כרטיס שמור |
| 7 | מחובר (ללא טוקן) | Desktop | כרטיס חדש + אופציה לשמור |
| 8 | מחובר (ללא טוקן) | Mobile | כרטיס חדש + אופציה לשמור |
| 9 | מחובר | Desktop | Bit |
| 10 | כולם | כולם | מצב שגיאה |
| 11 | כולם | כולם | מצב הצלחה |

---

## 🎨 מערכת עיצוב - Design Tokens

### צבעים (Colors)

```css
/* Primary Colors */
--og-primary: #0284c7;           /* Sky-600 - צבע ראשי */
--og-primary-hover: #0369a1;     /* Sky-700 - Hover state */
--og-primary-focus: #0ea5e9;     /* Sky-500 - Focus ring */

/* Semantic Colors */
--og-success: #22c55e;           /* Green-500 - הצלחה */
--og-success-bg: #f0fdf4;        /* Green-50 - רקע הצלחה */
--og-success-border: #86efac;    /* Green-300 - גבול הצלחה */

--og-error: #ef4444;             /* Red-500 - שגיאה */
--og-error-bg: #fef2f2;          /* Red-50 - רקע שגיאה */
--og-error-border: #fca5a5;      /* Red-300 - גבול שגיאה */

--og-warning: #f59e0b;           /* Amber-500 - אזהרה */
--og-info: #3b82f6;              /* Blue-500 - מידע */

/* Neutral Colors */
--og-gray-50: #f9fafb;
--og-gray-100: #f3f4f6;
--og-gray-200: #e5e7eb;
--og-gray-300: #d1d5db;
--og-gray-600: #4b5563;
--og-gray-700: #374151;
--og-gray-900: #111827;

/* Dark Mode Support */
--og-dark-bg: #1f2937;           /* Gray-800 */
--og-dark-surface: #111827;      /* Gray-900 */
--og-dark-border: #374151;       /* Gray-700 */
```

### טיפוגרפיה (Typography)

```css
/* Font Family */
--og-font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                "Helvetica Neue", Arial, sans-serif;
--og-font-mono: 'SF Mono', Monaco, 'Cascadia Code', 'Courier New', monospace;

/* Font Sizes */
--og-text-xs: 0.75rem;      /* 12px */
--og-text-sm: 0.875rem;     /* 14px */
--og-text-base: 1rem;       /* 16px */
--og-text-lg: 1.125rem;     /* 18px */
--og-text-xl: 1.25rem;      /* 20px */
--og-text-2xl: 1.5rem;      /* 24px */
--og-text-3xl: 1.875rem;    /* 30px */

/* Font Weights */
--og-font-normal: 400;
--og-font-medium: 500;
--og-font-semibold: 600;
--og-font-bold: 700;

/* Line Heights */
--og-leading-tight: 1.25;
--og-leading-normal: 1.5;
--og-leading-relaxed: 1.625;
```

### מרווחים (Spacing)

```css
/* Spacing Scale (Tailwind-based) */
--og-space-1: 0.25rem;    /* 4px */
--og-space-2: 0.5rem;     /* 8px */
--og-space-3: 0.75rem;    /* 12px */
--og-space-4: 1rem;       /* 16px */
--og-space-6: 1.5rem;     /* 24px */
--og-space-8: 2rem;       /* 32px */

/* Component-specific */
--og-input-padding-x: 1rem;      /* 16px */
--og-input-padding-y: 0.5rem;    /* 8px */
--og-button-padding-x: 1.5rem;   /* 24px */
--og-button-padding-y: 0.75rem;  /* 12px */
--og-card-padding: 1.5rem;       /* 24px */
```

### גבולות ועיגולים (Borders & Radius)

```css
/* Border Widths */
--og-border-thin: 1px;
--og-border-medium: 2px;

/* Border Radius */
--og-radius-sm: 0.375rem;   /* 6px */
--og-radius-md: 0.5rem;     /* 8px */
--og-radius-lg: 0.75rem;    /* 12px */
--og-radius-full: 9999px;   /* כפתור עגול */

/* Focus Ring */
--og-ring-width: 2px;
--og-ring-offset: 2px;
```

### צללים (Shadows)

```css
--og-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--og-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
                0 2px 4px -1px rgba(0, 0, 0, 0.06);
--og-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
                0 4px 6px -2px rgba(0, 0, 0, 0.05);
```

---

## 🧩 רכיבים מודולריים

### 1. Input Fields (שדות קלט)

#### Text Input - מצב רגיל

```
┌─────────────────────────────────────┐
│ Card Number *                       │
│ ┌─────────────────────────────────┐ │
│ │ •••• •••• •••• ••••             │ │
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘

Specs:
- Height: 42px (Desktop/Tablet), 48px (Mobile)
- Padding: 16px horizontal, 8px vertical
- Border: 1px solid #d1d5db (gray-300)
- Border-radius: 8px
- Font-size: 16px
- Placeholder: #9ca3af (gray-400)
```

#### Text Input - מצב Focus

```
┌─────────────────────────────────────┐
│ Card Number *                       │
│ ┌─────────────────────────────────┐ │
│ │ 4580 1234 5678 9012             │ │
│ └─────────────────────────────────┘ │
│   ↑ 2px ring: #0ea5e9 (sky-500)     │
└─────────────────────────────────────┘

Specs:
- Border: 2px solid #0284c7 (sky-600)
- Box-shadow (ring): 0 0 0 2px rgba(14, 165, 233, 0.3)
```

#### Text Input - מצב Error

```
┌─────────────────────────────────────┐
│ Card Number *                       │
│ ┌─────────────────────────────────┐ │
│ │ 1234                            │ │
│ └─────────────────────────────────┘ │
│ ⚠ Card number is required          │
└─────────────────────────────────────┘

Specs:
- Border: 2px solid #ef4444 (red-500)
- Error text: #dc2626 (red-600), 14px
- Icon: ⚠ 16px, #ef4444
```

### 2. Select Dropdown

```
┌─────────────────────────────────────┐
│ Number of Payments                  │
│ ┌─────────────────────────────────┐ │
│ │ 1 payment              ▼        │ │
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘

Specs (זהה ל-Text Input):
- Height: 42px (Desktop/Tablet), 48px (Mobile)
- Chevron icon: 16px, positioned right 12px
```

### 3. Checkbox

#### Desktop/Tablet (24x24px)

```
┌───┐
│ ✓ │ Save card for future purchases
└───┘

Specs:
- Size: 24px × 24px
- Border: 2px solid #d1d5db
- Border-radius: 4px
- Checked: background #0284c7, checkmark white
- Margin-right: 8px (RTL: margin-left)
```

#### Mobile (20x20px)

```
┌──┐
│✓ │ Save card
└──┘

Specs:
- Size: 20px × 20px
- Border: 2px solid #d1d5db
- Border-radius: 3px
```

### 4. Radio Button

```
┌────────────────────────────────────┐
│ ⦿ •••• •••• •••• 1234              │
│   (Expires 12/2025)                │
└────────────────────────────────────┘

Specs:
- Radio size: 20px × 20px (Desktop/Tablet), 18px (Mobile)
- Border: 2px solid #d1d5db
- Selected: background #0284c7 with white center dot
- Label padding: 12px
- Card container: border 1px solid #e5e7eb, padding 12px
- Hover: background #f9fafb
```

### 5. Primary Button

#### Desktop/Tablet

```
┌────────────────────────────────────┐
│  🔒 Pay ₪150.00                    │
└────────────────────────────────────┘

Specs:
- Width: 100% (max-width: 650px on tablet)
- Height: 48px
- Background: #0284c7 (sky-600)
- Hover: #0369a1 (sky-700)
- Border-radius: 8px
- Font: 16px, font-weight: 600
- Icon size: 20px, margin-right: 8px
- Padding: 12px 24px
```

#### Mobile

```
┌──────────────────────┐
│ 🔒 Pay ₪150.00       │
└──────────────────────┘

Specs:
- Width: 96% (centered with margin)
- Height: 52px (larger for touch)
- Font: 16px
- Border-radius: 8px
```

#### Loading State

```
┌────────────────────────────────────┐
│  ◌ Processing...                   │
└────────────────────────────────────┘

Specs:
- Spinner: 20px, animated rotation
- Opacity: 0.7
- Cursor: not-allowed
- Disabled attribute: true
```

### 6. Card (Container)

```
┌──────────────────────────────────────┐
│                                      │
│  [Card Content Here]                 │
│                                      │
└──────────────────────────────────────┘

Specs:
- Background: white (#ffffff)
- Border-radius: 12px
- Box-shadow: 0 1px 3px rgba(0,0,0,0.1)
- Padding: 24px (Desktop/Tablet), 16px (Mobile)
- Dark mode: background #1f2937, border #374151
```

### 7. Error Card Component

```
┌──────────────────────────────────────┐
│ ⚠  Payment Card Addition Failed      │
│                                      │
│    We encountered an issue while     │
│    processing your payment card.     │
│                                      │
│    ┌────────────────────────────┐   │
│    │ ✕ Error Message:          │   │
│    │   [Error description]     │   │
│    │                           │   │
│    │ Error Type: Validation    │   │
│    └────────────────────────────┘   │
│                                      │
│    ℹ Troubleshooting Tips:          │
│    • Tip 1                          │
│    • Tip 2                          │
│    • Tip 3                          │
└──────────────────────────────────────┘

Specs:
- Background: #fef2f2 (red-50)
- Border: 2px solid #fca5a5 (red-300)
- Icon: 48px, #ef4444 (red-500)
- Title: 18px, font-weight: 600
- Inner card: white background, padding 16px
- Tips section: #eff6ff (blue-50), border #bfdbfe
```

### 8. Success Card Component

```
┌──────────────────────────────────────┐
│ ✓  Payment Card Added Successfully!  │
│                                      │
│    The new payment card has been     │
│    securely saved.                   │
│                                      │
│    ┌────────────────────────────┐   │
│    │ Card Type:     Visa        │   │
│    │ Last 4 Digits: •••• 1234   │   │
│    │ Expiry:        12/2025     │   │
│    │ Customer:      John Doe    │   │
│    │ ────────────────────────   │   │
│    │ Default Payment: ✓ Yes     │   │
│    └────────────────────────────┘   │
└──────────────────────────────────────┘

Specs:
- Background: #f0fdf4 (green-50)
- Border: 2px solid #86efac (green-300)
- Icon: 48px, #22c55e (green-500)
- Title: 18px, font-weight: 600
- Inner card: white background, padding 16px
- Badge: #d1fae5 (green-100), text #065f46 (green-800)
```

### 9. Payment Method Tabs

```
┌──────────────────┬──────────────────┐
│  💳              │  🌐              │
│  Credit Card     │  Bit             │
│  [Active]        │                  │
└──────────────────┴──────────────────┘

Specs:
Active:
- Border: 2px solid #0284c7 (sky-600)
- Background: #f0f9ff (sky-50)
- Text: #075985 (sky-900)

Inactive:
- Border: 1px solid #e5e7eb (gray-200)
- Background: white
- Hover: border #d1d5db (gray-300)

Common:
- Padding: 16px
- Border-radius: 8px
- Icon size: 32px
- Font-size: 14px, font-weight: 500
```

### 10. Order Summary Sidebar

```
┌─────────────────────────────────┐
│ Order Summary                   │
│ ─────────────────────────────── │
│                                 │
│ Product Name × 2      ₪100.00  │
│ Service Fee            ₪20.00  │
│ Shipping               ₪30.00  │
│                                 │
│ ─────────────────────────────── │
│ Total              ₪150.00 ILS  │
└─────────────────────────────────┘

Specs:
- Width: 33.33% (Desktop), 100% (Mobile/Tablet)
- Sticky position: top 32px (Desktop only)
- Background: white
- Border-radius: 12px
- Box-shadow: 0 1px 3px rgba(0,0,0,0.1)
- Padding: 24px
- Title: 18px, font-weight: 600
- Line items: 14px, space-y 12px
- Total: 18px, font-weight: 700, color #0284c7
```

---

## 📱 תצוגות מלאות

### 1. Checkout Page - Desktop (1440px+)

#### Layout Grid

```
┌────────────────────────────────────────────────────────────┐
│                         Checkout                           │
│                 Complete your purchase securely            │
│                                                            │
│  ┌─────────────────────────────┬────────────────────────┐ │
│  │ Customer Information   66%  │  Order Summary    33%  │ │
│  │  ┌─────────┐ ┌─────────┐   │  ┌──────────────────┐ │ │
│  │  │ Name    │ │ Email   │   │  │ Product × 2      │ │ │
│  │  └─────────┘ └─────────┘   │  │ Service Fee      │ │ │
│  │  ┌─────────────────────┐   │  │ Shipping         │ │ │
│  │  │ Phone               │   │  │ ───────────────  │ │ │
│  │  └─────────────────────┘   │  │ Total ₪150.00    │ │ │
│  ├─────────────────────────────┤  └──────────────────┘ │ │
│  │ Payment Method              │                        │ │
│  │  💳 Credit Card  🌐 Bit     │  [Sticky at scroll]   │ │
│  │  ┌─────────────────────┐   │                        │ │
│  │  │ ⦿ •••• 1234         │   │                        │ │
│  │  ├─────────────────────┤   │                        │ │
│  │  │ ○ Use a new card    │   │                        │ │
│  │  └─────────────────────┘   │                        │ │
│  │  [New Card Fields]          │                        │ │
│  ├─────────────────────────────┤                        │ │
│  │ 🔒 Pay ₪150.00              │                        │ │
│  │ Secured by SUMIT            │                        │ │
│  └─────────────────────────────┴────────────────────────┘ │
└────────────────────────────────────────────────────────────┘

Container:
- Max-width: 1024px (4xl)
- Margin: auto
- Padding: 32px 16px
- Grid: 2 columns (66% + 33%)
- Gap: 32px
```

### 2. Checkout Page - Tablet (768px - 1023px)

```
┌──────────────────────────────────────┐
│           Checkout                   │
│     Complete your purchase           │
│                                      │
│  ┌────────────────────────────────┐ │
│  │ Customer Information           │ │
│  │  ┌────────┐ ┌────────┐        │ │
│  │  │ Name   │ │ Email  │        │ │
│  │  └────────┘ └────────┘        │ │
│  └────────────────────────────────┘ │
│  ┌────────────────────────────────┐ │
│  │ Payment Method                 │ │
│  │  [Radio buttons, same width]   │ │
│  └────────────────────────────────┘ │
│  ┌────────────────────────────────┐ │
│  │ Order Summary                  │ │
│  │  [Full width, below form]      │ │
│  └────────────────────────────────┘ │
│  ┌────────────────────────────────┐ │
│  │ 🔒 Pay ₪150.00 (78% width)     │ │
│  └────────────────────────────────┘ │
└──────────────────────────────────────┘

Specs:
- Grid: Single column
- Submit button: max-width 650px, centered
- Order summary: Not sticky, placed after payment
- Checkbox: 24px × 24px (same as desktop)
```

### 3. Checkout Page - Mobile (< 768px)

```
┌────────────────────┐
│     Checkout       │
│ Complete purchase  │
│                    │
│ ┌────────────────┐ │
│ │ Customer Info  │ │
│ │ ┌────────────┐ │ │
│ │ │ Name       │ │ │
│ │ └────────────┘ │ │
│ │ ┌────────────┐ │ │
│ │ │ Email      │ │ │
│ │ └────────────┘ │ │
│ │ ┌────────────┐ │ │
│ │ │ Phone      │ │ │
│ │ └────────────┘ │ │
│ └────────────────┘ │
│ ┌────────────────┐ │
│ │ Payment Method │ │
│ │ [Stacked tabs] │ │
│ └────────────────┘ │
│ ┌────────────────┐ │
│ │ Order Summary  │ │
│ │ [Collapsible]  │ │
│ └────────────────┘ │
│ ┌────────────────┐ │
│ │ 🔒 Pay ₪150    │ │
│ └────────────────┘ │
└────────────────────┘

Specs:
- Padding: 16px
- Grid: Single column
- Submit button: 96% width
- Input height: 48px (larger for touch)
- Font-size: 16px minimum (prevent zoom)
- Checkbox: 20px × 20px
```

---

## 🔄 מצבי משתמש (User States)

### State 1: Guest User - New Card (Mobile)

```
[Topbar: "Checkout"]

┌────────────────────┐
│ Customer Info      │
│ [All fields empty] │
└────────────────────┘

┌────────────────────┐
│ Payment Method     │
│ [Only "New Card"]  │
│ [No saved cards]   │
└────────────────────┘

[No "Save card" option]

Button: "Pay ₪150.00"
```

### State 2: Logged In User - With Saved Token (Desktop)

```
┌────────────────────────────────────┐
│ Customer Information               │
│ [Pre-filled from user profile]     │
└────────────────────────────────────┘

┌────────────────────────────────────┐
│ Payment Method                     │
│                                    │
│ Saved Payment Methods:             │
│ ┌────────────────────────────────┐ │
│ │ ⦿ •••• •••• •••• 1234          │ │
│ │   (Expires 12/2025)            │ │
│ ├────────────────────────────────┤ │
│ │ ○ •••• •••• •••• 5678          │ │
│ │   (Expires 03/2026)            │ │
│ ├────────────────────────────────┤ │
│ │ ○ Use a new card               │ │
│ └────────────────────────────────┘ │
│                                    │
│ [CVV field only, if required]      │
└────────────────────────────────────┘

Button: "Pay ₪150.00"
```

### State 3: Logged In User - No Saved Token (Tablet)

```
┌────────────────────────────────────┐
│ Customer Information               │
│ [Pre-filled]                       │
└────────────────────────────────────┘

┌────────────────────────────────────┐
│ Payment Method                     │
│                                    │
│ [Full card form displayed]         │
│ ┌────────────────────────────────┐ │
│ │ Card Number                    │ │
│ ├────────────────────────────────┤ │
│ │ MM  YY  CVV                    │ │
│ ├────────────────────────────────┤ │
│ │ ID Number                      │ │
│ └────────────────────────────────┘ │
│                                    │
│ ☑ Save card for future purchases   │
└────────────────────────────────────┘

Button: "Pay ₪150.00"
```

### State 4: Error Display (All Devices)

```
┌────────────────────────────────────┐
│ ⚠ Please fix the following errors: │
│ • Card number is required          │
│ • Expiration date is required      │
│ • Security code is required        │
└────────────────────────────────────┘

[Form below with error highlights]

┌────────────────────────────────────┐
│ Card Number *                      │
│ ┌────────────────────────────────┐ │
│ │ [Empty - red border]           │ │
│ └────────────────────────────────┘ │
│ ⚠ Card number is required         │
└────────────────────────────────────┘
```

### State 5: Processing (Loading)

```
┌────────────────────────────────────┐
│ [Form fields disabled, opacity 0.6]│
└────────────────────────────────────┘

┌────────────────────────────────────┐
│  ◌ Processing...                   │
│  [Spinner animation, disabled]     │
└────────────────────────────────────┘
```

### State 6: Success - Add Card (Admin)

```
┌────────────────────────────────────┐
│ ✓ Payment Card Added Successfully! │
│                                    │
│ The new payment card has been      │
│ securely saved and is ready to use.│
│                                    │
│ ┌────────────────────────────────┐ │
│ │ Card Type:     Visa            │ │
│ │ Last 4 Digits: •••• 1234       │ │
│ │ Expiry:        12/2025         │ │
│ │ Customer:      John Doe        │ │
│ │ ──────────────────────────────  │ │
│ │ Default Payment: ✓ Yes         │ │
│ └────────────────────────────────┘ │
│                                    │
│ [View All Cards] [Add Another Card]│
└────────────────────────────────────┘
```

### State 7: Bit Payment Selected

```
┌────────────────────────────────────┐
│ Payment Method                     │
│                                    │
│ [Credit Card] [Bit - Active]       │
│                                    │
│ ℹ You will be redirected to        │
│   complete your payment via Bit    │
│   after clicking the button below. │
└────────────────────────────────────┘

Button: "Continue to Bit"
```

---

## 📐 רספונסיביות - Breakpoints

### Breakpoint System

```css
/* Mobile First Approach */

/* Mobile (Default) */
/* 0px - 767px */
.og-checkout {
  --container-width: 100%;
  --padding: 16px;
  --input-height: 48px;
  --checkbox-size: 20px;
  --button-height: 52px;
}

/* Tablet */
@media (min-width: 768px) and (max-width: 1023px) {
  .og-checkout {
    --container-width: 90%;
    --padding: 24px;
    --input-height: 42px;
    --checkbox-size: 24px;
    --button-height: 48px;
    --button-max-width: 650px;
  }
}

/* Desktop */
@media (min-width: 1024px) {
  .og-checkout {
    --container-width: 1024px;
    --padding: 32px;
    --input-height: 42px;
    --checkbox-size: 24px;
    --button-height: 48px;
    --grid-columns: 2;
  }
}

/* Large Desktop */
@media (min-width: 1440px) {
  .og-checkout {
    --container-width: 1280px;
  }
}
```

### Grid Behavior

| Breakpoint | Container Width | Grid Layout | Sidebar |
|------------|----------------|-------------|---------|
| < 768px | 100% | 1 column | Below form |
| 768-1023px | 90% | 1 column | Below form |
| 1024-1439px | 1024px | 2 columns (66/33) | Sticky right |
| 1440px+ | 1280px | 2 columns (66/33) | Sticky right |

### Component Responsive Behavior

| Component | Mobile | Tablet | Desktop |
|-----------|--------|--------|---------|
| Input Height | 48px | 42px | 42px |
| Checkbox | 20×20px | 24×24px | 24×24px |
| Button Width | 96% | 650px max | 100% |
| Card Padding | 16px | 24px | 24px |
| Font Size (min) | 16px | 16px | 16px |
| Touch Target | 44px | 42px | N/A |

---

## 🎬 אינטראקציות ואנימציות

### 1. Focus States

```css
/* Input Focus */
input:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.3);
  outline: none;
  transition: all 150ms ease-in-out;
}

/* Button Hover */
button:hover {
  background-color: #0369a1;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
  transition: all 200ms ease-in-out;
}
```

### 2. Loading Animation

```css
/* Spinner */
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.spinner {
  animation: spin 1s linear infinite;
}
```

### 3. Error Shake Animation

```css
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
  20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.error-field {
  animation: shake 0.5s ease-in-out;
}
```

### 4. Success Fade In

```css
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.success-card {
  animation: fadeIn 0.4s ease-out;
}
```

### 5. Accordion/Collapse

```css
/* Order Summary Toggle (Mobile) */
.summary-toggle {
  max-height: 60px;
  overflow: hidden;
  transition: max-height 0.3s ease-in-out;
}

.summary-toggle.expanded {
  max-height: 500px;
}
```

---

## 🛠️ מפרט טכני ליישום

### HTML Structure

```html
<!-- Root Container -->
<div class="og-checkout" dir="rtl">

  <!-- Header -->
  <header class="og-header">
    <h1>Checkout</h1>
    <p>Complete your purchase securely</p>
  </header>

  <!-- Main Grid -->
  <div class="og-grid">

    <!-- Form Column (66%) -->
    <div class="og-form-column">

      <!-- Customer Info Card -->
      <section class="og-card">
        <h2>Customer Information</h2>
        <div class="og-form-grid">
          <!-- Fields -->
        </div>
      </section>

      <!-- Payment Method Card -->
      <section class="og-card">
        <h2>Payment Method</h2>

        <!-- Tabs (if Bit enabled) -->
        <div class="og-tabs">
          <button class="og-tab active">Credit Card</button>
          <button class="og-tab">Bit</button>
        </div>

        <!-- Saved Methods (if user logged in) -->
        <div class="og-saved-methods">
          <!-- Radio buttons -->
        </div>

        <!-- New Card Form -->
        <div class="og-payment-form">
          <!-- Card fields -->
        </div>
      </section>

      <!-- Submit Card -->
      <section class="og-card">
        <button class="og-button-primary">
          Pay ₪150.00
        </button>
        <div class="og-security-badge">
          Secured by SUMIT
        </div>
      </section>

    </div>

    <!-- Sidebar Column (33%) -->
    <aside class="og-sidebar">
      <section class="og-card og-sticky">
        <h2>Order Summary</h2>
        <!-- Line items -->
        <!-- Total -->
      </section>
    </aside>

  </div>
</div>
```

### CSS Class Naming Convention (BEM)

```css
/* Block */
.og-checkout { }

/* Elements */
.og-checkout__header { }
.og-checkout__grid { }
.og-checkout__form-column { }
.og-checkout__sidebar { }

/* Components */
.og-card { }
.og-card--error { }
.og-card--success { }

.og-input { }
.og-input--error { }
.og-input--disabled { }

.og-button { }
.og-button--primary { }
.og-button--secondary { }
.og-button--loading { }

/* Modifiers */
.og-grid--desktop { }
.og-grid--mobile { }
```

### Alpine.js Data Structure

```javascript
function checkoutPage() {
  return {
    // RTL Support
    rtl: true,

    // Payment Method
    paymentMethod: 'card', // 'card' | 'bit'
    selectedToken: 'new',  // 'new' | token_id

    // Card Data
    cardNumber: '',
    expMonth: '',
    expYear: '',
    cvv: '',
    citizenId: '',
    singleUseToken: '',

    // Options
    paymentsCount: '1',
    saveCard: false,

    // Customer Data
    customerName: '',
    customerEmail: '',
    customerPhone: '',

    // UI State
    processing: false,
    errors: [],

    // Methods
    init() { /* Initialize SUMIT SDK */ },
    validate() { /* Client-side validation */ },
    submitForm() { /* Handle submission */ }
  }
}
```

### Form Validation Rules

```javascript
const validationRules = {
  customerName: {
    required: true,
    minLength: 2,
    errorMessage: 'Full name is required'
  },
  customerEmail: {
    required: true,
    pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    errorMessage: 'Please enter a valid email address'
  },
  cardNumber: {
    required: true,
    minLength: 13,
    maxLength: 19,
    pattern: /^\d+$/,
    errorMessage: 'Card number is required'
  },
  expMonth: {
    required: true,
    min: 1,
    max: 12,
    errorMessage: 'Expiration month is required'
  },
  expYear: {
    required: true,
    min: new Date().getFullYear(),
    errorMessage: 'Expiration year is required'
  },
  cvv: {
    required: true, // if cvv_mode === 'required'
    minLength: 3,
    maxLength: 4,
    pattern: /^\d+$/,
    errorMessage: 'Security code is required'
  },
  citizenId: {
    required: true, // if citizen_id_mode === 'required'
    length: 9,
    pattern: /^\d{9}$/,
    errorMessage: 'ID number is required (9 digits)'
  }
};
```

---

## 📊 Component States Matrix

| Component | State | Visual | Interaction |
|-----------|-------|--------|-------------|
| Text Input | Default | Gray border, placeholder | Clickable |
| Text Input | Focus | Blue border, ring | Typing enabled |
| Text Input | Error | Red border, error text | Shake animation |
| Text Input | Disabled | Gray background, no cursor | No interaction |
| Button | Default | Blue background | Clickable |
| Button | Hover | Darker blue, shadow | Clickable |
| Button | Loading | Spinner, disabled | No interaction |
| Button | Disabled | Opacity 0.5 | No interaction |
| Checkbox | Unchecked | White, gray border | Clickable |
| Checkbox | Checked | Blue, white checkmark | Clickable |
| Radio | Unselected | White, gray border | Clickable |
| Radio | Selected | Blue, white dot | Clickable |
| Card | Default | White, subtle shadow | N/A |
| Card | Hover | Slight shadow increase | N/A |
| Error Card | Visible | Red background, border | Dismissible |
| Success Card | Visible | Green background, border | Dismissible |

---

## 🎨 Figma Import Checklist

### ✅ לפני הייבוא

- [ ] הכן 3 Frames ראשיים: Desktop, Tablet, Mobile
- [ ] צור Auto Layout components לכל רכיב
- [ ] הגדר Design Tokens בפאנל Variables
- [ ] הכן color styles לכל הצבעים
- [ ] הגדר text styles לכל גדלי הפונט

### ✅ במהלך הייבוא

- [ ] יבא את כל הרכיבים כ-Components
- [ ] הגדר Variants לכל מצבי הרכיבים (Default, Hover, Error, etc.)
- [ ] צור Auto Layout לכל ה-Cards והטפסים
- [ ] הגדר Constraints לרספונסיביות
- [ ] הוסף Interactive Components ל-Buttons ו-Inputs

### ✅ אחרי הייבוא

- [ ] בדוק את כל ה-Breakpoints
- [ ] ודא שכל הטקסטים בעברית נכתבים מימין לשמאל
- [ ] בדוק את גדלי המגע (Touch Targets) במובייל (44px minimum)
- [ ] צור Prototypes לכל התרחישים
- [ ] בדוק Accessibility (contrast ratios, focus states)
- [ ] צור Documentation page עם הסברים

---

## 📚 קבצי קוד מקור

### קבצים ראשיים

1. **checkout.blade.php** - דף התשלום הציבורי המלא
   - מיקום: `resources/views/pages/checkout.blade.php`
   - שורות: 613
   - כולל: כל התצוגות והמצבים

2. **add-new-card.blade.php** - עמוד הוספת כרטיס באדמין
   - מיקום: `resources/views/filament/resources/token-resource/pages/add-new-card.blade.php`
   - שורות: 394
   - כולל: SUMIT SDK integration

3. **payment-form.blade.php** - רכיב תשלום מודולרי
   - מיקום: `resources/views/components/payment-form.blade.php`
   - שורות: 274
   - שימוש חוזר במספר מקומות

4. **error-card.blade.php** - רכיב הצגת שגיאות
   - מיקום: `resources/views/components/error-card.blade.php`
   - שורות: 71

5. **success-card.blade.php** - רכיב הצגת הצלחה
   - מיקום: `resources/views/components/success-card.blade.php`
   - שורות: 51

### קבצי תמיכה

- **CLAUDE.md** - מדריך פיתוח מלא
- **README.md** - תיעוד משתמש בעברית
- **config/officeguy.php** - 74 הגדרות
- **AddNewCard.php** - Filament Page controller

---

## 🎯 סיכום וצעדים הבאים

### מה יש לך עכשיו

1. ✅ מפרט מלא של כל הרכיבים עם מידות מדויקות
2. ✅ Design Tokens מוכנים ליבוא ל-Figma
3. ✅ 11 תרחישי שימוש מתועדים במלואם
4. ✅ 3 Breakpoints עם התנהגות רספונסיבית
5. ✅ מצבי אינטראקציה ואנימציות
6. ✅ מבנה HTML ו-CSS מדויק

### צעדים ליישום ב-Figma

#### שלב 1: הכנה (30 דקות)

1. פתח Figma project חדש: "SUMIT Payment Gateway"
2. צור 3 Pages:
   - **Components Library** - כל הרכיבים
   - **Full Views** - כל התצוגות המלאות
   - **Documentation** - הסברים

3. הגדר Variables:
   ```
   Colors → Import all CSS variables
   Typography → Create text styles
   Spacing → Create spacing tokens
   ```

#### שלב 2: בנה רכיבים (2-3 שעות)

1. התחל עם Atomic Components:
   - Input Fields (5 variants)
   - Buttons (4 variants)
   - Checkboxes (2 variants)
   - Radio Buttons (2 variants)

2. בנה Molecule Components:
   - Payment Method Tab
   - Saved Card Row
   - Error Card
   - Success Card

3. בנה Organism Components:
   - Customer Info Section
   - Payment Method Section
   - Order Summary Sidebar

#### שלב 3: בנה תצוגות (2-3 שעות)

1. Desktop (1440px width)
   - Guest user view
   - Logged in user view
   - Error state
   - Success state

2. Tablet (768px width)
   - All 4 states above

3. Mobile (375px width)
   - All 4 states above

#### שלב 4: הוסף אינטראקציות (1-2 שעות)

1. צור Prototypes:
   - Form submission flow
   - Error handling flow
   - Success flow
   - Token selection flow

2. הוסף Animations:
   - Button hover states
   - Input focus states
   - Loading spinner
   - Error shake

#### שלב 5: תיעוד (1 שעה)

1. צור Documentation page עם:
   - Usage guidelines
   - Component specs
   - Responsive behavior
   - State management

2. הוסף annotations ל-Components
3. צור Style Guide

### סך הכל זמן משוער: 7-10 שעות עבודה

---

## 💡 טיפים ל-Figma

### Auto Layout Best Practices

```
Container (Horizontal Auto Layout)
├─ Icon (Fixed 20px)
├─ Text (Hug contents)
└─ Spacer (Fill)
```

### Responsive Frames

```
Desktop Frame (1440px)
├─ Container (Max-width: 1024px, Centered)
│   ├─ Form Column (66%, Min-width: 400px)
│   └─ Sidebar (33%, Min-width: 300px)

Tablet Frame (768px)
├─ Container (90% width, Centered)
│   └─ Single Column (100%)

Mobile Frame (375px)
├─ Container (100%, Padding: 16px)
│   └─ Single Column (100%)
```

### Component Variants

```
Input Component
├─ State: Default, Focus, Error, Disabled
├─ Size: Mobile (48px), Desktop (42px)
└─ Type: Text, Number, Select
```

---

## 📞 תמיכה נוספת

אם יש צורך בעזרה נוספת:

1. **שאלות על הקוד**: עיין ב-CLAUDE.md
2. **שאלות על העיצוב**: עיין במסמך זה
3. **שאלות על Figma**: בדוק את [Figma Best Practices](https://www.figma.com/best-practices/)
4. **שאלות על SUMIT API**: ראה [SUMIT Documentation](https://docs.sumit.co.il)

---

**תאריך עדכון אחרון**: 2025-12-07
**גרסת מסמך**: 1.0.0
**נוצר על ידי**: Claude Code (Sonnet 4.5)

---

## 🎁 נספח: Figma Plugin Recommendations

### Plugins שימושיים לייבוא

1. **HTML to Design** - להמיר HTML ישירות ל-Figma
2. **Styles to CSS** - לייצא CSS מ-Figma styles
3. **Auto Layout Guides** - להציג margins/padding
4. **Responsiveness** - לבדוק breakpoints
5. **Contrast** - לבדוק accessibility
6. **IconScout** - לייבא icons (Heroicons)
7. **Content Reel** - למלא תוכן לדוגמה
8. **Stark** - בדיקות נגישות מקיפות

### Resources חיצוניים

- **Heroicons**: https://heroicons.com (האייקונים בשימוש)
- **Tailwind CSS**: https://tailwindcss.com/docs (מערכת הצבעים)
- **SUMIT SDK**: https://app.sumit.co.il/scripts/payments.js
- **Alpine.js**: https://alpinejs.dev (למבנה ה-State)

---

**סוף המסמך** 🎉

כל המידע הנדרש לייבוא מלא של מערכת התשלומים ל-Figma נמצא במסמך זה.
בהצלחה! 🚀
