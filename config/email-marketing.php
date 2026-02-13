<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Marketing Settings
    |--------------------------------------------------------------------------
    */

    // Rate limiting: max emails per hour
    'rate_limit' => env('EMAIL_MARKETING_RATE_LIMIT', 10),

    // Base delay between emails (seconds)
    'base_delay' => env('EMAIL_MARKETING_BASE_DELAY', 360),

    // Random delay range (seconds) - adds 0 to this value
    'random_delay' => env('EMAIL_MARKETING_RANDOM_DELAY', 120),

    // Lead/Recipient model class (must have: id, email, company_name or name)
    'lead_model' => env('EMAIL_MARKETING_LEAD_MODEL', null),

    // Company/Settings model class for storing SMTP settings (must have: key, value columns)
    // Set to null to use .env SMTP settings instead
    'company_model' => env('EMAIL_MARKETING_COMPANY_MODEL', null),

    // Images model class for logo (must have: type, src columns)
    // Set to null to disable logo in emails
    'images_model' => env('EMAIL_MARKETING_IMAGES_MODEL', null),

    // Database connection for leads (null = default connection)
    'lead_connection' => env('EMAIL_MARKETING_LEAD_CONNECTION', null),

    // Admin route prefix
    'route_prefix' => env('EMAIL_MARKETING_ROUTE_PREFIX', 'admin/email-marketing'),

    // Admin middleware
    'middleware' => ['web', 'auth'],

    // Layout to extend for views (null = use package's built-in layout)
    'layout' => env('EMAIL_MARKETING_LAYOUT', null),

    // Allowed emails for admin access (empty = all authenticated)
    'admin_emails' => [],

    // Company settings keys (for sender info and logo)
    'company_settings' => [
        'smtp_from_name' => 'SMTP_FROM_NAME',
        'site_title' => 'site_title',
        'logo_type' => 'company',
    ],
];
