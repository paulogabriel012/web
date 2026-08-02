@php
    /** @var \Laravel\Passport\Client $client */
    /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
    /** @var \Laravel\Passport\Scope[] $scopes */
    /** @var \Illuminate\Http\Request $request */
    $query = $request->except(['_token', 'authToken']);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Autorização') }} — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-zinc-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-sm p-8">
        <h1 class="text-xl font-semibold text-zinc-900">{{ __('Autorização de acesso') }}</h1>
        <p class="mt-2 text-sm text-zinc-500">
            <strong>{{ $client->name }}</strong> {{ __('solicita acesso à sua conta') }}
            <strong>{{ $user->name }}</strong> {{ __('para:') }}
        </p>

        <ul class="mt-4 space-y-2">
            @foreach ($scopes as $scope)
                <li class="flex items-start gap-2 text-sm text-zinc-700">
                    <svg class="mt-0.5 h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ $scope->description ?: $scope->id }}
                </li>
            @endforeach
        </ul>

        <div class="mt-8 flex items-center justify-end gap-3">
            <form method="post" action="{{ url('/oauth/authorize') }}" class="inline">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                @foreach ($query as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ is_array($value) ? implode(' ', $value) : $value }}">
                @endforeach
                <button type="submit" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                    {{ __('Recusar') }}
                </button>
            </form>

            <form method="post" action="{{ url('/oauth/authorize') }}" class="inline">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <input type="hidden" name="approve" value="1">
                @foreach ($query as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ is_array($value) ? implode(' ', $value) : $value }}">
                @endforeach
                <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700">
                    {{ __('Autorizar') }}
                </button>
            </form>
        </div>
    </div>
</body>
</html>
