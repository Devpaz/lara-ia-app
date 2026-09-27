<?php
namespace App\Ai\Tools;

use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchOrders implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable | string
    {
        return 'Search and receive order information form the application database. '
            . 'Can filter orders by status (pending, processing, completed, cancelled), '
            . 'by product category (Laptops, Phones, Audio, Monitors, Accessories), '
            . 'and by date range. '
            . 'Returns order detail including product name, quantity, total amount, '
            . 'order status, and customer name. '
            . 'User this when the user asks about orders, purchases, sales, '
            . 'order status, revenue, or business insights.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable | string
    {
        try {
            $status   = $request['status'] ?? null;
            $category = $request['product_category'] ?? null;
            $since    = $request['since'] ?? null;

            $query = Order::with(['product', 'user']);

            if ($status) {
                $query->where('status', $status);
            }

            if ($category) {
                $query->whereHas('product', function ($q) use ($category) {
                    $q->where('category', $category);
                });
            }

            if ($since) {
                $query->where('created_at', '>=', $since);
            }

            $orders = $query->latest()->limit(15)->get();

            if ($orders->isEmpty()) {
                return 'No orders found matching the given criteria';
            }

            $totalRevenue = $orders->sum('total');

            $result = $orders->map(function ($order) {
                return "Order #{$order->id}: {$order->product->name} "
                . "- Qty: {$order->quantity} "
                . "- Rs." . number_format($order->total) . " "
                    . "- Status: {$order->status} "
                    . "- Customer: {$order->user->name} "
                    . "- Date: {$order->created_at->format('d M Y')}";
            })->implode("\n");

            return "Found {$orders->count()} order(s). "
            . "Total revenue: Rs. " . number_format($totalRevenue) . "\n\n"
                . $result;

        } catch (\Throwable $e) {
            report($e);

            return 'Order search failed. Please try again!';
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status'           => $schema->string()->enum(
                ['pending', 'processing', 'completed', 'cancelled']
            )->description('Filter orders by status.'),

            'product_category' => $schema->string()->enum([
                'Laptops', 'Phones', 'Audio', 'Monitors', 'Accessories',
            ]),

            'since'            => $schema->string()->description('Filter orders from this date onwards. Format: Y-m-d, e.g. 2026-07-01'),
        ];
    }
}