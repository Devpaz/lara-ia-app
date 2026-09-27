<?php
namespace App\Ai\Tools;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchProducts implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable | string
    {
        return 'Search for products in the application database. '
            . 'Can filter by category (Laptops, Phones, Audio, Monitors, Accessories), '
            . 'search by keyword in product name or description, '
            . 'and filter by maximum price. '
            . 'Use this when the user asks about product recommendations, '
            . 'product prices, categories, availability, or products within a budget.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable | string
    {
        try {

            $category = $request['category'] ?? null;
            $keyword  = $request['keyword'] ?? null;
            $maxPrice = $request['max_price'] ?? null;

            $query = Product::query();

            if ($category) {
                $query->where(function ($q) use ($category) {
                    $q->where('name', 'like', "%{$category}%")
                        ->orWhere('description', 'like', "%{$category}%");
                });
            }

            if ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            }

            if ($maxPrice) {
                $query->where('price', '<=', $maxPrice);
            }

            $products = $query->where('in_stock', true)->orderBy('price')->limit(10)->get();

            if ($products->isEmpty()) {
                return 'No products found matching the given criteria';
            }

            return $products->map(function ($product) {
                return "{$product->name} - Rs" . number_format($product->price)
                    . "({$product->category}) - { $product->description}";
            })
                ->implode("\n");
        } catch (\Throwable $e) {
            report($e);
            return 'Product search failed. Please try again!';
        }

    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category'  => $schema->string()
                ->enum(['Laptops', 'Phones', 'Audio', 'Monitors', 'Accessories'])
                ->description('Product category to filter by.'),

            'keyword'   => $schema->string()
                ->description('Keyword to search in product name or description.'),

            'max_price' => $schema->number()
                ->description('Maximum product price in INR.'),
        ];
    }
}