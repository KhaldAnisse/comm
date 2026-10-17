@extends('layouts.app')

@section('title', 'Mon panier')

@section('content')
    <h1>Mon panier</h1>

    @if ($items->isEmpty())
        <p>Votre panier est vide. <a href="{{ route('products.index') }}">Voir le catalogue</a></p>
    @else
        <table>
            <thead>
            <tr><th>Produit</th><th>Prix unitaire</th><th>Quantité</th><th>Sous-total</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ number_format($item->unit_price, 2, ',', ' ') }} €</td>
                    <td>
                        <form class="inline" method="POST" action="{{ route('cart.update', $item) }}">
                            @csrf
                            @method('PATCH')
                            <input type="number" name="quantity" min="1" max="99" value="{{ $item->quantity }}">
                            <button class="btn" type="submit">OK</button>
                        </form>
                    </td>
                    <td>{{ number_format($item->subtotal(), 2, ',', ' ') }} €</td>
                    <td>
                        <form class="inline" method="POST" action="{{ route('cart.destroy', $item) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn" type="submit">Retirer</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr><th colspan="3">Total</th><th colspan="2">{{ number_format($total, 2, ',', ' ') }} €</th></tr>
            </tfoot>
        </table>
    @endif
@endsection
