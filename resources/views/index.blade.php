@extends(config('email-marketing.layout') ?: 'email-marketing::layouts.app')

@section('content')
<div class="email-marketing">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h1>Email Marketing</h1>
        <div>
            <a href="{{ route('email-marketing.smtp') }}" class="btn btn-outline-secondary me-2">
                <i class="fas fa-cog"></i> SMTP
            </a>
            <a href="{{ route('email-marketing.campaigns.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Campaign
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Campaigns</h5>
                    <h2>{{ $stats['total_campaigns'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Sent</h5>
                    <h2>{{ $stats['total_sent'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Opened</h5>
                    <h2>{{ $stats['total_opened'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5 class="card-title">Templates</h5>
                    <h2>{{ $stats['templates_count'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            @if(Route::has('email-marketing.unsubscribes'))
                <a href="{{ route('email-marketing.unsubscribes') }}" class="text-decoration-none">
            @endif
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Отписки</h5>
                        <h2>{{ $stats['unsubscribes_count'] }}</h2>
                    </div>
                </div>
            @if(Route::has('email-marketing.unsubscribes'))
                </a>
            @endif
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('email-marketing.templates') }}" class="btn btn-outline-primary mb-2 me-2">
                        <i class="fas fa-file-alt"></i> Email Templates
                    </a>
                    <a href="{{ route('email-marketing.campaigns') }}" class="btn btn-outline-primary mb-2 me-2">
                        <i class="fas fa-paper-plane"></i> All Campaigns
                    </a>
                    <a href="{{ route('email-marketing.templates.create') }}" class="btn btn-outline-success mb-2 me-2">
                        <i class="fas fa-plus"></i> Create Template
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Campaigns</h5>
                    <a href="{{ route('email-marketing.campaigns') }}" class="btn btn-sm btn-link">All</a>
                </div>
                <div class="card-body p-0">
                    @if($recentCampaigns->count() > 0)
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Opens</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentCampaigns as $campaign)
                                    <tr>
                                        <td>
                                            <a href="{{ route('email-marketing.campaigns.show', $campaign) }}">
                                                {{ $campaign->name }}
                                            </a>
                                        </td>
                                        <td>
                                            @switch($campaign->status)
                                                @case('draft')
                                                    <span class="badge bg-secondary">Draft</span>
                                                    @break
                                                @case('sending')
                                                    <span class="badge bg-warning">Sending</span>
                                                    @break
                                                @case('completed')
                                                    <span class="badge bg-success">Completed</span>
                                                    @break
                                                @case('paused')
                                                    <span class="badge bg-info">Paused</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td>{{ $campaign->open_rate }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted p-3 mb-0">No campaigns yet</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
