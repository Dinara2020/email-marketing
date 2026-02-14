<?php

namespace Dinara\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SmtpSetting extends Model
{
    protected $table = 'email_smtp_settings';

    protected $fillable = [
        'tenant_id',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Encrypt password when setting
     */
    public function setPasswordAttribute($value): void
    {
        if ($value) {
            $this->attributes['password'] = Crypt::encryptString($value);
        } else {
            $this->attributes['password'] = null;
        }
    }

    /**
     * Decrypt password when getting
     */
    public function getPasswordAttribute($value): ?string
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value; // Return as-is if decryption fails
            }
        }
        return null;
    }

    /**
     * Get settings for a specific tenant (or global if tenant_id is null)
     */
    public static function forTenant(?string $tenantId = null): ?self
    {
        return static::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get or create settings for a tenant
     */
    public static function getOrCreateForTenant(?string $tenantId = null): self
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'port' => 587,
                'encryption' => 'tls',
                'is_active' => true,
            ]
        );
    }

    /**
     * Check if settings are configured (has required fields)
     */
    public function isConfigured(): bool
    {
        return !empty($this->host) && !empty($this->from_address);
    }

    /**
     * Convert to array format compatible with SmtpConfigService
     */
    public function toSettingsArray(): array
    {
        return [
            'SMTP_HOST' => $this->host,
            'SMTP_PORT' => $this->port,
            'SMTP_USERNAME' => $this->username,
            'SMTP_PASSWORD' => $this->password,
            'SMTP_ENCRYPTION' => $this->encryption,
            'SMTP_FROM_ADDRESS' => $this->from_address,
            'SMTP_FROM_NAME' => $this->from_name,
        ];
    }

    /**
     * Update from settings array
     */
    public function updateFromArray(array $data): bool
    {
        return $this->update([
            'host' => $data['SMTP_HOST'] ?? $this->host,
            'port' => $data['SMTP_PORT'] ?? $this->port,
            'username' => $data['SMTP_USERNAME'] ?? $this->username,
            'password' => $data['SMTP_PASSWORD'] ?? $this->password,
            'encryption' => $data['SMTP_ENCRYPTION'] ?? $this->encryption,
            'from_address' => $data['SMTP_FROM_ADDRESS'] ?? $this->from_address,
            'from_name' => $data['SMTP_FROM_NAME'] ?? $this->from_name,
        ]);
    }
}
