<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;

/**
 * Les écritures dans `wishlists` sont capturées par Debezium
 * dans le topic Kafka `shop.ecommerce.wishlists`.
 */
class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $items = Wishlist::with('product')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('wishlist.index', compact('items'));
    }

    // Mise en favori : INSERT (op=c) / retrait : DELETE (op=d)
    public function toggle(Request $request, Product $product)
    {
        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $message = "« {$product->name} » retiré des favoris.";
        } else {
            Wishlist::create(['user_id' => $request->user()->id, 'product_id' => $product->id]);
            $message = "« {$product->name} » ajouté aux favoris.";
        }

        return back()->with('status', $message);
    }
}
