<?php

namespace Dinara\EmailMarketing\Jobs;

use Dinara\EmailMarketing\Models\EmailCampaign;
use Dinara\EmailMarketing\Models\EmailSend;
use Dinara\EmailMarketing\Services\EmailCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1; // No auto-retry, manual resend via dashboard
    public $maxExceptions = 1;

    protected EmailSend $emailSend;

    public function __construct(EmailSend $emailSend)
    {
        $this->emailSend = $emailSend;
    }

    public function handle(EmailCampaignService $service): void
    {
        // Refresh model from DB
        $this->emailSend->refresh();

        // Check if campaign is still sending
        $campaign = $this->emailSend->campaign;
        if ($campaign->status !== EmailCampaign::STATUS_SENDING) {
            return;
        }

        // Check if email is still pending
        if (!$this->emailSend->isPending()) {
            return;
        }

        // Check max attempts (strict limit of 2)
        if (($this->emailSend->attempts ?? 0) >= EmailSend::MAX_ATTEMPTS) {
            $this->emailSend->markAsFailed('Превышен лимит попыток (2)');
            return;
        }

        // Increment attempts before sending
        $this->emailSend->increment('attempts');

        // Send the email
        $service->sendEmail($this->emailSend);

        // Update campaign stats
        $campaign->updateStats();

        // Check if campaign is complete
        if ($campaign->pendingSends()->count() === 0) {
            $campaign->status = EmailCampaign::STATUS_COMPLETED;
            $campaign->completed_at = now();
            $campaign->save();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->emailSend->markAsFailed($exception->getMessage());
    }
}
