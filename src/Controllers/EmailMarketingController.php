<?php

namespace Dinara\EmailMarketing\Controllers;

use Illuminate\Routing\Controller;
use Dinara\EmailMarketing\Models\EmailCampaign;
use Dinara\EmailMarketing\Models\EmailSend;
use Dinara\EmailMarketing\Models\EmailTemplate;
use Dinara\EmailMarketing\Models\EmailClick;
use Dinara\EmailMarketing\Models\EmailImage;
use Dinara\EmailMarketing\Models\EmailUnsubscribe;
use Dinara\EmailMarketing\Services\EmailCampaignService;
use Dinara\EmailMarketing\Services\SmtpConfigService;
use Dinara\EmailMarketing\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmailMarketingController extends Controller
{
    protected SmtpConfigService $smtpConfig;
    protected EmailCampaignService $campaignService;
    protected ImageUploadService $imageService;

    public function __construct(
        SmtpConfigService $smtpConfig,
        EmailCampaignService $campaignService,
        ImageUploadService $imageService
    ) {
        $this->smtpConfig = $smtpConfig;
        $this->campaignService = $campaignService;
        $this->imageService = $imageService;
    }

    /**
     * Get the Lead model class from config
     */
    protected function getLeadModel(): ?string
    {
        return config('email-marketing.lead_model');
    }

    /**
     * Check if package is properly configured
     */
    protected function checkConfiguration(): ?string
    {
        if (!$this->getLeadModel()) {
            return 'Email Marketing is not configured. Please set EMAIL_MARKETING_LEAD_MODEL in your .env file.';
        }
        if (!class_exists($this->getLeadModel())) {
            return 'Lead model class does not exist: ' . $this->getLeadModel();
        }
        return null;
    }

    /**
     * Dashboard / Overview
     */
    public function index()
    {
        if ($error = $this->checkConfiguration()) {
            return view('email-marketing::error', ['error' => $error]);
        }

        $stats = [
            'total_campaigns' => EmailCampaign::count(),
            'total_sent' => EmailSend::whereIn('status', ['sent', 'opened'])->count(),
            'total_opened' => EmailSend::where('status', 'opened')->count(),
            'templates_count' => EmailTemplate::count(),
            'unsubscribes_count' => EmailUnsubscribe::count(),
        ];

        $recentCampaigns = EmailCampaign::with('template')
            ->latest()
            ->take(5)
            ->get();

        return view('email-marketing::index', compact('stats', 'recentCampaigns'));
    }

    // ==================== SMTP Settings ====================

    public function smtpSettings()
    {
        $settings = $this->smtpConfig->getSettings();
        return view('email-marketing::smtp-settings', compact('settings'));
    }

    public function saveSmtpSettings(Request $request)
    {
        $request->validate([
            'SMTP_HOST' => 'required|string',
            'SMTP_PORT' => 'required|integer',
            'SMTP_FROM_ADDRESS' => 'required|email',
            'SMTP_FROM_NAME' => 'required|string',
        ]);

        $this->smtpConfig->saveSettings($request->only([
            'SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD',
            'SMTP_ENCRYPTION', 'SMTP_FROM_ADDRESS', 'SMTP_FROM_NAME'
        ]));

        return back()->with('success', 'SMTP settings saved');
    }

    public function testSmtp(): JsonResponse
    {
        $result = $this->smtpConfig->testConnection();
        return response()->json($result);
    }

    // ==================== Templates ====================

    public function templates()
    {
        $templates = EmailTemplate::latest()->paginate(20);
        return view('email-marketing::templates.index', compact('templates'));
    }

    public function createTemplate()
    {
        $variables = EmailTemplate::getAvailableVariables();
        return view('email-marketing::templates.create', compact('variables'));
    }

    public function storeTemplate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
        ]);

        EmailTemplate::create($request->only(['name', 'subject', 'body_html', 'body_text', 'is_active']));

        return redirect()->route('email-marketing.templates')
            ->with('success', 'Template created');
    }

    public function editTemplate(EmailTemplate $template)
    {
        $variables = EmailTemplate::getAvailableVariables();
        return view('email-marketing::templates.edit', compact('template', 'variables'));
    }

    public function updateTemplate(Request $request, EmailTemplate $template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
        ]);

        $template->update($request->only(['name', 'subject', 'body_html', 'body_text', 'is_active']));

        return redirect()->route('email-marketing.templates')
            ->with('success', 'Template updated');
    }

    public function deleteTemplate(EmailTemplate $template)
    {
        if ($template->campaigns()->exists()) {
            return back()->with('error', 'Cannot delete template that is used in campaigns');
        }

        $template->delete();
        return back()->with('success', 'Template deleted');
    }

    public function previewTemplate(Request $request, EmailTemplate $template): JsonResponse
    {
        $hotelId = $request->get('hotel_id');
        $leadModel = $this->getLeadModel();
        $lead = $hotelId ? $leadModel::find($hotelId) : null;

        $variables = $lead
            ? $this->campaignService->prepareVariables($lead)
            : $this->campaignService->getDummyVariables();

        $rendered = $template->render($variables);

        return response()->json($rendered);
    }

    public function sendTestEmail(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'test_email' => 'required|email',
        ]);

        $result = $this->campaignService->sendTestEmail(
            $request->input('template_id'),
            $request->input('test_email'),
            $request->input('hotel_id')
        );

        return response()->json($result);
    }

    // ==================== Campaigns ====================

    public function campaigns()
    {
        $campaigns = EmailCampaign::with('template')
            ->latest()
            ->paginate(20);

        return view('email-marketing::campaigns.index', compact('campaigns'));
    }

    public function createCampaign()
    {
        $templates = EmailTemplate::where('is_active', true)->get();
        return view('email-marketing::campaigns.create', compact('templates'));
    }

    public function storeCampaign(Request $request)
    {
        $sendToAll = $request->boolean('send_to_all');

        if ($sendToAll) {
            $request->validate([
                'name' => 'required|string|max:255',
                'template_id' => 'required|exists:email_templates,id',
            ]);

            $campaign = $this->campaignService->createCampaignForAll(
                $request->input('name'),
                $request->input('template_id')
            );
        } else {
            $request->validate([
                'name' => 'required|string|max:255',
                'template_id' => 'required|exists:email_templates,id',
                'hotel_ids' => 'required|array|min:1',
            ]);

            $campaign = $this->campaignService->createCampaign(
                $request->input('name'),
                $request->input('template_id'),
                $request->input('hotel_ids')
            );
        }

        return redirect()->route('email-marketing.campaigns.show', $campaign)
            ->with('success', 'Campaign created with ' . $campaign->total_recipients . ' recipients');
    }

    public function createCampaignCsv()
    {
        $templates = EmailTemplate::where('is_active', true)->get();
        return view('email-marketing::campaigns.create-csv', compact('templates'));
    }

    public function storeCampaignCsv(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'template_id' => 'required|exists:email_templates,id',
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $campaign = $this->campaignService->createCampaignFromCsv(
            $request->input('name'),
            $request->input('template_id'),
            $request->file('csv_file')
        );

        if ($campaign->total_recipients === 0) {
            return back()->with('error', 'Не найдено валидных email адресов');
        }

        return redirect()->route('email-marketing.campaigns.show', $campaign)
            ->with('success', 'Кампания создана: ' . $campaign->total_recipients . ' получателей');
    }

    public function showCampaign(EmailCampaign $campaign, Request $request)
    {
        $campaign->load('template');

        // Sorting
        $sortBy = $request->get('sort', 'id');
        $sortDir = $request->get('dir', 'desc');

        // Validate sort columns
        $allowedSorts = ['id', 'email', 'status', 'sent_at', 'opened_at', 'open_count'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'id';
        }
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        // Filter by status
        $statusFilter = $request->get('status');

        $sendsQuery = $campaign->sends()
            ->withCount('clicks')
            ->with('lead');

        if ($statusFilter && in_array($statusFilter, ['pending', 'sent', 'opened', 'failed', 'skipped', 'bounced'])) {
            $sendsQuery->where('status', $statusFilter);
        }

        $sends = $sendsQuery->orderBy($sortBy, $sortDir)->paginate(50)->withQueryString();

        $stats = $this->campaignService->getCampaignStats($campaign);

        return view('email-marketing::campaigns.show', compact('campaign', 'sends', 'stats', 'sortBy', 'sortDir', 'statusFilter'));
    }

    public function startCampaign(EmailCampaign $campaign): JsonResponse
    {
        if (!$campaign->isDraft() && !$campaign->status === EmailCampaign::STATUS_PAUSED) {
            return response()->json(['success' => false, 'message' => 'Campaign already started or completed']);
        }

        if (!$this->smtpConfig->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'SMTP not configured']);
        }

        $this->campaignService->startCampaign($campaign);

        return response()->json(['success' => true, 'message' => 'Campaign started']);
    }

    public function pauseCampaign(EmailCampaign $campaign): JsonResponse
    {
        $this->campaignService->pauseCampaign($campaign);
        return response()->json(['success' => true, 'message' => 'Campaign paused']);
    }

    public function resumeCampaign(EmailCampaign $campaign): JsonResponse
    {
        $this->campaignService->resumeCampaign($campaign);
        return response()->json(['success' => true, 'message' => 'Campaign resumed']);
    }

    public function deleteCampaign(EmailCampaign $campaign)
    {
        $campaign->delete();
        return redirect()->route('email-marketing.campaigns')
            ->with('success', 'Campaign deleted');
    }

    /**
     * Resend failed emails in a campaign (max 2 attempts total)
     */
    public function resendFailed(EmailCampaign $campaign): JsonResponse
    {
        // Only resend emails with less than 2 attempts
        $canRetryCount = $campaign->sends()
            ->where('status', 'failed')
            ->where(function ($q) {
                $q->whereNull('attempts')->orWhere('attempts', '<', EmailSend::MAX_ATTEMPTS);
            })
            ->count();

        if ($canRetryCount === 0) {
            $totalFailed = $campaign->sends()->where('status', 'failed')->count();
            if ($totalFailed > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Все {$totalFailed} писем уже отправлялись 2 раза"
                ]);
            }
            return response()->json(['success' => false, 'message' => 'Нет failed писем для переотправки']);
        }

        // Reset only failed emails with attempts < 2
        $campaign->sends()
            ->where('status', 'failed')
            ->where(function ($q) {
                $q->whereNull('attempts')->orWhere('attempts', '<', EmailSend::MAX_ATTEMPTS);
            })
            ->update([
                'status' => 'pending',
                'error_message' => null,
            ]);

        // Update campaign stats
        $campaign->update([
            'status' => EmailCampaign::STATUS_SENDING,
        ]);

        // Restart sending
        $this->campaignService->startCampaign($campaign);

        return response()->json([
            'success' => true,
            'message' => "Переотправка {$canRetryCount} писем"
        ]);
    }

    // ==================== Lead Search ====================

    public function searchHotels(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $leadModel = $this->getLeadModel();

        if (!$leadModel || !class_exists($leadModel)) {
            return response()->json([]);
        }

        $searchFields = config('email-marketing.lead_search_fields', ['email']);
        $nameField = config('email-marketing.lead_name_field', 'email');

        try {
            $modelInstance = new $leadModel;
            $primaryKey = $modelInstance->getKeyName();

            // Search by configured fields (user must configure only existing fields)
            $leads = $leadModel::where(function ($q) use ($query, $searchFields) {
                    foreach ($searchFields as $index => $field) {
                        if ($index === 0) {
                            $q->where($field, 'like', "%{$query}%");
                        } else {
                            $q->orWhere($field, 'like', "%{$query}%");
                        }
                    }
                })
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->limit(20)
                ->get()
                ->map(function ($lead) use ($nameField, $primaryKey) {
                    return [
                        'id' => $lead->{$primaryKey} ?? $lead->getKey(),
                        'name' => $lead->{$nameField} ?? $lead->email ?? '',
                        'email' => $lead->email,
                    ];
                });

            return response()->json($leads);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ==================== Tracking (Public Routes) ====================

    public function trackOpen(string $trackingId)
    {
        $emailSend = EmailSend::where('tracking_id', $trackingId)->first();

        if ($emailSend) {
            $emailSend->markAsOpened(
                request()->ip(),
                request()->userAgent()
            );
        }

        // Return 1x1 transparent GIF
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function trackClick(string $trackingId, Request $request)
    {
        $url = $request->query('url');

        if (!$url) {
            abort(400, 'Missing URL');
        }

        $emailSend = EmailSend::where('tracking_id', $trackingId)->first();

        if ($emailSend) {
            EmailClick::create([
                'email_send_id' => $emailSend->id,
                'url' => $url,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->away($url);
    }

    // ==================== Image Upload ====================

    /**
     * List uploaded images
     */
    public function images(): JsonResponse
    {
        $images = $this->imageService->getImages();

        return response()->json([
            'images' => $images->map(fn($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'name' => $img->original_name,
                'size' => $img->human_size,
                'created_at' => $img->created_at->format('d.m.Y H:i'),
            ]),
        ]);
    }

    /**
     * Upload image for email template
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|max:2048',
        ]);

        $result = $this->imageService->upload($request->file('image'));

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'url' => $result['url'],
            'image' => [
                'id' => $result['image']->id,
                'url' => $result['url'],
                'name' => $result['image']->original_name,
            ],
        ]);
    }

    /**
     * Delete uploaded image
     */
    public function deleteImage(EmailImage $image): JsonResponse
    {
        $deleted = $this->imageService->delete($image);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete this image',
            ], 403);
        }

        return response()->json(['success' => true]);
    }

    // ==================== Unsubscribe ====================

    /**
     * Show unsubscribe page
     */
    public function showUnsubscribe(Request $request)
    {
        $email = $request->query('email');
        $token = $request->query('token');

        if (!$email || !$token) {
            return view('email-marketing::unsubscribe', [
                'error' => 'Invalid unsubscribe link',
                'email' => null,
            ]);
        }

        try {
            $decodedEmail = base64_decode($email);
        } catch (\Exception $e) {
            return view('email-marketing::unsubscribe', [
                'error' => 'Invalid unsubscribe link',
                'email' => null,
            ]);
        }

        if (!EmailUnsubscribe::verifyToken($decodedEmail, $token)) {
            return view('email-marketing::unsubscribe', [
                'error' => 'Invalid or expired unsubscribe link',
                'email' => null,
            ]);
        }

        // Check if already unsubscribed
        if (EmailUnsubscribe::isUnsubscribed($decodedEmail)) {
            return view('email-marketing::unsubscribe', [
                'success' => true,
                'already' => true,
                'email' => $decodedEmail,
            ]);
        }

        return view('email-marketing::unsubscribe', [
            'email' => $decodedEmail,
            'token' => $token,
            'encodedEmail' => $email,
        ]);
    }

    /**
     * Process unsubscribe request
     */
    public function processUnsubscribe(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'token' => 'required',
        ]);

        try {
            $decodedEmail = base64_decode($request->input('email'));
        } catch (\Exception $e) {
            return back()->with('error', 'Invalid request');
        }

        if (!EmailUnsubscribe::verifyToken($decodedEmail, $request->input('token'))) {
            return back()->with('error', 'Invalid or expired link');
        }

        EmailUnsubscribe::unsubscribe(
            $decodedEmail,
            null, // tenant_id
            $request->input('reason'),
            $request->ip(),
            $request->userAgent()
        );

        return view('email-marketing::unsubscribe', [
            'success' => true,
            'email' => $decodedEmail,
        ]);
    }

    // ==================== Unsubscribes Admin ====================

    /**
     * List all unsubscribed emails
     */
    public function unsubscribes()
    {
        $unsubscribes = EmailUnsubscribe::orderBy('created_at', 'desc')->paginate(50);
        return view('email-marketing::unsubscribes.index', compact('unsubscribes'));
    }

    /**
     * Remove email from unsubscribe list (resubscribe)
     */
    public function deleteUnsubscribe(EmailUnsubscribe $unsubscribe)
    {
        $unsubscribe->delete();
        return back()->with('success', 'Email возвращён в рассылку');
    }
}
