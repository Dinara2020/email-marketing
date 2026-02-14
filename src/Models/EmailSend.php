<?php

namespace Dinara\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'hotel_id',
        'email',
        'recipient_name',
        'status',
        'attempts',
        'tracking_id',
        'sent_at',
        'opened_at',
        'open_count',
        'opened_ip',
        'opened_user_agent',
        'error_message',
    ];

    const MAX_ATTEMPTS = 2;

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_OPENED = 'opened';
    const STATUS_FAILED = 'failed';
    const STATUS_BOUNCED = 'bounced';
    const STATUS_SKIPPED = 'skipped';

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('orderByIdDesc', function ($query) {
            $query->orderBy('id', 'desc');
        });

        static::creating(function ($model) {
            if (empty($model->tracking_id)) {
                $model->tracking_id = Str::uuid()->toString();
            }
        });
    }

    public function campaign()
    {
        return $this->belongsTo(EmailCampaign::class, 'campaign_id');
    }

    /**
     * Get the lead model dynamically from config
     */
    public function lead()
    {
        $leadModel = config('email-marketing.lead_model', 'App\\Models\\MainCrm\\Lead');
        return $this->belongsTo($leadModel, 'hotel_id');
    }

    // Alias for backward compatibility
    public function hotel()
    {
        return $this->lead();
    }

    public function clicks()
    {
        return $this->hasMany(EmailClick::class);
    }

    /**
     * Mark email as sent
     */
    public function markAsSent(): void
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = now();
        $this->save();
    }

    /**
     * Mark email as opened
     */
    public function markAsOpened(?string $ip = null, ?string $userAgent = null): void
    {
        $this->open_count++;

        if ($this->status !== self::STATUS_OPENED) {
            $this->status = self::STATUS_OPENED;
            $this->opened_at = now();
            $this->opened_ip = $ip;
            $this->opened_user_agent = $userAgent;
        }

        $this->save();

        // Update campaign stats
        $this->campaign->updateStats();
    }

    /**
     * Mark email as failed (with bounce detection)
     */
    public function markAsFailed(string $error): void
    {
        // Detect bounce errors (invalid/non-existent email)
        $bouncePatterns = [
            '550',           // Mailbox not found
            '551',           // User not local
            '552',           // Mailbox full
            '553',           // Mailbox name invalid
            '554',           // Transaction failed
            'does not exist',
            'user unknown',
            'no such user',
            'mailbox not found',
            'recipient rejected',
            'address rejected',
            'invalid recipient',
            'undeliverable',
        ];

        $isBounce = false;
        $errorLower = strtolower($error);
        foreach ($bouncePatterns as $pattern) {
            if (str_contains($errorLower, strtolower($pattern))) {
                $isBounce = true;
                break;
            }
        }

        $this->status = $isBounce ? self::STATUS_BOUNCED : self::STATUS_FAILED;
        $this->error_message = $error;
        $this->save();

        // Mark lead email as invalid if bounced
        if ($isBounce && $this->lead) {
            $this->lead->update([
                'email_invalid' => true,
                'email_bounced_at' => now(),
            ]);
        }
    }

    /**
     * Check if can retry sending
     */
    public function canRetry(): bool
    {
        return ($this->attempts ?? 0) < self::MAX_ATTEMPTS
            && $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark email as bounced
     */
    public function markAsBounced(string $error): void
    {
        $this->status = self::STATUS_BOUNCED;
        $this->error_message = $error;
        $this->save();

        // Mark lead email as invalid
        if ($this->lead) {
            $this->lead->update([
                'email_invalid' => true,
                'email_bounced_at' => now(),
            ]);
        }
    }

    /**
     * Mark email as skipped (unsubscribed or invalid)
     */
    public function markAsSkipped(string $reason): void
    {
        $this->status = self::STATUS_SKIPPED;
        $this->error_message = $reason;
        $this->save();
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function isBounced(): bool
    {
        return $this->status === self::STATUS_BOUNCED;
    }

    /**
     * Get tracking pixel URL
     */
    public function getTrackingUrl(): string
    {
        $publicUrl = config('email-marketing.public_url');

        if ($publicUrl) {
            return rtrim($publicUrl, '/') . '/email/track/' . $this->tracking_id;
        }

        return route('email-marketing.track', ['id' => $this->tracking_id]);
    }

    /**
     * Get click tracking base URL
     */
    public function getClickTrackingUrl(): string
    {
        $publicUrl = config('email-marketing.public_url');

        if ($publicUrl) {
            return rtrim($publicUrl, '/') . '/email/click/' . $this->tracking_id;
        }

        return route('email-marketing.click', ['id' => $this->tracking_id]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_OPENED]);
    }

    public function isOpened(): bool
    {
        return $this->status === self::STATUS_OPENED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
