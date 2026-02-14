<?php

namespace Dinara\EmailMarketing;

use Illuminate\Support\ServiceProvider;

class EmailMarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/email-marketing.php', 'email-marketing');

        $this->app->singleton(Services\SmtpConfigService::class);
        $this->app->singleton(Services\EmailCampaignService::class);
        $this->app->singleton(Services\ImageUploadService::class);
    }

    public function boot(): void
    {
        // Routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/email-marketing.php');

        // Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'email-marketing');

        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish config
        $this->publishes([
            __DIR__ . '/../config/email-marketing.php' => config_path('email-marketing.php'),
        ], 'email-marketing-config');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/email-marketing'),
        ], 'email-marketing-views');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'email-marketing-migrations');
    }

    /**
     * Validate that required configuration is set
     */
    public static function validateConfig(): array
    {
        $errors = [];

        if (!config('email-marketing.lead_model')) {
            $errors[] = 'email-marketing.lead_model is not configured. Set EMAIL_MARKETING_LEAD_MODEL in .env';
        } elseif (!class_exists(config('email-marketing.lead_model'))) {
            $errors[] = 'Lead model class does not exist: ' . config('email-marketing.lead_model');
        }

        return $errors;
    }

    /**
     * Get the lead model class
     */
    public static function getLeadModel(): ?string
    {
        return config('email-marketing.lead_model');
    }

    /**
     * Get the company model class
     */
    public static function getCompanyModel(): ?string
    {
        return config('email-marketing.company_model');
    }

    /**
     * Get the images model class
     */
    public static function getImagesModel(): ?string
    {
        return config('email-marketing.images_model');
    }
}
