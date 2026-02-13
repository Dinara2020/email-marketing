@extends(config('email-marketing.layout', 'layouts.app'))

@section('content')
<div class="container py-5">
    <div class="alert alert-danger">
        <h4 class="alert-heading">Email Marketing Configuration Error</h4>
        <p>{{ $error }}</p>
        <hr>
        <p class="mb-0">
            Please publish the config file and configure the required settings:
        </p>
        <pre class="mt-3 bg-light p-3">php artisan vendor:publish --tag=email-marketing-config</pre>
        <p class="mt-3">Then add to your <code>.env</code> file:</p>
        <pre class="bg-light p-3">EMAIL_MARKETING_LEAD_MODEL=App\Models\YourLeadModel</pre>
    </div>
</div>
@endsection
