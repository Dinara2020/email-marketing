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

    // Lead model class (for recipient data)
    'lead_model' => env('EMAIL_MARKETING_LEAD_MODEL', 'App\\Models\\MainCrm\\Lead'),

    // Company/Settings model class (for SMTP settings storage)
    'company_model' => env('EMAIL_MARKETING_COMPANY_MODEL', 'App\\Models\\Company'),

    // Images model class (for logo)
    'images_model' => env('EMAIL_MARKETING_IMAGES_MODEL', 'App\\Models\\Images'),

    // Database connection for leads
    'lead_connection' => env('EMAIL_MARKETING_LEAD_CONNECTION', 'main'),

    // Admin route prefix
    'route_prefix' => env('EMAIL_MARKETING_ROUTE_PREFIX', 'admin/email-marketing'),

    // Admin middleware
    'middleware' => ['web', 'auth'],

    // Layout to extend for views
    'layout' => env('EMAIL_MARKETING_LAYOUT', 'admin.layout'),

    // Allowed emails for admin access (empty = all authenticated)
    'admin_emails' => [],

    // Company settings keys (for sender info and logo)
    'company_settings' => [
        'smtp_from_name' => 'SMTP_FROM_NAME',
        'site_title' => 'site_title',
        'logo_type' => 'company',
    ],
];
