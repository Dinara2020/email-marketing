<?php

namespace Dinara\EmailMarketing\Services;

use Dinara\EmailMarketing\Models\SmtpSetting;
use Illuminate\Support\Facades\Config;

class SmtpConfigService
{
    /**
     * Current tenant ID (for SaaS multi-tenancy)
     */
    protected ?string $tenantId = null;

    /**
     * Set tenant ID for multi-tenant SaaS
     */
    public function setTenant(?string $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Get current tenant ID
     */
    public function getTenantId(): ?string
    {
        // Allow override via config/callback
        $resolver = config('email-marketing.tenant_resolver');

        if ($resolver && is_callable($resolver)) {
            return $resolver();
        }

        return $this->tenantId;
    }

    /**
     * Get SMTP settings model for current tenant
     */
    public function getSettingsModel(): SmtpSetting
    {
        return SmtpSetting::getOrCreateForTenant($this->getTenantId());
    }

    /**
     * Get SMTP settings as array
     */
    public function getSettings(): array
    {
        $model = SmtpSetting::forTenant($this->getTenantId());

        if ($model && $model->isConfigured()) {
            return $model->toSettingsArray();
        }

        // Fallback to Laravel mail config if no settings in DB
        return [
            'SMTP_HOST' => config('mail.mailers.smtp.host'),
            'SMTP_PORT' => config('mail.mailers.smtp.port', 587),
            'SMTP_USERNAME' => config('mail.mailers.smtp.username'),
            'SMTP_PASSWORD' => config('mail.mailers.smtp.password'),
            'SMTP_ENCRYPTION' => config('mail.mailers.smtp.encryption', 'tls'),
            'SMTP_FROM_ADDRESS' => config('mail.from.address'),
            'SMTP_FROM_NAME' => config('mail.from.name'),
        ];
    }

    /**
     * Save SMTP settings
     */
    public function saveSettings(array $data): bool
    {
        $model = $this->getSettingsModel();
        return $model->updateFromArray($data);
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
     * Check if there are saved settings in database (not just fallback)
     */
    public function hasSavedSettings(): bool
    {
        $model = SmtpSetting::forTenant($this->getTenantId());
        return $model && $model->isConfigured();
    }

    /**
     * Settings can always be edited (stored in package's own table)
     */
    public function canEditSettings(): bool
    {
        return true;
    }
}
