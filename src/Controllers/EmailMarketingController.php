<?php

namespace Dinara\EmailMarketing\Controllers;

use Illuminate\Routing\Controller;
use Dinara\EmailMarketing\Models\EmailCampaign;
use Dinara\EmailMarketing\Models\EmailSend;
use Dinara\EmailMarketing\Models\EmailTemplate;
use Dinara\EmailMarketing\Models\EmailClick;
use Dinara\EmailMarketing\Services\EmailCampaignService;
use Dinara\EmailMarketing\Services\SmtpConfigService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmailMarketingController extends Controller
{
    protected SmtpConfigService $smtpConfig;
    protected EmailCampaignService $campaignService;

    public function __construct(SmtpConfigService $smtpConfig, EmailCampaignService $campaignService)
    {
        $this->smtpConfig = $smtpConfig;
        $this->campaignService = $campaignService;
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

        return redirect()->route('email-marketing.campaigns.show', $campaign)
            ->with('success', 'Campaign created');
    }

    public function showCampaign(EmailCampaign $campaign)
    {
        $campaign->load(['template', 'sends' => function ($query) {
            $query->withCount('clicks')->with('lead');
        }]);
        $stats = $this->campaignService->getCampaignStats($campaign);

        return view('email-marketing::campaigns.show', compact('campaign', 'stats'));
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

    // ==================== Lead Search ====================

    public function searchHotels(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $leadModel = $this->getLeadModel();

        $leads = $leadModel::where(function ($q) use ($query) {
                $q->where('company_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('address', 'like', "%{$query}%");
            })
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select('id', 'company_name as name', 'email', 'address as city')
            ->limit(20)
            ->get();

        return response()->json($leads);
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
}
