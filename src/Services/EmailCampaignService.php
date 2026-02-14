<?php

namespace Dinara\EmailMarketing\Services;

use Dinara\EmailMarketing\Models\EmailCampaign;
use Dinara\EmailMarketing\Models\EmailSend;
use Dinara\EmailMarketing\Models\EmailTemplate;
use Dinara\EmailMarketing\Models\EmailClick;
use Dinara\EmailMarketing\Jobs\SendCampaignEmail;
use Dinara\EmailMarketing\Mail\CampaignEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailCampaignService
{
    protected SmtpConfigService $smtpConfig;

    public function __construct(SmtpConfigService $smtpConfig)
    {
        $this->smtpConfig = $smtpConfig;
    }

    /**
     * Get the Lead model class from config
     */
    protected function getLeadModel(): ?string
    {
        return config('email-marketing.lead_model');
    }

    /**
     * Get the Images model class from config
     */
    protected function getImagesModel(): ?string
    {
        return config('email-marketing.images_model');
    }

    /**
     * Create a new campaign
     */
    public function createCampaign(string $name, int $templateId, array $hotelIds): EmailCampaign
    {
        $campaign = EmailCampaign::create([
            'name' => $name,
            'template_id' => $templateId,
            'status' => EmailCampaign::STATUS_DRAFT,
            'total_recipients' => count($hotelIds),
        ]);

        $leadModel = $this->getLeadModel();

        if (!$leadModel || !class_exists($leadModel)) {
            return $campaign;
        }

        // Create email sends for each lead
        foreach ($hotelIds as $leadId) {
            $lead = $leadModel::find($leadId);
            if ($lead && $lead->email) {
                EmailSend::create([
                    'campaign_id' => $campaign->id,
                    'hotel_id' => $leadId,
                    'email' => $lead->email,
                    'recipient_name' => $lead->manager_name ?? $lead->company_name ?? '',
                    'status' => EmailSend::STATUS_PENDING,
                ]);
            }
        }

        return $campaign;
    }

    /**
     * Send a single email
     */
    public function sendEmail(EmailSend $emailSend): bool
    {
        try {
            // Apply SMTP config
            if (!$this->smtpConfig->applyToConfig()) {
                throw new \Exception('SMTP not configured');
            }

            $campaign = $emailSend->campaign;
            $template = $campaign->template;

            $leadModel = $this->getLeadModel();
            $lead = ($leadModel && class_exists($leadModel))
                ? $leadModel::find($emailSend->hotel_id)
                : null;

            // Prepare template variables
            $variables = $lead
                ? $this->prepareVariables($lead)
                : $this->getDummyVariables();

            // Render template
            $rendered = $template->render($variables);

            // Wrap links with tracking URLs
            $htmlWithClickTracking = $this->wrapLinksWithTracking($rendered['html'], $emailSend);

            // Add tracking pixel
            $trackingPixel = '<img src="' . $emailSend->getTrackingUrl() . '" width="1" height="1" style="display:none" alt="" />';
            $htmlWithTracking = $htmlWithClickTracking . $trackingPixel;

            // Send email using Mailable for better deliverability
            $mailable = new CampaignEmail(
                htmlContent: $htmlWithTracking,
                subject: $rendered['subject'],
                trackingId: $emailSend->tracking_id
            );

            Mail::to($emailSend->email, $emailSend->recipient_name)->send($mailable);

            $emailSend->markAsSent();

            return true;
        } catch (\Exception $e) {
            Log::error('Email send failed', [
                'email_send_id' => $emailSend->id,
                'error' => $e->getMessage(),
            ]);

            $emailSend->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Send test email
     */
    public function sendTestEmail(int $templateId, string $testEmail, ?int $hotelId = null): array
    {
        try {
            if (!$this->smtpConfig->applyToConfig()) {
                return ['success' => false, 'message' => 'SMTP not configured'];
            }

            $template = EmailTemplate::findOrFail($templateId);

            // Use lead data or dummy data
            $variables = $this->getDummyVariables();

            if ($hotelId) {
                $leadModel = $this->getLeadModel();
                if ($leadModel && class_exists($leadModel)) {
                    $lead = $leadModel::find($hotelId);
                    if ($lead) {
                        $variables = $this->prepareVariables($lead);
                    }
                }
            }

            $rendered = $template->render($variables);

            // Send using Mailable for better deliverability
            $mailable = new CampaignEmail(
                htmlContent: $rendered['html'],
                subject: '[TEST] ' . $rendered['subject']
            );

            Mail::to($testEmail)->send($mailable);

            return ['success' => true, 'message' => 'Test email sent'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Start sending campaign
     * Max 10 emails per hour with random delays
     */
    public function startCampaign(EmailCampaign $campaign): void
    {
        $campaign->status = EmailCampaign::STATUS_SENDING;
        $campaign->started_at = now();
        $campaign->save();

        $this->dispatchEmailsWithDelay($campaign);
    }

    /**
     * Pause campaign
     */
    public function pauseCampaign(EmailCampaign $campaign): void
    {
        $campaign->status = EmailCampaign::STATUS_PAUSED;
        $campaign->save();
    }

    /**
     * Resume campaign
     */
    public function resumeCampaign(EmailCampaign $campaign): void
    {
        $campaign->status = EmailCampaign::STATUS_SENDING;
        $campaign->save();

        $this->dispatchEmailsWithDelay($campaign);
    }

    /**
     * Dispatch emails with random delays (max 10 per hour)
     */
    protected function dispatchEmailsWithDelay(EmailCampaign $campaign): void
    {
        $delaySeconds = 0;

        // Base delay: 6 minutes (360 sec) = 10 emails/hour
        // Random addition: 0-120 seconds for natural variation
        $baseDelay = config('email-marketing.base_delay', 360);
        $randomDelay = config('email-marketing.random_delay', 120);

        foreach ($campaign->pendingSends()->get() as $index => $emailSend) {
            if ($index > 0) {
                // Add random delay between 5-8 minutes
                $delaySeconds += $baseDelay + rand(0, $randomDelay);
            }

            SendCampaignEmail::dispatch($emailSend)
                ->delay(now()->addSeconds($delaySeconds));
        }
    }

    /**
     * Prepare variables for template
     */
    public function prepareVariables($lead): array
    {
        $smtpSettings = $this->smtpConfig->getSettings();
        $siteUrl = config('app.url');
        $logo = $this->getLogo();

        return [
            'hotel_name' => $lead->company_name ?? '',
            'contact_name' => $lead->manager_name ?? $lead->company_name ?? '',
            'contact_email' => $lead->email ?? '',
            'hotel_city' => '',
            'hotel_address' => $lead->address ?? '',
            'current_date' => now()->format('d.m.Y'),
            'sender_name' => $smtpSettings['SMTP_FROM_NAME'] ?? config('app.name'),
            'sender_company' => $smtpSettings['SMTP_FROM_NAME'] ?? config('app.name'),
            'logo_url' => $logo,
            'site_url' => $siteUrl,
            'site_name' => config('app.name'),
        ];
    }

    /**
     * Get dummy variables for preview
     */
    public function getDummyVariables(): array
    {
        $smtpSettings = $this->smtpConfig->getSettings();
        $siteUrl = config('app.url');
        $logo = $this->getLogo();

        return [
            'hotel_name' => 'Example Hotel',
            'contact_name' => 'John Doe',
            'contact_email' => 'example@hotel.com',
            'hotel_city' => 'Moscow',
            'hotel_address' => '123 Example Street',
            'current_date' => now()->format('d.m.Y'),
            'sender_name' => $smtpSettings['SMTP_FROM_NAME'] ?? config('app.name'),
            'sender_company' => $smtpSettings['SMTP_FROM_NAME'] ?? config('app.name'),
            'logo_url' => $logo,
            'site_url' => $siteUrl,
            'site_name' => config('app.name'),
        ];
    }

    /**
     * Get logo URL from images model if configured
     */
    protected function getLogo(): string
    {
        $imagesModel = $this->getImagesModel();

        if (!$imagesModel || !class_exists($imagesModel)) {
            return '';
        }

        $logoType = config('email-marketing.branding.logo_type', 'company');
        $logo = $imagesModel::where('type', $logoType)->first();

        if (!$logo || !$logo->src) {
            return '';
        }

        $siteUrl = config('app.url');
        return str_starts_with($logo->src, 'http') ? $logo->src : $siteUrl . $logo->src;
    }

    /**
     * Get campaign statistics
     */
    public function getCampaignStats(EmailCampaign $campaign): array
    {
        $clicks = EmailClick::whereIn('email_send_id', $campaign->sends()->pluck('id'))->count();

        return [
            'total' => $campaign->total_recipients,
            'sent' => $campaign->sent_count,
            'opened' => $campaign->opened_count,
            'failed' => $campaign->failed_count,
            'pending' => $campaign->pendingSends()->count(),
            'open_rate' => $campaign->open_rate,
            'clicks' => $clicks,
        ];
    }

    /**
     * Wrap links in HTML with tracking URLs
     */
    protected function wrapLinksWithTracking(string $html, EmailSend $emailSend): string
    {
        $trackingBase = route('email-marketing.click', ['id' => $emailSend->tracking_id]);

        // Find all href attributes and wrap them
        return preg_replace_callback(
            '/<a\s+([^>]*href=["\'])([^"\']+)(["\'][^>]*)>/i',
            function ($matches) use ($trackingBase) {
                $url = $matches[2];

                // Don't track mailto: links or anchors
                if (str_starts_with($url, 'mailto:') || str_starts_with($url, '#')) {
                    return $matches[0];
                }

                $trackedUrl = $trackingBase . '?url=' . urlencode($url);
                return '<a ' . $matches[1] . $trackedUrl . $matches[3] . '>';
            },
            $html
        );
    }
}
