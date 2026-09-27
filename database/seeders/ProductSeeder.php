<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Acer Aspire 5',
                'description' => '15.6 inch laptop, Ryzen 5, 8GB RAM, 512GB SSD',
                'category' => 'Laptops',
                'price' => 42990,
                'in_stock' => true,
            ],
            [
                'name' => 'Lenovo IdeaPad 3',
                'description' => '15.6 inch laptop, Intel i5, 8GB RAM, 256GB SSD',
                'category' => 'Laptops',
                'price' => 38500,
                'in_stock' => true,
            ],
            [
                'name' => 'HP 15s',
                'description' => '15.6 inch laptop, Intel i3, 8GB RAM, 512GB SSD',
                'category' => 'Laptops',
                'price' => 44999,
                'in_stock' => true,
            ],
            [
                'name' => 'MacBook Air M3',
                'description' => '13.6 inch laptop, Apple M3 chip, 8GB RAM, 256GB SSD',
                'category' => 'Laptops',
                'price' => 114900,
                'in_stock' => true,
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'description' => '6.2 inch phone, Snapdragon 8 Gen 3, 8GB RAM',
                'category' => 'Phones',
                'price' => 74999,
                'in_stock' => true,
            ],
            [
                'name' => 'OnePlus 13',
                'description' => '6.82 inch phone, Snapdragon 8 Elite, 12GB RAM',
                'category' => 'Phones',
                'price' => 57999,
                'in_stock' => true,
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'description' => 'Over-ear noise cancelling headphones',
                'category' => 'Audio',
                'price' => 29990,
                'in_stock' => true,
            ],
            [
                'name' => 'boAt Rockerz 450',
                'description' => 'On-ear Bluetooth headphones',
                'category' => 'Audio',
                'price' => 1499,
                'in_stock' => true,
            ],
            [
                'name' => 'Samsung 27 inch 4K Monitor',
                'description' => '27 inch 4K UHD IPS monitor',
                'category' => 'Monitors',
                'price' => 25999,
                'in_stock' => true,
            ],
            [
                'name' => 'Logitech MX Master 3S',
                'description' => 'Wireless ergonomic mouse',
                'category' => 'Accessories',
                'price' => 8995,
                'in_stock' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}