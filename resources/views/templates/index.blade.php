@extends(config('email-marketing.layout') ?: 'email-marketing::layouts.app')

@section('content')
<div class="templates-list">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h1>Email Templates</h1>
        <div>
            <a href="{{ route('email-marketing.index') }}" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="{{ route('email-marketing.templates.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Template
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if($templates->count() > 0)
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Used In</th>
                            <th>Created</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($templates as $template)
                            <tr>
                                <td>
                                    <strong>{{ $template->name }}</strong>
                                </td>
                                <td>{{ Str::limit($template->subject, 50) }}</td>
                                <td>
                                    @if($template->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $template->campaigns->count() }}</td>
                                <td>{{ $template->created_at->format('d.m.Y') }}</td>
                                <td>
                                    <a href="{{ route('email-marketing.templates.edit', $template) }}"
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-info preview-btn"
                                            data-template-id="{{ $template->id }}" title="Preview">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <form action="{{ route('email-marketing.templates.delete', $template) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this template?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="p-3">
                    {{ $templates->links() }}
                </div>
            @else
                <p class="text-muted p-4 mb-0 text-center">
                    No templates yet.
                    <a href="{{ route('email-marketing.templates.create') }}">Create your first template</a>
                </p>
            @endif
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Subject:</strong> <span id="previewSubject"></span></p>
                <hr>
                <div id="previewBody" style="border: 1px solid #ddd; padding: 15px; background: #fff;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.preview-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const templateId = this.dataset.templateId;

        fetch(`{{ url(config('email-marketing.route_prefix', 'admin/email-marketing')) }}/templates/${templateId}/preview`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('previewSubject').textContent = data.subject;
                document.getElementById('previewBody').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('previewModal')).show();
            });
    });
});
</script>
@endsection
