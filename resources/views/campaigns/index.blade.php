@extends(config('email-marketing.layout') ?: 'email-marketing::layouts.app')

@section('content')
<div class="campaigns-list">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h1>Email Campaigns</h1>
        <div>
            <a href="{{ route('email-marketing.index') }}" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="{{ route('email-marketing.campaigns.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Campaign
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if($campaigns->count() > 0)
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Template</th>
                            <th>Status</th>
                            <th>Sent</th>
                            <th>Opened</th>
                            <th>Open Rate</th>
                            <th>Date</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($campaigns as $campaign)
                            <tr>
                                <td>
                                    <a href="{{ route('email-marketing.campaigns.show', $campaign) }}">
                                        <strong>{{ $campaign->name }}</strong>
                                    </a>
                                </td>
                                <td>{{ $campaign->template->name ?? 'Deleted' }}</td>
                                <td>
                                    @switch($campaign->status)
                                        @case('draft')
                                            <span class="badge bg-secondary">Draft</span>
                                            @break
                                        @case('sending')
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-spinner fa-spin"></i> Sending
                                            </span>
                                            @break
                                        @case('completed')
                                            <span class="badge bg-success">Completed</span>
                                            @break
                                        @case('paused')
                                            <span class="badge bg-info">Paused</span>
                                            @break
                                    @endswitch
                                </td>
                                <td>{{ $campaign->sent_count }} / {{ $campaign->total_recipients }}</td>
                                <td>{{ $campaign->opened_count }}</td>
                                <td>
                                    <span class="badge bg-{{ $campaign->open_rate > 20 ? 'success' : ($campaign->open_rate > 10 ? 'warning' : 'secondary') }}">
                                        {{ $campaign->open_rate }}%
                                    </span>
                                </td>
                                <td>{{ $campaign->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('email-marketing.campaigns.show', $campaign) }}"
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if($campaign->status === 'draft')
                                        <form action="{{ route('email-marketing.campaigns.delete', $campaign) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete this campaign?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="p-3">
                    {{ $campaigns->links() }}
                </div>
            @else
                <p class="text-muted p-4 mb-0 text-center">
                    No campaigns yet.
                    <a href="{{ route('email-marketing.campaigns.create') }}">Create your first campaign</a>
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
