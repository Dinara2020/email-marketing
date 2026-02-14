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

    public $tries = 3;
    public $backoff = [60, 300, 900]; // Retry after 1min, 5min, 15min

    protected EmailSend $emailSend;

    public function __construct(EmailSend $emailSend)
    {
        $this->emailSend = $emailSend;
    }

    public function handle(EmailCampaignService $service): void
    {
        // Check if campaign is still sending
        $campaign = $this->emailSend->campaign;
        if ($campaign->status !== EmailCampaign::STATUS_SENDING) {
            return;
        }

        // Check if email is still pending
        if (!$this->emailSend->isPending()) {
            return;
        }

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
