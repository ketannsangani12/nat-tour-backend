<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dispute Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the Stripe Dispute Management System.
    | These settings control notification behavior, file handling, and
    | integration parameters.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for Stripe API integration and webhook processing.
    |
    */
    'stripe' => [
        'webhook_tolerance' => env('DISPUTE_WEBHOOK_TOLERANCE', 300), // 5 minutes
        'retry_attempts' => env('DISPUTE_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('DISPUTE_RETRY_DELAY', 60), // seconds
        'rate_limit_delay' => env('DISPUTE_RATE_LIMIT_DELAY', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Evidence File Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for evidence file uploads and storage.
    |
    */
    'evidence' => [
        'max_file_size' => env('DISPUTE_MAX_FILE_SIZE', 10 * 1024 * 1024), // 10MB
        'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'txt'],
        'storage_disk' => env('DISPUTE_STORAGE_DISK', 's3'),
        'storage_path' => 'evidence',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for automated dispute notifications and alerts.
    |
    */
    'notifications' => [
        'enabled' => [
            'deadline' => env('DISPUTE_DEADLINE_NOTIFICATIONS', true),
            'created' => env('DISPUTE_CREATED_NOTIFICATIONS', true),
            'status_change' => env('DISPUTE_STATUS_NOTIFICATIONS', true),
        ],
        
        'channels' => ['database'], // Removed 'mail' to stop email notifications
        
        'deadline_warning_days' => [7, 3, 1], // Days before deadline to send warnings
        
        'queue' => env('DISPUTE_NOTIFICATION_QUEUE', 'notifications'),
        
        'batch_size' => env('DISPUTE_NOTIFICATION_BATCH_SIZE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Configuration
    |--------------------------------------------------------------------------
    |
    | Permission names used throughout the dispute management system.
    |
    */
    'permissions' => [
        'view' => 'view-payment-dispute',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the dispute management dashboard and interface.
    |
    */
    'dashboard' => [
        'items_per_page' => env('DISPUTE_ITEMS_PER_PAGE', 25),
        'max_items_per_page' => env('DISPUTE_MAX_ITEMS_PER_PAGE', 100),
        'cache_ttl' => env('DISPUTE_CACHE_TTL', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit and Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for audit trails and logging behavior.
    |
    */
    'audit' => [
        'enabled' => env('DISPUTE_AUDIT_ENABLED', true),
        'retention_days' => env('DISPUTE_AUDIT_RETENTION_DAYS', 2555), // 7 years
        'log_level' => env('DISPUTE_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Template Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for dispute response templates and automation.
    |
    */
    'templates' => [
        'enabled' => env('DISPUTE_TEMPLATES_ENABLED', true),
        'auto_apply' => env('DISPUTE_AUTO_APPLY_TEMPLATES', false),
        'cache_ttl' => env('DISPUTE_TEMPLATE_CACHE_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for integration with existing platform components.
    |
    */
    'integration' => [
        'booking_highlight' => env('DISPUTE_BOOKING_HIGHLIGHT', true),
        'profile_menu' => env('DISPUTE_PROFILE_MENU', true),
        'dashboard_widgets' => env('DISPUTE_DASHBOARD_WIDGETS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for performance optimization and caching.
    |
    */
    'performance' => [
        'cache_enabled' => env('DISPUTE_CACHE_ENABLED', true),
        'cache_prefix' => env('DISPUTE_CACHE_PREFIX', 'dispute'),
        'query_timeout' => env('DISPUTE_QUERY_TIMEOUT', 30), // seconds
    ],
];