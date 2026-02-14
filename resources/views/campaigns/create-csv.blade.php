@extends(config('email-marketing.layout') ?: 'email-marketing::layouts.app')

@section('content')
<div class="campaign-form">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h1>Кампания из CSV</h1>
        <a href="{{ route('email-marketing.campaigns') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Назад
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('email-marketing.campaigns.store-csv') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Настройки кампании</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Название кампании *</label>
                            <input type="text" name="name" class="form-control" required
                                   placeholder="Рассылка февраль 2026"
                                   value="{{ old('name') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Шаблон письма *</label>
                            <select name="template_id" class="form-select" required>
                                <option value="">Выберите шаблон</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">CSV файл с email адресами *</label>
                            <input type="file" name="csv_file" class="form-control" required accept=".csv,.txt">
                            <small class="text-muted">
                                Файл должен содержать email адреса (по одному на строку или через запятую)
                            </small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Загрузить и создать кампанию
                    </button>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Информация</h5>
                    </div>
                    <div class="card-body">
                        <ul class="small text-muted mb-0">
                            <li>Email очищаются от пробелов и символов &lt; &gt;</li>
                            <li>Дубликаты удаляются автоматически</li>
                            <li>Отписавшиеся исключаются</li>
                            <li>Не отправляется если email получал письмо в последние 3 дня</li>
                            <li>Лимит: 400 писем в час</li>
                        </ul>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Формат CSV</h5>
                    </div>
                    <div class="card-body">
                        <pre class="small bg-light p-2 mb-0">email1@example.com
email2@example.com
&lt;email3@example.com&gt;
email4@example.com, email5@example.com</pre>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
