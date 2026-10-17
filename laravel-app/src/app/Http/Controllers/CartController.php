<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Chaque action se traduit par une écriture dans `cart_items`,
 * que Debezium lit dans le binlog MySQL et publie dans le topic
 * Kafka `shop.ecommerce.cart_items`. Aucun code Kafka n'est
 * nécessaire côté application : c'est tout l'intérêt du CDC.
 */
class CartController extends Controller
{
    public function index(Request $request)
    {
        $items = $request->user()->cartItems()->with('product')->get();
        $total = $items->sum(fn (CartItem $item) => $item->subtotal());

        return view('cart.index', compact('items', 'total'));
    }

    // Ajout au panier : INSERT (op=c) ou UPDATE de la quantité (op=u)
    public function store(Request $request, Product $product)
    {
        $quantity = max(1, $request->integer('quantity', 1));

        $item = CartItem::firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
        $item->unit_price = $product->price;
        $item->save();

        return back()->with('status', "« {$product->name} » ajouté au panier.");
    }

    // Changement de quantité : UPDATE (op=u)
    public function update(Request $request, CartItem $item)
    {
        abort_unless($item->user()->is($request->user()), 403);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $item->update($data);

        return back()->with('status', 'Quantité mise à jour.');
    }

    // Retrait du panier : DELETE (op=d)
    public function destroy(Request $request, CartItem $item)
    {
        abort_unless($item->user()->is($request->user()), 403);

        $item->delete();

        return back()->with('status', 'Produit retiré du panier.');
    }
}
