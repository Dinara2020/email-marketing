@extends(config('email-marketing.layout') ?: 'email-marketing::layouts.app')

@section('content')
<div class="unsubscribes-list">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h1>Отписавшиеся</h1>
        <span class="badge bg-secondary fs-6">{{ $unsubscribes->total() }} всего</span>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Причина</th>
                        <th>IP</th>
                        <th>Дата</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($unsubscribes as $unsub)
                        <tr>
                            <td>{{ $unsub->email }}</td>
                            <td>
                                @switch($unsub->reason)
                                    @case('too_frequent')
                                        <span class="badge bg-warning text-dark">Слишком часто</span>
                                        @break
                                    @case('not_relevant')
                                        <span class="badge bg-info">Неактуально</span>
                                        @break
                                    @case('never_subscribed')
                                        <span class="badge bg-danger">Не подписывался</span>
                                        @break
                                    @case('other')
                                        <span class="badge bg-secondary">Другое</span>
                                        @break
                                    @default
                                        <span class="text-muted">—</span>
                                @endswitch
                            </td>
                            <td><small class="text-muted">{{ $unsub->ip }}</small></td>
                            <td>{{ $unsub->created_at->format('d.m.Y H:i') }}</td>
                            <td>
                                <form action="{{ route('email-marketing.unsubscribes.delete', $unsub) }}" method="POST" class="d-inline" onsubmit="return confirm('Вернуть в рассылку?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Вернуть в рассылку">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Нет отписавшихся</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $unsubscribes->links() }}
    </div>
</div>
@endsection
