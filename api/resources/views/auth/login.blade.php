@extends('auth.layout')

@section('title', 'Entrar')

@section('content')
    <h1>Entrar no {{ config('app.name', 'Nox Tasks') }}</h1>
    <p>Use sua conta para autorizar o acesso do aplicativo.</p>

    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <label for="email">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="password">Senha</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        @error('password') <div class="error">{{ $message }}</div> @enderror

        <div class="actions">
            <button type="submit" class="primary">Entrar</button>
        </div>
    </form>
@endsection
