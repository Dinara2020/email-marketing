<?php

namespace Dinara\EmailMarketing\Services;

use Dinara\EmailMarketing\Models\EmailCampaign;
use Dinara\EmailMarketing\Models\EmailSend;
use Dinara\EmailMarketing\Models\EmailTemplate;
use Dinara\EmailMarketing\Models\EmailClick;
use Dinara\EmailMarketing\Models\EmailUnsubscribe;
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
     * Get recipient name from lead using configured field
     */
    protected function getLeadName($lead): string
    {
        $nameField = config('email-marketing.lead_name_field', 'company_name');
        return $lead->{$nameField} ?? $lead->company_name ?? $lead->name ?? '';
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
        $leadModel = $this->getLeadModel();

        if (!$leadModel || !class_exists($leadModel)) {
            return EmailCampaign::create([
                'name' => $name,
                'template_id' => $templateId,
                'status' => EmailCampaign::STATUS_DRAFT,
                'total_recipients' => 0,
            ]);
        }

        // Get unsubscribed emails list
        $unsubscribedEmails = EmailUnsubscribe::pluck('email')->map(fn($e) => strtolower($e))->toArray();

        $validRecipients = 0;
        $recipientData = [];
        $seenEmails = []; // Track unique emails to prevent duplicates

        // Collect valid recipients
        foreach ($hotelIds as $leadId) {
            $lead = $leadModel::find($leadId);
            if ($lead && $lead->email) {
                $email = strtolower(trim($lead->email));

                // Skip duplicate emails
                if (in_array($email, $seenEmails)) {
                    continue;
                }

                // Skip unsubscribed emails
                if (in_array($email, $unsubscribedEmails)) {
                    continue;
                }

                // Skip invalid emails
                if ($this->isEmailInvalid($lead)) {
                    continue;
                }

                $seenEmails[] = $email;
                $recipientData[] = [
                    'lead_id' => $leadId,
                    'email' => $lead->email,
                    'name' => $this->getLeadName($lead),
                ];
                $validRecipients++;
            }
        }

        $campaign = EmailCampaign::create([
            'name' => $name,
            'template_id' => $templateId,
            'status' => EmailCampaign::STATUS_DRAFT,
            'total_recipients' => $validRecipients,
        ]);

        // Create email sends for valid recipients
        foreach ($recipientData as $data) {
            EmailSend::create([
                'campaign_id' => $campaign->id,
                'hotel_id' => $data['lead_id'],
                'email' => $data['email'],
                'recipient_name' => $data['name'],
                'status' => EmailSend::STATUS_PENDING,
            ]);
        }

        return $campaign;
    }

    /**
     * Create campaign for all recipients in lead model
     */
    public function createCampaignForAll(string $name, int $templateId): EmailCampaign
    {
        $leadModel = $this->getLeadModel();

        if (!$leadModel || !class_exists($leadModel)) {
            return EmailCampaign::create([
                'name' => $name,
                'template_id' => $templateId,
                'status' => EmailCampaign::STATUS_DRAFT,
                'total_recipients' => 0,
            ]);
        }

        // Get unsubscribed emails list
        $unsubscribedEmails = EmailUnsubscribe::pluck('email')->map(fn($e) => strtolower($e))->toArray();

        // Get primary key for ordering
        $modelInstance = new $leadModel;
        $primaryKey = $modelInstance->getKeyName();

        // Query all leads with valid email
        $query = $leadModel::whereNotNull('email')
            ->where('email', '!=', '');

        // Exclude unsubscribed emails
        if (!empty($unsubscribedEmails)) {
            $query->whereNotIn(\DB::raw('LOWER(email)'), $unsubscribedEmails);
        }

        // Exclude invalid emails if column exists
        if ($this->hasColumn($leadModel, 'email_invalid')) {
            $query->where(function($q) {
                $q->whereNull('email_invalid')->orWhere('email_invalid', false);
            });
        }
        if ($this->hasColumn($leadModel, 'is_email_invalid')) {
            $query->where(function($q) {
                $q->whereNull('is_email_invalid')->orWhere('is_email_invalid', false);
            });
        }
        if ($this->hasColumn($leadModel, 'email_bounced')) {
            $query->where(function($q) {
                $q->whereNull('email_bounced')->orWhere('email_bounced', false);
            });
        }

        $campaign = EmailCampaign::create([
            'name' => $name,
            'template_id' => $templateId,
            'status' => EmailCampaign::STATUS_DRAFT,
            'total_recipients' => 0, // Will update after processing
        ]);

        // Track unique emails to prevent duplicates
        $seenEmails = [];
        $totalCount = 0;

        // Use chunking for large datasets
        $query->orderBy($primaryKey)->chunk(500, function ($leads) use ($campaign, &$seenEmails, &$totalCount, $primaryKey) {
            foreach ($leads as $lead) {
                $email = strtolower(trim($lead->email));

                // Skip duplicate emails
                if (in_array($email, $seenEmails)) {
                    continue;
                }

                $seenEmails[] = $email;
                $totalCount++;

                EmailSend::create([
                    'campaign_id' => $campaign->id,
                    'hotel_id' => $lead->getKey(),
                    'email' => $lead->email,
                    'recipient_name' => $this->getLeadName($lead),
                    'status' => EmailSend::STATUS_PENDING,
                ]);
            }
        });

        // Update total recipients count
        $campaign->update(['total_recipients' => $totalCount]);

        return $campaign;
    }

    /**
     * Create campaign from CSV file
     */
    public function createCampaignFromCsv(string $name, int $templateId, $csvFile): EmailCampaign
    {
        // Parse CSV and extract emails
        $content = file_get_contents($csvFile->getRealPath());

        // Remove BOM if present (UTF-8, UTF-16, UTF-32)
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Convert encoding if needed
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Split by newlines, commas, semicolons, tabs
        $rawEmails = preg_split('/[\r\n,;\t]+/', $content);

        // Clean and validate emails
        $validEmails = [];
        foreach ($rawEmails as $email) {
            $cleaned = $this->cleanEmail($email);
            if ($cleaned && filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = strtolower($cleaned);
            }
        }

        // Remove duplicates
        $validEmails = array_unique($validEmails);

        if (empty($validEmails)) {
            return EmailCampaign::create([
                'name' => $name,
                'template_id' => $templateId,
                'status' => EmailCampaign::STATUS_DRAFT,
                'total_recipients' => 0,
            ]);
        }

        // Get unsubscribed emails
        $unsubscribedEmails = EmailUnsubscribe::pluck('email')->map(fn($e) => strtolower($e))->toArray();

        // Get emails sent in last 3 days
        $recentlySentEmails = EmailSend::where('created_at', '>=', now()->subDays(3))
            ->whereIn('status', [EmailSend::STATUS_SENT, EmailSend::STATUS_OPENED])
            ->pluck('email')
            ->map(fn($e) => strtolower($e))
            ->toArray();

        // Filter emails
        $filteredEmails = array_filter($validEmails, function ($email) use ($unsubscribedEmails, $recentlySentEmails) {
            // Skip unsubscribed
            if (in_array($email, $unsubscribedEmails)) {
                return false;
            }
            // Skip recently sent
            if (in_array($email, $recentlySentEmails)) {
                return false;
            }
            return true;
        });

        $campaign = EmailCampaign::create([
            'name' => $name,
            'template_id' => $templateId,
            'status' => EmailCampaign::STATUS_DRAFT,
            'total_recipients' => count($filteredEmails),
        ]);

        // Create email sends
        foreach ($filteredEmails as $email) {
            EmailSend::create([
                'campaign_id' => $campaign->id,
                'hotel_id' => null,
                'email' => $email,
                'recipient_name' => '',
                'status' => EmailSend::STATUS_PENDING,
            ]);
        }

        return $campaign;
    }

    /**
     * Clean email from spaces and < > symbols
     */
    protected function cleanEmail(string $email): string
    {
        // Remove < and > symbols
        $email = str_replace(['<', '>', '"', "'"], '', $email);

        // Remove all whitespace characters (spaces, tabs, etc)
        $email = preg_replace('/\s+/', '', $email);

        // Remove invisible characters
        $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);

        // Trim
        return strtolower(trim($email));
    }

    /**
     * Check if model table has specific column
     */
    protected function hasColumn(string $model, string $column): bool
    {
        try {
            $table = (new $model)->getTable();
            return \Schema::hasColumn($table, $column);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Send a single email
     */
    public function sendEmail(EmailSend $emailSend): bool
    {
        try {
            // Check if email is unsubscribed
            if (EmailUnsubscribe::isUnsubscribed($emailSend->email)) {
                $emailSend->markAsSkipped('Email is unsubscribed');
                return false;
            }

            // Check if this email was already sent in this campaign (duplicate protection)
            $alreadySent = EmailSend::where('campaign_id', $emailSend->campaign_id)
                ->where('id', '!=', $emailSend->id)
                ->whereRaw('LOWER(email) = ?', [strtolower($emailSend->email)])
                ->whereIn('status', [EmailSend::STATUS_SENT, EmailSend::STATUS_OPENED])
                ->exists();

            if ($alreadySent) {
                $emailSend->markAsSkipped('Duplicate email in campaign');
                return false;
            }

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

            // Check if email is marked as invalid in the lead model
            if ($lead && $this->isEmailInvalid($lead)) {
                $emailSend->markAsSkipped('Email marked as invalid');
                return false;
            }

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

            // Generate unsubscribe URL
            $unsubscribeUrl = EmailUnsubscribe::getUnsubscribeUrl($emailSend->email);

            // Send email using Mailable for better deliverability
            $mailable = new CampaignEmail(
                htmlContent: $htmlWithTracking,
                subject: $rendered['subject'],
                unsubscribeUrl: $unsubscribeUrl,
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
     * Check if lead has invalid email flag
     */
    protected function isEmailInvalid($lead): bool
    {
        // Check common column names for email validity
        if (property_exists($lead, 'email_invalid') || isset($lead->email_invalid)) {
            return (bool) $lead->email_invalid;
        }
        if (property_exists($lead, 'is_email_invalid') || isset($lead->is_email_invalid)) {
            return (bool) $lead->is_email_invalid;
        }
        if (property_exists($lead, 'email_bounced') || isset($lead->email_bounced)) {
            return (bool) $lead->email_bounced;
        }
        return false;
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
     * Dispatch emails with random delays (max 400 per hour)
     */
    protected function dispatchEmailsWithDelay(EmailCampaign $campaign): void
    {
        $delaySeconds = 0;

        // Base delay: 9 seconds = 400 emails/hour
        // Random addition: 0-3 seconds for natural variation
        $baseDelay = config('email-marketing.base_delay', 9);
        $randomDelay = config('email-marketing.random_delay', 3);

        foreach ($campaign->pendingSends()->get() as $index => $emailSend) {
            if ($index > 0) {
                // Add random delay between 9-12 seconds
                $delaySeconds += $baseDelay + rand(0, $randomDelay);
            }

            SendCampaignEmail::dispatch($emailSend)
                ->onQueue(config('email-marketing.queue', 'email-marketing'))
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
            'contact_name' => $this->getLeadName($lead),
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
        $skipped = $campaign->sends()->where('status', EmailSend::STATUS_SKIPPED)->count();

        return [
            'total' => $campaign->total_recipients,
            'sent' => $campaign->sent_count,
            'opened' => $campaign->opened_count,
            'failed' => $campaign->failed_count,
            'skipped' => $skipped,
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
        $trackingBase = $emailSend->getClickTrackingUrl();

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
