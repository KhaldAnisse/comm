<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::orderBy('name')->get();

        $favoriteIds = $request->user()?->favorites()->pluck('products.id')->all() ?? [];

        return view('products.index', compact('products', 'favoriteIds'));
    }
}
