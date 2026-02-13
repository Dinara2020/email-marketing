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
}
