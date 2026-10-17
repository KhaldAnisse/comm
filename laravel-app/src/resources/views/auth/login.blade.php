@extends('layouts.app')

@section('title', 'Connexion')

@section('content')
    <h1>Connexion</h1>
    <p>Comptes de démo : <code>alice@shop.test</code> / <code>bob@shop.test</code> — mot de passe <code>password</code></p>

    <form method="POST" action="{{ route('login') }}" style="max-width: 360px; display: grid; gap: 10px;">
        @csrf
        <input type="email" name="email" value="{{ old('email', 'alice@shop.test') }}" placeholder="Email" required>
        <input type="password" name="password" value="password" placeholder="Mot de passe" required>
        @error('email') <small style="color: #b91c1c">{{ $message }}</small> @enderror
        <button class="btn btn-primary" type="submit">Se connecter</button>
    </form>
@endsection
