@extends('layouts.app')

@section('title', 'Mes favoris')

@section('content')
    <h1>Mes favoris</h1>

    @if ($items->isEmpty())
        <p>Aucun favori pour l'instant.</p>
    @else
        <div class="grid">
            @foreach ($items as $item)
                <div class="card">
                    <img src="{{ $item->product->image_url }}" alt="{{ $item->product->name }}">
                    <div class="body">
                        <strong>{{ $item->product->name }}</strong>
                        <span class="price">{{ number_format($item->product->price, 2, ',', ' ') }} €</span>
                        <div class="actions">
                            <form method="POST" action="{{ route('cart.store', $item->product) }}">
                                @csrf
                                <button class="btn btn-primary" type="submit">Ajouter au panier</button>
                            </form>
                            <form method="POST" action="{{ route('wishlist.toggle', $item->product) }}">
                                @csrf
                                <button class="btn btn-fav on" type="submit">Retirer ♥</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
