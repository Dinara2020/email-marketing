<?php

namespace Dinara\EmailMarketing\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SmtpConfigService
{
    /**
     * SMTP setting keys in company table
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
     * Get the Company model class from config or use default
     */
    protected function getCompanyModel(): string
    {
        return config('email-marketing.company_model', 'App\\Models\\Company');
    }

    /**
     * Get SMTP settings from database
     */
    public function getSettings(): array
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
     * Save SMTP settings to database
     */
    public function saveSettings(array $data): void
    {
        $companyModel = $this->getCompanyModel();

        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $companyModel::updateOrCreate(
                    ['key' => $key],
                    ['value' => $data[$key] ?? '']
                );
            }
        }
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
}
