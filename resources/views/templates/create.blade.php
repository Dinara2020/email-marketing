@extends(config('email-marketing.layout') ?: 'email-marketing::layouts.app')

@section('content')
<div class="template-form">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h1>New Email Template</h1>
        <a href="{{ route('email-marketing.templates') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <form action="{{ route('email-marketing.templates.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Template Name *</label>
                            <input type="text" name="name" class="form-control" required
                                   placeholder="Welcome Email"
                                   value="{{ old('name') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Subject *</label>
                            <input type="text" name="subject" class="form-control" required
                                   placeholder="Welcome to @{{site_name}}, @{{contact_name}}!"
                                   value="{{ old('subject') }}">
                            <small class="text-muted">You can use template variables</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Body (HTML) *</label>
                            <textarea name="body_html" id="body_html" class="form-control" rows="15"
                                      required>{{ old('body_html', $defaultTemplate ?? '') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1"
                                       class="form-check-input" id="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" id="previewBtn">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Template
                    </button>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Available Variables</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">Click to insert:</p>
                        @foreach($variables as $var => $desc)
                            <button type="button" class="btn btn-sm btn-outline-secondary mb-1 var-btn"
                                    data-var="{{ $var }}">
                                {{ $var }}
                            </button>
                            <small class="d-block text-muted mb-2">{{ $desc }}</small>
                        @endforeach
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Test Email</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">Save the template first, then you can send a test email.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
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

<style>
.ck-editor__editable {
    min-height: 400px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if CKEditor is available
    if (typeof CKEDITOR !== 'undefined' && CKEDITOR.ClassicEditor) {
        CKEDITOR.ClassicEditor
            .create(document.querySelector('#body_html'), {
                toolbar: {
                    items: [
                        'heading', '|',
                        'bold', 'italic', 'underline', '|',
                        'link', '|',
                        'bulletedList', 'numberedList', '|',
                        'undo', 'redo', '|',
                        'sourceEditing'
                    ],
                    shouldNotGroupWhenFull: true
                },
                htmlSupport: {
                    allow: [
                        { name: /.*/, attributes: true, classes: true, styles: true }
                    ]
                },
                removePlugins: ['CKBox', 'CKFinder', 'EasyImage', 'RealTimeCollaborativeComments', 'RealTimeCollaborativeTrackChanges', 'RealTimeCollaborativeRevisionHistory', 'PresenceList', 'Comments', 'TrackChanges', 'TrackChangesData', 'RevisionHistory', 'Pagination', 'WProofreader', 'MathType', 'SlashCommand', 'Template', 'DocumentOutline', 'FormatPainter', 'TableOfContents']
            })
            .then(editor => {
                window.editor = editor;
            })
            .catch(error => {
                console.error('CKEditor error:', error);
            });
    }

    // Insert variable
    document.querySelectorAll('.var-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const varText = this.dataset.var;
            if (window.editor) {
                window.editor.model.change(writer => {
                    window.editor.model.insertContent(writer.createText(varText));
                });
            } else {
                const textarea = document.querySelector('#body_html');
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                textarea.value = textarea.value.substring(0, start) + varText + textarea.value.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + varText.length;
                textarea.focus();
            }
        });
    });

    // Preview
    document.getElementById('previewBtn').addEventListener('click', function() {
        const subject = document.querySelector('input[name="subject"]').value;
        const body = window.editor ? window.editor.getData() : document.querySelector('#body_html').value;

        // Simple variable replacement for preview
        const dummyData = {
            '@{{hotel_name}}': 'Example Hotel',
            '@{{contact_name}}': 'John Doe',
            '@{{contact_email}}': 'example@hotel.com',
            '@{{hotel_city}}': 'New York',
            '@{{hotel_address}}': '123 Main Street',
            '@{{current_date}}': new Date().toLocaleDateString(),
            '@{{sender_name}}': 'Service Team',
            '@{{sender_company}}': '{{ config("app.name") }}',
            '@{{logo_url}}': '',
            '@{{site_url}}': '{{ config("app.url") }}',
            '@{{site_name}}': '{{ config("app.name") }}'
        };

        let previewSubject = subject;
        let previewBody = body;

        for (const [key, value] of Object.entries(dummyData)) {
            previewSubject = previewSubject.split(key).join(value);
            previewBody = previewBody.split(key).join(value);
        }

        document.getElementById('previewSubject').textContent = previewSubject;
        document.getElementById('previewBody').innerHTML = previewBody;
        new bootstrap.Modal(document.getElementById('previewModal')).show();
    });
});
</script>
@endsection
