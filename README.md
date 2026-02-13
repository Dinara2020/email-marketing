# Laravel Email Marketing

A comprehensive email marketing package for Laravel with campaigns, templates, tracking, and rate limiting.

## Features

- **Email Templates** - Create and manage reusable email templates with variable support
- **Campaigns** - Organize email sends into campaigns with statistics
- **Open Tracking** - Track when recipients open emails via tracking pixel
- **Click Tracking** - Track link clicks with automatic URL wrapping
- **Rate Limiting** - Built-in rate limiting (configurable, default 10 emails/hour)
- **Queue Support** - Send emails via Laravel queues with random delays
- **Bounce Detection** - Automatic detection of bounced/invalid emails
- **SMTP Configuration** - Configure SMTP settings from admin panel
- **Admin Dashboard** - Full admin interface for managing everything

## Requirements

- PHP 8.1+
- Laravel 8.x, 9.x, 10.x, or 11.x
- Redis (recommended for queues)

## Installation

```bash
composer require dinara/email-marketing
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=email-marketing-config
```

Run migrations:

```bash
php artisan migrate
```

Optionally publish views for customization:

```bash
php artisan vendor:publish --tag=email-marketing-views
```

## Configuration

### Required Environment Variables

Add to your `.env` file:

```env
# Required: Your lead/recipient model
EMAIL_MARKETING_LEAD_MODEL=App\Models\Lead

# Optional: Model for storing SMTP settings (null = use .env SMTP)
EMAIL_MARKETING_COMPANY_MODEL=App\Models\Company

# Optional: Model for logo images (null = no logo)
EMAIL_MARKETING_IMAGES_MODEL=App\Models\Images

# Optional: Database connection for leads (null = default)
EMAIL_MARKETING_LEAD_CONNECTION=null

# Optional: Admin panel customization
EMAIL_MARKETING_LAYOUT=layouts.admin
EMAIL_MARKETING_ROUTE_PREFIX=admin/email-marketing

# Optional: Rate limiting
EMAIL_MARKETING_RATE_LIMIT=10
EMAIL_MARKETING_BASE_DELAY=360
EMAIL_MARKETING_RANDOM_DELAY=120
```

### Config File

The published `config/email-marketing.php`:

```php
return [
    // Rate limiting: max emails per hour
    'rate_limit' => env('EMAIL_MARKETING_RATE_LIMIT', 10),

    // Base delay between emails (seconds)
    'base_delay' => env('EMAIL_MARKETING_BASE_DELAY', 360),

    // Random delay range (seconds)
    'random_delay' => env('EMAIL_MARKETING_RANDOM_DELAY', 120),

    // Lead/recipient model class (REQUIRED)
    'lead_model' => env('EMAIL_MARKETING_LEAD_MODEL', null),

    // Company/Settings model for SMTP storage (optional)
    'company_model' => env('EMAIL_MARKETING_COMPANY_MODEL', null),

    // Images model for logo (optional)
    'images_model' => env('EMAIL_MARKETING_IMAGES_MODEL', null),

    // Database connection for leads (null = default)
    'lead_connection' => env('EMAIL_MARKETING_LEAD_CONNECTION', null),

    // Admin route prefix
    'route_prefix' => env('EMAIL_MARKETING_ROUTE_PREFIX', 'admin/email-marketing'),

    // Admin middleware
    'middleware' => ['web', 'auth'],

    // Blade layout to extend
    'layout' => env('EMAIL_MARKETING_LAYOUT', 'admin.layout'),
];
```

## Usage

### Access Admin Panel

Navigate to `/admin/email-marketing` (or your configured prefix).

### Available Routes

| Route | Description |
|-------|-------------|
| `email-marketing.index` | Dashboard |
| `email-marketing.smtp` | SMTP Settings |
| `email-marketing.templates` | Template List |
| `email-marketing.templates.create` | Create Template |
| `email-marketing.campaigns` | Campaign List |
| `email-marketing.campaigns.create` | Create Campaign |

### Template Variables

Templates support the following variables:

| Variable | Description |
|----------|-------------|
| `{{hotel_name}}` | Company/recipient name |
| `{{contact_name}}` | Contact person name |
| `{{contact_email}}` | Recipient email |
| `{{current_date}}` | Current date |
| `{{sender_name}}` | Sender name |
| `{{sender_company}}` | Sender company |
| `{{logo_url}}` | Company logo URL |
| `{{site_url}}` | Website URL |
| `{{site_name}}` | Website name |

### Running Queue Worker

For sending emails via queue:

```bash
php artisan queue:work
```

Or use Laravel Horizon for Redis queues:

```bash
php artisan horizon
```

### Programmatic Usage

```php
use Dinara\EmailMarketing\Models\EmailTemplate;
use Dinara\EmailMarketing\Models\EmailCampaign;
use Dinara\EmailMarketing\Services\EmailCampaignService;

// Create a template
$template = EmailTemplate::create([
    'name' => 'Welcome Email',
    'subject' => 'Welcome {{contact_name}}!',
    'body_html' => '<h1>Hello {{contact_name}}</h1><p>Welcome to our service!</p>',
    'is_active' => true,
]);

// Create and start a campaign
$service = app(EmailCampaignService::class);
$campaign = $service->createCampaign(
    name: 'Welcome Campaign',
    templateId: $template->id,
    recipientIds: [1, 2, 3] // Lead IDs
);

$service->startCampaign($campaign);
```

## Tracking

### Open Tracking

Emails automatically include a 1x1 transparent tracking pixel. Opens are recorded with:
- Timestamp
- IP address
- User agent
- Open count

### Click Tracking

All links in emails are automatically wrapped with tracking URLs. Clicks record:
- Original URL
- Timestamp
- IP address
- User agent

### Bounce Detection

The package automatically detects bounced emails based on SMTP error codes (550, 551, 552, 553, 554) and common error messages. Bounced recipients are marked with `email_invalid = true`.

## Customization

### Custom Lead Model

Create your own lead model and configure it:

```php
// config/email-marketing.php
'lead_model' => App\Models\Contact::class,
```

Your model should have at least:
- `id`
- `email`
- `company_name` or `name`
- `manager_name` (optional, for contact person)

### Custom Views

Publish and modify views:

```bash
php artisan vendor:publish --tag=email-marketing-views
```

Views will be copied to `resources/views/vendor/email-marketing/`.

### Custom Layout

Set your admin layout in config:

```php
'layout' => 'admin.layout',
```

## Events

The package dispatches events you can listen to:

- `EmailSent` - When an email is successfully sent
- `EmailOpened` - When a tracking pixel is loaded
- `EmailClicked` - When a tracked link is clicked
- `EmailBounced` - When an email bounces

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please submit a Pull Request.

## Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/dinara/email-marketing/issues).
