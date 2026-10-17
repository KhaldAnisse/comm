<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'price', 'category', 'image_url', 'stock'];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
