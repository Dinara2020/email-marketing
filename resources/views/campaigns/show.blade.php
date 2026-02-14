@extends(config('email-marketing.layout') ?: 'email-marketing::layouts.app')

@section('content')
<div class="campaign-details">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>{{ $campaign->name }}</h1>
            <small class="text-muted">Template: {{ $campaign->template->name ?? 'Deleted' }}</small>
        </div>
        <div>
            <a href="{{ route('email-marketing.campaigns') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- Campaign Status & Actions -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h5 class="mb-1">Campaign Status</h5>
                    @switch($campaign->status)
                        @case('draft')
                            <span class="badge bg-secondary fs-6">Draft</span>
                            @break
                        @case('sending')
                            <span class="badge bg-warning text-dark fs-6">
                                <i class="fas fa-spinner fa-spin"></i> Sending
                            </span>
                            @break
                        @case('completed')
                            <span class="badge bg-success fs-6">Completed</span>
                            @break
                        @case('paused')
                            <span class="badge bg-info fs-6">Paused</span>
                            @break
                    @endswitch
                </div>
                <div class="col-md-8 text-end">
                    @if($campaign->status === 'draft')
                        <button type="button" class="btn btn-success" id="startCampaignBtn">
                            <i class="fas fa-play"></i> Start Campaign
                        </button>
                    @elseif($campaign->status === 'sending')
                        <button type="button" class="btn btn-warning" id="pauseCampaignBtn">
                            <i class="fas fa-pause"></i> Pause
                        </button>
                    @elseif($campaign->status === 'paused')
                        <button type="button" class="btn btn-success" id="resumeCampaignBtn">
                            <i class="fas fa-play"></i> Resume
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary">{{ $stats['total'] }}</h3>
                    <small class="text-muted">Total Recipients</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success">{{ $stats['sent'] }}</h3>
                    <small class="text-muted">Sent</small>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-success" style="width: {{ $stats['total'] > 0 ? ($stats['sent'] / $stats['total']) * 100 : 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info">{{ $stats['opened'] }}</h3>
                    <small class="text-muted">Opened</small>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-info" style="width: {{ $stats['sent'] > 0 ? ($stats['opened'] / $stats['sent']) * 100 : 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="{{ $stats['open_rate'] > 20 ? 'text-success' : ($stats['open_rate'] > 10 ? 'text-warning' : 'text-secondary') }}">
                        {{ $stats['open_rate'] }}%
                    </h3>
                    <small class="text-muted">Open Rate</small>
                </div>
            </div>
        </div>
        <div class="col-md-1">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-purple">{{ $stats['clicks'] ?? 0 }}</h3>
                    <small class="text-muted">Clicks</small>
                </div>
            </div>
        </div>
    </div>

    @if($stats['failed'] > 0 || ($stats['skipped'] ?? 0) > 0)
        <div class="row mb-4">
            @if($stats['failed'] > 0)
                <div class="col-md-6">
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>{{ $stats['failed'] }}</strong> emails failed to deliver
                    </div>
                </div>
            @endif
            @if(($stats['skipped'] ?? 0) > 0)
                <div class="col-md-6">
                    <div class="alert alert-secondary mb-0">
                        <i class="fas fa-ban"></i>
                        <strong>{{ $stats['skipped'] }}</strong> emails skipped (unsubscribed/invalid/duplicate)
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Emails List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Email List</h5>
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary filter-btn active" data-filter="all">
                    All ({{ $stats['total'] }})
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning filter-btn" data-filter="pending">
                    Pending ({{ $stats['pending'] }})
                </button>
                <button type="button" class="btn btn-sm btn-outline-success filter-btn" data-filter="sent">
                    Sent ({{ $stats['sent'] - $stats['opened'] }})
                </button>
                <button type="button" class="btn btn-sm btn-outline-info filter-btn" data-filter="opened">
                    Opened ({{ $stats['opened'] }})
                </button>
                @if($stats['failed'] > 0)
                    <button type="button" class="btn btn-sm btn-outline-danger filter-btn" data-filter="failed">
                        Failed ({{ $stats['failed'] }})
                    </button>
                @endif
                @if(($stats['skipped'] ?? 0) > 0)
                    <button type="button" class="btn btn-sm btn-outline-secondary filter-btn" data-filter="skipped">
                        Skipped ({{ $stats['skipped'] }})
                    </button>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Opened</th>
                        <th>Opens</th>
                        <th>Clicks</th>
                    </tr>
                </thead>
                <tbody id="sendsTable">
                    @foreach($campaign->sends as $send)
                        <tr class="send-row" data-status="{{ $send->status }}">
                            <td>
                                @if($send->lead)
                                    {{ $send->lead->company_name ?? $send->recipient_name }}
                                @else
                                    <span class="text-muted">{{ $send->recipient_name }}</span>
                                @endif
                            </td>
                            <td>{{ $send->email }}</td>
                            <td>
                                @switch($send->status)
                                    @case('pending')
                                        <span class="badge bg-warning text-dark">Pending</span>
                                        @break
                                    @case('sent')
                                        <span class="badge bg-success">Sent</span>
                                        @break
                                    @case('opened')
                                        <span class="badge bg-info">Opened</span>
                                        @break
                                    @case('failed')
                                        <span class="badge bg-danger" title="{{ $send->error_message }}">
                                            Failed
                                        </span>
                                        @break
                                    @case('bounced')
                                        <span class="badge bg-dark" title="{{ $send->error_message }}">
                                            Bounced
                                        </span>
                                        @break
                                    @case('skipped')
                                        <span class="badge bg-secondary" title="{{ $send->error_message }}">
                                            Skipped
                                        </span>
                                        @break
                                @endswitch
                            </td>
                            <td>{{ $send->sent_at ? $send->sent_at->format('d.m.Y H:i') : '-' }}</td>
                            <td>{{ $send->opened_at ? $send->opened_at->format('d.m.Y H:i') : '-' }}</td>
                            <td>
                                @if($send->open_count > 0)
                                    <span class="badge bg-light text-dark">{{ $send->open_count }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($send->clicks_count > 0)
                                    <span class="badge bg-purple text-white">{{ $send->clicks_count }}</span>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Click Statistics -->
    @if(($stats['clicks'] ?? 0) > 0)
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Clicked URLs</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th class="text-center" style="width: 100px;">Clicks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $clicksByUrl = \Dinara\EmailMarketing\Models\EmailClick::whereIn('email_send_id', $campaign->sends()->pluck('id'))
                                ->selectRaw('url, COUNT(*) as clicks_count')
                                ->groupBy('url')
                                ->orderByDesc('clicks_count')
                                ->get();
                        @endphp
                        @foreach($clicksByUrl as $click)
                            <tr>
                                <td class="text-truncate" style="max-width: 500px;">
                                    <a href="{{ $click->url }}" target="_blank" class="text-primary">
                                        {{ Str::limit($click->url, 80) }}
                                    </a>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-purple">{{ $click->clicks_count }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($campaign->started_at || $campaign->completed_at)
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Timeline</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li><strong>Created:</strong> {{ $campaign->created_at->format('d.m.Y H:i') }}</li>
                    @if($campaign->started_at)
                        <li><strong>Started:</strong> {{ $campaign->started_at->format('d.m.Y H:i') }}</li>
                    @endif
                    @if($campaign->completed_at)
                        <li><strong>Completed:</strong> {{ $campaign->completed_at->format('d.m.Y H:i') }}</li>
                    @endif
                </ul>
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const filter = this.dataset.filter;
            document.querySelectorAll('.send-row').forEach(row => {
                if (filter === 'all' || row.dataset.status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });

    // Start campaign
    const startBtn = document.getElementById('startCampaignBtn');
    if (startBtn) {
        startBtn.addEventListener('click', function() {
            if (!confirm('Start this campaign? Emails will begin sending.')) return;

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';

            fetch('{{ route("email-marketing.campaigns.start", $campaign) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-play"></i> Start Campaign';
                }
            });
        });
    }

    // Pause campaign
    const pauseBtn = document.getElementById('pauseCampaignBtn');
    if (pauseBtn) {
        pauseBtn.addEventListener('click', function() {
            this.disabled = true;

            fetch('{{ route("email-marketing.campaigns.pause", $campaign) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                    this.disabled = false;
                }
            });
        });
    }

    // Resume campaign
    const resumeBtn = document.getElementById('resumeCampaignBtn');
    if (resumeBtn) {
        resumeBtn.addEventListener('click', function() {
            this.disabled = true;

            fetch('{{ route("email-marketing.campaigns.resume", $campaign) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                    this.disabled = false;
                }
            });
        });
    }

    // Auto-refresh if campaign is sending
    @if($campaign->status === 'sending')
        setInterval(() => {
            location.reload();
        }, 30000); // Refresh every 30 seconds
    @endif
});
</script>

<style>
.filter-btn.active {
    font-weight: bold;
}
.text-purple, .bg-purple {
    color: #6f42c1;
}
.bg-purple {
    background-color: #6f42c1 !important;
    color: #fff !important;
}
</style>
@endsection
