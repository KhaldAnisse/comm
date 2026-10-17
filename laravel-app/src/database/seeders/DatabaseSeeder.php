<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['alice', 'bob'] as $name) {
            User::updateOrCreate(
                ['email' => "{$name}@shop.test"],
                ['name' => ucfirst($name), 'password' => Hash::make('password')]
            );
        }

        $products = [
            ['Casque Bluetooth', 79.90, 'audio'],
            ['Enceinte portable', 49.00, 'audio'],
            ['Clavier mécanique', 109.99, 'informatique'],
            ['Souris sans fil', 29.90, 'informatique'],
            ['Écran 27 pouces', 249.00, 'informatique'],
            ['Montre connectée', 159.00, 'objets connectés'],
            ['Liseuse numérique', 129.00, 'livres'],
            ['Sac à dos urbain', 59.90, 'accessoires'],
            ['Gourde isotherme', 19.90, 'accessoires'],
        ];

        foreach ($products as [$name, $price, $category]) {
            $slug = Str::slug($name);

            Product::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => "Description du produit {$name}.",
                'price' => $price,
                'category' => $category,
                'image_url' => "https://picsum.photos/seed/{$slug}/400/300",
                'stock' => 100,
            ]);
        }
    }
}
