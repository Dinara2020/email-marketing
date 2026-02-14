<?php

namespace Dinara\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Model;

class EmailUnsubscribe extends Model
{
    protected $table = 'email_unsubscribes';

    protected $fillable = [
        'email',
        'tenant_id',
        'reason',
        'ip',
        'user_agent',
    ];

    /**
     * Check if email is unsubscribed
     */
    public static function isUnsubscribed(string $email, ?string $tenantId = null): bool
    {
        return static::where('email', strtolower($email))
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /**
     * Unsubscribe an email
     */
    public static function unsubscribe(
        string $email,
        ?string $tenantId = null,
        ?string $reason = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): self {
        return static::firstOrCreate(
            [
                'email' => strtolower($email),
                'tenant_id' => $tenantId,
            ],
            [
                'reason' => $reason,
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]
        );
    }

    /**
     * Resubscribe an email (remove from unsubscribe list)
     */
    public static function resubscribe(string $email, ?string $tenantId = null): bool
    {
        return static::where('email', strtolower($email))
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * Generate unsubscribe token for email
     */
    public static function generateToken(string $email, ?string $tenantId = null): string
    {
        $data = $email . '|' . ($tenantId ?? '') . '|' . config('app.key');
        return base64_encode(hash_hmac('sha256', $data, config('app.key'), true));
    }

    /**
     * Verify unsubscribe token
     */
    public static function verifyToken(string $email, string $token, ?string $tenantId = null): bool
    {
        $expectedToken = static::generateToken($email, $tenantId);
        return hash_equals($expectedToken, $token);
    }

    /**
     * Generate full unsubscribe URL
     */
    public static function getUnsubscribeUrl(string $email, ?string $tenantId = null): string
    {
        $token = static::generateToken($email, $tenantId);

        $publicUrl = config('email-marketing.public_url');

        if ($publicUrl) {
            // Use custom public URL
            return rtrim($publicUrl, '/') . '/email/unsubscribe?' . http_build_query([
                'email' => base64_encode($email),
                'token' => $token,
            ]);
        }

        return route('email-marketing.unsubscribe', [
            'email' => base64_encode($email),
            'token' => $token,
        ]);
    }
}
