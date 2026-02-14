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

    /*
    |--------------------------------------------------------------------------
    | Lead/Recipient Model
    |--------------------------------------------------------------------------
    |
    | The model class for email recipients. Must have: id, email, company_name or name
    |
    */
    'lead_model' => env('EMAIL_MARKETING_LEAD_MODEL', null),

    // Database connection for leads (null = default connection)
    'lead_connection' => env('EMAIL_MARKETING_LEAD_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy (SaaS)
    |--------------------------------------------------------------------------
    |
    | For SaaS applications, you can provide a tenant resolver callback.
    | This allows each tenant to have their own SMTP settings.
    |
    | Example:
    | 'tenant_resolver' => fn() => auth()->user()?->tenant_id,
    |
    */
    'tenant_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Optional External Models
    |--------------------------------------------------------------------------
    |
    | These models are optional and provide additional features:
    | - images_model: For logo in emails (must have: type, src columns)
    |
    */
    'images_model' => env('EMAIL_MARKETING_IMAGES_MODEL', null),

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Settings
    |--------------------------------------------------------------------------
    */

    // Admin route prefix
    'route_prefix' => env('EMAIL_MARKETING_ROUTE_PREFIX', 'admin/email-marketing'),

    // Admin middleware
    'middleware' => ['web', 'auth'],

    // Layout to extend for views (null = use package's built-in layout)
    'layout' => env('EMAIL_MARKETING_LAYOUT', null),

    // Allowed emails for admin access (empty = all authenticated)
    'admin_emails' => [],

    /*
    |--------------------------------------------------------------------------
    | Email Branding
    |--------------------------------------------------------------------------
    */
    'branding' => [
        'logo_type' => 'company', // Used with images_model to find logo
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Upload Settings
    |--------------------------------------------------------------------------
    */
    'image_disk' => env('EMAIL_MARKETING_IMAGE_DISK', 'public'),
    'image_max_size' => env('EMAIL_MARKETING_IMAGE_MAX_SIZE', 2048), // KB
];
