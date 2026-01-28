<?php
/**
 * ملف إعدادات التحقق من IP
 * 
 * يحتوي على جميع الإعدادات المتعلقة بنظام التحقق من IP
 */

return [
    // الرتب المعفاة من قيود IP
    'exempt_roles' => [
        'developer',
        'super_admin'
    ],
    
    // إعدادات IP
    'ip' => [
        // السماح بالـ IP الخاص (localhost, 127.0.0.1, etc.)
        'allow_private_ip' => false,
        
        // السماح بالـ IP المحجوز
        'allow_reserved_ip' => false,
        
        // الحقول التي يتم البحث فيها عن IP (بالترتيب)
        'check_headers' => [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ]
    ],
    
    // إعدادات الحضور
    'attendance' => [
        // السماح بتسجيل الحضور من IP غير مسموح به (مع تحذير)
        'allow_unverified_ip' => false,
        
        // تسجيل محاولات الحضور الفاشلة
        'log_failed_attempts' => true,
        
        // الحد الأقصى لمحاولات الحضور الفاشلة قبل القفل
        'max_failed_attempts' => 5,
        
        // مدة القفل بالدقائق
        'lockout_duration' => 30
    ],
    
    // إعدادات السجلات
    'logging' => [
        // تسجيل جميع محاولات التحقق من IP
        'log_all_attempts' => true,
        
        // تسجيل فقط المحاولات الفاشلة
        'log_failed_only' => false,
        
        // جدول السجلات
        'log_table' => 'activity_log'
    ],
    
    // إعدادات الرسائل
    'messages' => [
        'ip_not_authorized' => 'عنوان IP غير مسموح به. يرجى الاتصال بالمدير.',
        'branch_not_found' => 'الفرع غير موجود',
        'user_not_found' => 'المستخدم غير موجود',
        'user_no_branch' => 'المستخدم غير مرتبط بفرع',
        'branch_no_ip' => 'لم يتم تحديد IP مسموح به للفرع',
        'already_checked_in' => 'تم تسجيل الحضور مسبقاً لهذا اليوم',
        'checkin_success' => 'تم تسجيل الحضور بنجاح',
        'high_level_role' => 'الرتبة العالية - السماح من أي IP'
    ],
    
    // إعدادات CIDR
    'cidr' => [
        // الحد الأدنى لقناع CIDR لـ IPv4
        'ipv4_min_mask' => 8,
        
        // الحد الأقصى لقناع CIDR لـ IPv4
        'ipv4_max_mask' => 32,
        
        // الحد الأدنى لقناع CIDR لـ IPv6
        'ipv6_min_mask' => 64,
        
        // الحد الأقصى لقناع CIDR لـ IPv6
        'ipv6_max_mask' => 128
    ],
    
    // إعدادات الأمان
    'security' => [
        // تشفير عناوين IP في قاعدة البيانات
        'encrypt_ips' => false,
        
        // إخفاء جزء من IP في التقارير (مثال: 192.168.1.xxx)
        'mask_ip_in_reports' => false,
        
        // عدد الأجزاء المرئية من IP (من اليسار)
        'visible_ip_parts' => 3
    ],
    
    // إعدادات الإشعارات
    'notifications' => [
        // إرسال إشعار عند محاولة دخول من IP غير مسموح به
        'notify_on_unauthorized_ip' => true,
        
        // إرسال إشعار للمديرين فقط
        'notify_admins_only' => true,
        
        // الرتب التي يجب إرسال الإشعارات لها
        'notify_roles' => ['super_admin', 'general_manager']
    ]
];
