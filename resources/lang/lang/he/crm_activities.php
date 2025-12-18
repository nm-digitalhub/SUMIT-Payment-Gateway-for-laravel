<?php

return [
    'nav_label' => 'פעילויות',

    'fields' => [
        'related_entity' => 'ישות קשורה',
        'activity_type' => 'סוג פעילות',
        'subject' => 'נושא',
        'status' => 'סטטוס',
        'priority' => 'עדיפות',
        'assigned_to' => 'מוקצה ל',
        'description' => 'תיאור',
        'start_at' => 'תחילת הפעילות',
        'end_at' => 'סיום הפעילות',
        'reminder_at' => 'תזכורת',
        'related_document_id' => 'מסמך מקושר',
        'related_ticket_id' => 'כרטיס שירות מקושר',
    ],

    'help' => [
        'related_entity' => 'הישות ב‑CRM אליה הפעילות משויכת',
        'assigned_to' => 'השאר ריק כדי לשייך למשתמש הנוכחי',
        'start_at' => 'מועד תחילת הפעילות',
        'end_at' => 'מועד סיום הפעילות',
        'reminder_at' => 'מתי לשלוח תזכורת',
        'related_document' => 'קישור לחשבונית/קבלה/מסמך',
        'related_ticket' => 'קישור לכרטיס תמיכה',
    ],

    'options' => [
        'activity_type' => [
            'call' => 'שיחה',
            'email' => 'אימייל',
            'meeting' => 'פגישה',
            'note' => 'הערה',
            'task' => 'משימה',
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
        ],
        'status' => [
            'planned' => 'מתוכננת',
            'in_progress' => 'בתהליך',
            'completed' => 'הושלמה',
            'cancelled' => 'בוטלה',
        ],
        'priority' => [
            'low' => 'נמוכה',
            'normal' => 'רגילה',
            'high' => 'גבוהה',
            'urgent' => 'דחופה',
        ],
    ],

    'columns' => [
        'activity_type' => 'סוג',
        'subject' => 'נושא',
        'related_to' => 'קשור ל',
        'created_by' => 'נוצר ע״י',
        'assigned_to' => 'מוקצה ל',
        'activity_date' => 'תאריך פעילות',
        'status' => 'סטטוס',
        'created' => 'נוצר',
        'reminder' => 'תזכורת',
        'overdue' => 'באיחור',
    ],

    'filters' => [
        'activity_type' => 'סוג פעילות',
        'related_entity' => 'ישות קשורה',
        'status' => 'סטטוס',
        'priority' => 'עדיפות',
        'upcoming' => 'עתידיות',
        'overdue' => 'באיחור',
        'has_reminder' => 'עם תזכורת',
        'from' => 'מתאריך',
        'to' => 'עד תאריך',
    ],

    'actions' => [
        'mark_in_progress' => 'סמן כמתבצע',
        'mark_completed' => 'סמן כהושלם',
        'cancel' => 'בטל',
    ],
];
