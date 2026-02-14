<?php

namespace Dinara\EmailMarketing\Services;

use Illuminate\Support\Facades\Config;

class SmtpConfigService
{
    /**
     * SMTP setting keys
     */
    const KEYS = [
        'SMTP_HOST',
        'SMTP_PORT',
        'SMTP_USERNAME',
        'SMTP_PASSWORD',
        'SMTP_ENCRYPTION',
        'SMTP_FROM_ADDRESS',
        'SMTP_FROM_NAME',
    ];

    /**
     * Check if we have a company model configured for storing SMTP settings
     */
    protected function hasCompanyModel(): bool
    {
        $model = config('email-marketing.company_model');
        return $model && class_exists($model);
    }

    /**
     * Get the Company model class from config
     */
    protected function getCompanyModel(): ?string
    {
        return config('email-marketing.company_model');
    }

    /**
     * Get SMTP settings - from database if company_model is configured, otherwise from .env
     */
    public function getSettings(): array
    {
        if ($this->hasCompanyModel()) {
            return $this->getSettingsFromDatabase();
        }

        return $this->getSettingsFromEnv();
    }

    /**
     * Get SMTP settings from database
     */
    protected function getSettingsFromDatabase(): array
    {
        $settings = [];
        $companyModel = $this->getCompanyModel();

        foreach (self::KEYS as $key) {
            $record = $companyModel::where('key', $key)->first();
            $settings[$key] = $record ? $record->value : null;
        }

        return $settings;
    }

    /**
     * Get SMTP settings from .env/config
     */
    protected function getSettingsFromEnv(): array
    {
        return [
            'SMTP_HOST' => config('mail.mailers.smtp.host'),
            'SMTP_PORT' => config('mail.mailers.smtp.port'),
            'SMTP_USERNAME' => config('mail.mailers.smtp.username'),
            'SMTP_PASSWORD' => config('mail.mailers.smtp.password'),
            'SMTP_ENCRYPTION' => config('mail.mailers.smtp.encryption'),
            'SMTP_FROM_ADDRESS' => config('mail.from.address'),
            'SMTP_FROM_NAME' => config('mail.from.name'),
        ];
    }

    /**
     * Save SMTP settings to database (only works if company_model is configured)
     */
    public function saveSettings(array $data): bool
    {
        if (!$this->hasCompanyModel()) {
            return false;
        }

        $companyModel = $this->getCompanyModel();

        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $companyModel::updateOrCreate(
                    ['key' => $key],
                    ['value' => $data[$key] ?? '']
                );
            }
        }

        return true;
    }

    /**
     * Apply SMTP settings to Laravel mail config
     */
    public function applyToConfig(): bool
    {
        $settings = $this->getSettings();

        if (empty($settings['SMTP_HOST']) || empty($settings['SMTP_FROM_ADDRESS'])) {
            return false;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $settings['SMTP_HOST']);
        Config::set('mail.mailers.smtp.port', $settings['SMTP_PORT'] ?? 587);
        Config::set('mail.mailers.smtp.username', $settings['SMTP_USERNAME']);
        Config::set('mail.mailers.smtp.password', $settings['SMTP_PASSWORD']);
        Config::set('mail.mailers.smtp.encryption', $settings['SMTP_ENCRYPTION'] ?? 'tls');
        Config::set('mail.from.address', $settings['SMTP_FROM_ADDRESS']);
        Config::set('mail.from.name', $settings['SMTP_FROM_NAME'] ?? config('app.name'));

        return true;
    }

    /**
     * Test SMTP connection
     */
    public function testConnection(): array
    {
        try {
            if (!$this->applyToConfig()) {
                return [
                    'success' => false,
                    'message' => 'SMTP settings are not configured',
                ];
            }

            $settings = $this->getSettings();

            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $settings['SMTP_HOST'],
                (int)($settings['SMTP_PORT'] ?? 587),
                $settings['SMTP_ENCRYPTION'] === 'ssl'
            );

            if (!empty($settings['SMTP_USERNAME'])) {
                $transport->setUsername($settings['SMTP_USERNAME']);
                $transport->setPassword($settings['SMTP_PASSWORD'] ?? '');
            }

            $transport->start();
            $transport->stop();

            return [
                'success' => true,
                'message' => 'Connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if SMTP is configured
     */
    public function isConfigured(): bool
    {
        $settings = $this->getSettings();
        return !empty($settings['SMTP_HOST']) && !empty($settings['SMTP_FROM_ADDRESS']);
    }

    /**
     * Check if settings can be edited (only if company_model is configured)
     */
    public function canEditSettings(): bool
    {
        return $this->hasCompanyModel();
    }
}
