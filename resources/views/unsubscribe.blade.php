<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отписка от рассылки</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .unsubscribe-card {
            max-width: 500px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card unsubscribe-card shadow-sm">
            <div class="card-body p-4">
                @if(isset($error))
                    <div class="text-center">
                        <div class="text-danger mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                        </div>
                        <h4>Ошибка</h4>
                        <p class="text-muted">{{ $error }}</p>
                    </div>
                @elseif(isset($success) && $success)
                    <div class="text-center">
                        <div class="text-success mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                            </svg>
                        </div>
                        @if(isset($already) && $already)
                            <h4>Вы уже отписаны</h4>
                            <p class="text-muted">Email <strong>{{ $email }}</strong> уже был отписан от рассылки ранее.</p>
                        @else
                            <h4>Вы успешно отписались</h4>
                            <p class="text-muted">Email <strong>{{ $email }}</strong> больше не будет получать рассылку.</p>
                        @endif
                    </div>
                @else
                    <h4 class="card-title mb-4 text-center">Отписка от рассылки</h4>
                    <p class="text-muted text-center mb-4">
                        Вы уверены, что хотите отписаться от рассылки?<br>
                        <strong>{{ $email }}</strong>
                    </p>

                    <form action="{{ route('email-marketing.unsubscribe.process') }}" method="POST">
                        @csrf
                        <input type="hidden" name="email" value="{{ $encodedEmail }}">
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div class="mb-3">
                            <label class="form-label">Причина отписки (необязательно)</label>
                            <select name="reason" class="form-select">
                                <option value="">-- Выберите причину --</option>
                                <option value="too_frequent">Слишком частые письма</option>
                                <option value="not_relevant">Неактуальный контент</option>
                                <option value="never_subscribed">Я не подписывался</option>
                                <option value="other">Другое</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger">
                                Отписаться
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        <p class="text-center text-muted mt-4 small">
            {{ config('app.name') }}
        </p>
    </div>
</body>
</html>
