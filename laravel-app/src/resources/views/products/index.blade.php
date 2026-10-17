@extends('layouts.app')

@section('title', 'Catalogue')

@section('content')
    <h1>Catalogue</h1>

    <div class="grid">
        @foreach ($products as $product)
            <div class="card">
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                <div class="body">
                    <strong>{{ $product->name }}</strong>
                    <small>{{ $product->category }}</small>
                    <span class="price">{{ number_format($product->price, 2, ',', ' ') }} €</span>

                    <div class="actions">
                        <form method="POST" action="{{ route('cart.store', $product) }}">
                            @csrf
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn btn-primary" type="submit">Ajouter au panier</button>
                        </form>

                        @php($isFav = in_array($product->id, $favoriteIds))
                        <form method="POST" action="{{ route('wishlist.toggle', $product) }}">
                            @csrf
                            <button class="btn btn-fav {{ $isFav ? 'on' : '' }}" type="submit"
                                    title="{{ $isFav ? 'Retirer des favoris' : 'Ajouter aux favoris' }}">
                                {{ $isFav ? '♥' : '♡' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
