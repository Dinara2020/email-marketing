@extends(config('email-marketing.layout') ?: 'email-marketing::layouts.app')

@section('content')
<div class="smtp-settings">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h1>SMTP Settings</h1>
        <a href="{{ route('email-marketing.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    @if(!$canEdit)
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        SMTP settings are read from <code>.env</code> file. To enable editing from this panel, configure <code>EMAIL_MARKETING_COMPANY_MODEL</code>.
    </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('email-marketing.smtp.save') }}" method="POST">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">SMTP Host *</label>
                                <input type="text" name="SMTP_HOST" class="form-control"
                                       value="{{ $settings['SMTP_HOST'] ?? '' }}"
                                       placeholder="smtp.example.com" {{ $canEdit ? 'required' : 'readonly' }}>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Port *</label>
                                <input type="number" name="SMTP_PORT" class="form-control"
                                       value="{{ $settings['SMTP_PORT'] ?? 587 }}" {{ $canEdit ? 'required' : 'readonly' }}>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="SMTP_USERNAME" class="form-control"
                                       value="{{ $settings['SMTP_USERNAME'] ?? '' }}"
                                       placeholder="user@domain.com" {{ $canEdit ? '' : 'readonly' }}>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="{{ $canEdit ? 'password' : 'text' }}" name="SMTP_PASSWORD" class="form-control"
                                       value="{{ $canEdit ? ($settings['SMTP_PASSWORD'] ?? '') : '********' }}"
                                       placeholder="********" {{ $canEdit ? '' : 'readonly' }}>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Encryption</label>
                            <select name="SMTP_ENCRYPTION" class="form-select" {{ $canEdit ? '' : 'disabled' }}>
                                <option value="tls" {{ ($settings['SMTP_ENCRYPTION'] ?? 'tls') == 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ ($settings['SMTP_ENCRYPTION'] ?? '') == 'ssl' ? 'selected' : '' }}>SSL</option>
                                <option value="" {{ ($settings['SMTP_ENCRYPTION'] ?? '') == '' ? 'selected' : '' }}>None</option>
                            </select>
                        </div>

                        <hr>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">From Email *</label>
                                <input type="email" name="SMTP_FROM_ADDRESS" class="form-control"
                                       value="{{ $settings['SMTP_FROM_ADDRESS'] ?? '' }}"
                                       placeholder="noreply@domain.com" {{ $canEdit ? 'required' : 'readonly' }}>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From Name *</label>
                                <input type="text" name="SMTP_FROM_NAME" class="form-control"
                                       value="{{ $settings['SMTP_FROM_NAME'] ?? '' }}"
                                       placeholder="My Company" {{ $canEdit ? 'required' : 'readonly' }}>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" id="testSmtpBtn">
                                <i class="fas fa-vial"></i> Test Connection
                            </button>
                            @if($canEdit)
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save
                            </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Reference</h5>
                </div>
                <div class="card-body">
                    <h6>Gmail</h6>
                    <ul class="small text-muted">
                        <li>Host: smtp.gmail.com</li>
                        <li>Port: 587 (TLS)</li>
                        <li>Requires App Password</li>
                    </ul>

                    <h6>Outlook/Office 365</h6>
                    <ul class="small text-muted">
                        <li>Host: smtp.office365.com</li>
                        <li>Port: 587 (TLS)</li>
                    </ul>

                    <h6>SendGrid</h6>
                    <ul class="small text-muted">
                        <li>Host: smtp.sendgrid.net</li>
                        <li>Port: 587 (TLS)</li>
                    </ul>

                    <h6>Yandex</h6>
                    <ul class="small text-muted">
                        <li>Host: smtp.yandex.ru</li>
                        <li>Port: 587 (TLS)</li>
                    </ul>
                </div>
            </div>

            <div class="card mt-3" id="testResult" style="display: none;">
                <div class="card-body">
                    <div id="testResultContent"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('testSmtpBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';

    fetch('{{ route("email-marketing.smtp.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        const resultDiv = document.getElementById('testResult');
        const contentDiv = document.getElementById('testResultContent');

        resultDiv.style.display = 'block';

        if (data.success) {
            contentDiv.innerHTML = '<div class="alert alert-success mb-0"><i class="fas fa-check"></i> ' + data.message + '</div>';
        } else {
            contentDiv.innerHTML = '<div class="alert alert-danger mb-0"><i class="fas fa-times"></i> ' + data.message + '</div>';
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-vial"></i> Test Connection';
    });
});
</script>
@endpush
