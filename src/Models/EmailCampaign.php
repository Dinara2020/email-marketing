<?php

namespace Dinara\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'template_id',
        'status',
        'total_recipients',
        'sent_count',
        'opened_count',
        'failed_count',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SENDING = 'sending';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('orderByIdDesc', function ($query) {
            $query->orderBy('id', 'desc');
        });
    }

    public function template()
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function sends()
    {
        return $this->hasMany(EmailSend::class, 'campaign_id');
    }

    public function pendingSends()
    {
        return $this->sends()->where('status', EmailSend::STATUS_PENDING);
    }

    public function sentSends()
    {
        return $this->sends()->where('status', EmailSend::STATUS_SENT);
    }

    public function openedSends()
    {
        return $this->sends()->where('status', EmailSend::STATUS_OPENED);
    }

    public function failedSends()
    {
        return $this->sends()->whereIn('status', [EmailSend::STATUS_FAILED, EmailSend::STATUS_BOUNCED]);
    }

    public function getOpenRateAttribute(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        return round(($this->opened_count / $this->sent_count) * 100, 2);
    }

    public function updateStats(): void
    {
        $this->sent_count = $this->sends()->whereIn('status', [
            EmailSend::STATUS_SENT,
            EmailSend::STATUS_OPENED
        ])->count();

        $this->opened_count = $this->sends()->where('status', EmailSend::STATUS_OPENED)->count();
        $this->failed_count = $this->sends()->whereIn('status', [
            EmailSend::STATUS_FAILED,
            EmailSend::STATUS_BOUNCED
        ])->count();
        $this->save();
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSending(): bool
    {
        return $this->status === self::STATUS_SENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
