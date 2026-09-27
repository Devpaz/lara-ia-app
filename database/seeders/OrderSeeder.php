<?php
namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            $user = User::factory()->create([
                'name'  => 'Paolo Paz',
                'email' => 'paolo@example.com',
            ]);
        }

        $products = Product::all();

        if ($products->isEmpty()) {
            $this->command->error('Run ProductSeeder first.');
            return;
        }

        $orders = [
            ['product_id' => $products->where('name', 'Acer Aspire 5')->first()?->id, 'quantity' => 1, 'total' => 42990, 'status' => 'completed'],
            ['product_id' => $products->where('name', 'Sony WH-1000XM5')->first()?->id, 'quantity' => 2, 'total' => 59980, 'status' => 'completed'],
            ['product_id' => $products->where('name', 'Logitech MX Master 3S')->first()?->id, 'quantity' => 1, 'total' => 8995, 'status' => 'completed'],
            ['product_id' => $products->where('name', 'Samsung Galaxy S24')->first()?->id, 'quantity' => 1, 'total' => 74999, 'status' => 'completed'],
            ['product_id' => $products->where('name', 'boAt Rockerz 450')->first()?->id, 'quantity' => 3, 'total' => 4497, 'status' => 'completed'],
            ['product_id' => $products->where('name', 'MacBook Air M3')->first()?->id, 'quantity' => 1, 'total' => 114900, 'status' => 'cancelled'],
            ['product_id' => $products->where('name', 'OnePlus 13')->first()?->id, 'quantity' => 1, 'total' => 57999, 'status' => 'pending'],
            ['product_id' => $products->where('name', 'HP 15s')->first()?->id, 'quantity' => 1, 'total' => 44999, 'status' => 'completed'],
        ];

        foreach ($orders as $order) {
            if ($order['product_id']) {
                Order::create(array_merge($order, ['user_id' => $user->id]));
            }
        }
    }
}