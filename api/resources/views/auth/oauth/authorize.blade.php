@extends('auth.layout')

@section('title', 'Autorizar acesso')

@section('content')
    <h1>Autorizar acesso</h1>
    <p><strong>{{ $client->name }}</strong> quer acessar o {{ config('app.name', 'Nox Tasks') }} em seu nome.</p>

    <p>Conectado como <strong>{{ $user->email }}</strong>. O aplicativo poderá usar as ferramentas do workspace permitidas pelo seu perfil:</p>

    @if (count($scopes) > 0)
        <ul>
            @foreach ($scopes as $scope)
                <li>{{ $scope->description }}</li>
            @endforeach
        </ul>
    @endif

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit">Negar</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="primary">Autorizar</button>
        </form>
    </div>

    <form method="POST" action="{{ route('logout') }}" style="margin-top:16px">
        @csrf
        <button type="submit" style="border:none;color:var(--muted);font-weight:400">Não é você? Sair</button>
    </form>
@endsection
