# Laravel Email Marketing

A comprehensive email marketing package for Laravel with campaigns, templates, tracking, and rate limiting. **SaaS-ready with multi-tenancy support.**

## Features

- **Email Templates** - Create and manage reusable email templates with variable support
- **Campaigns** - Organize email sends into campaigns with statistics
- **Open Tracking** - Track when recipients open emails via tracking pixel
- **Click Tracking** - Track link clicks with automatic URL wrapping
- **Rate Limiting** - Built-in rate limiting (configurable, default 10 emails/hour)
- **Queue Support** - Send emails via Laravel queues with random delays
- **Bounce Detection** - Automatic detection of bounced/invalid emails
- **SMTP Configuration** - Configure SMTP settings from admin panel (stored in database)
- **Multi-tenancy** - SaaS-ready with per-tenant SMTP settings
- **Admin Dashboard** - Full admin interface for managing everything
- **Standalone Layout** - Works out of the box with built-in Bootstrap 5 admin panel

## Requirements

- PHP 8.1+
- Laravel 8.x, 9.x, 10.x, or 11.x
- Redis (recommended for queues)

## Installation

```bash
composer require dinara/email-marketing
```

Run migrations:

```bash
php artisan migrate
```

Optionally publish configuration:

```bash
php artisan vendor:publish --tag=email-marketing-config
```

Optionally publish views for customization:

```bash
php artisan vendor:publish --tag=email-marketing-views
```

## Configuration

### Minimal Setup

Add to your `.env` file:

```env
# Required: Your lead/recipient model
EMAIL_MARKETING_LEAD_MODEL=App\Models\Lead
```

That's it! The package works out of the box with:
- Built-in Bootstrap 5 admin panel
- SMTP settings stored in database (configurable from admin panel)
- Default rate limiting (10 emails/hour)

### Full Configuration

```env
# Required: Your lead/recipient model
EMAIL_MARKETING_LEAD_MODEL=App\Models\Lead

# Optional: Database connection for leads (null = default)
EMAIL_MARKETING_LEAD_CONNECTION=null

# Optional: Model for logo images (must have: type, src columns)
EMAIL_MARKETING_IMAGES_MODEL=App\Models\Images

# Optional: Use your own layout (null = use package's built-in layout)
EMAIL_MARKETING_LAYOUT=admin.layout

# Optional: Admin panel route prefix
EMAIL_MARKETING_ROUTE_PREFIX=admin/email-marketing

# Optional: Rate limiting
EMAIL_MARKETING_RATE_LIMIT=10
EMAIL_MARKETING_BASE_DELAY=360
EMAIL_MARKETING_RANDOM_DELAY=120
```

### Multi-tenancy (SaaS)

For SaaS applications with multiple tenants, configure a tenant resolver in `config/email-marketing.php`:

```php
'tenant_resolver' => fn() => auth()->user()?->tenant_id,
```

Each tenant will have their own SMTP settings stored in the `email_smtp_settings` table.

Example with Spatie Laravel Multitenancy:
```php
'tenant_resolver' => fn() => app('currentTenant')?->id,
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
use Dinara\EmailMarketing\Services\SmtpConfigService;

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

// For SaaS: Set tenant before operations
$smtpService = app(SmtpConfigService::class);
$smtpService->setTenant($tenantId);
$settings = $smtpService->getSettings();
```

## Database Tables

The package creates the following tables:

- `email_templates` - Email templates with HTML content
- `email_campaigns` - Campaign metadata and status
- `email_sends` - Individual email sends with tracking
- `email_clicks` - Click tracking data
- `email_smtp_settings` - SMTP settings (supports multi-tenancy)

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

Your lead model should have at least:
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

Set your admin layout in `.env`:

```env
EMAIL_MARKETING_LAYOUT=admin.layout
```

Your layout should have a `content` section:
```blade
@yield('content')
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
