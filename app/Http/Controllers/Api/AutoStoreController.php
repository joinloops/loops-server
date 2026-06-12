<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class AutoStoreController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web,api');
    }

    /**
     * Trigger AI product generation for a vendor.
     *
     * POST /api/auto-store/generate
     *
     * @bodyParam category string optional Filter by category
     * @bodyParam count int optional Number of products to generate (default: 5)
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'nullable|string|max:100',
            'count' => 'nullable|integer|min:1|max:50',
        ]);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        $category = $validated['category'] ?? null;
        $count = $validated['count'] ?? 5;

        // Gather existing products for style hint
        $existingProducts = Product::where('profile_id', $profileId)
            ->where('status', 'active')
            ->pluck('name')
            ->toArray();

        $styleHint = ! empty($existingProducts)
            ? 'Match the style of existing products: ' . implode(', ', array_slice($existingProducts, 0, 5))
            : 'Create diverse and marketable products';

        // Call loops-ai API
        try {
            $response = Http::timeout(60)->post('http://loops-ai:8500/ai/generate-products', [
                'category' => $category ?? 'general',
                'count' => $count,
                'style' => $styleHint,
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI generation service returned an error.',
                    'error' => $response->body(),
                ], 502);
            }

            $data = $response->json();

            return response()->json([
                'success' => true,
                'message' => 'Products generated successfully.',
                'products' => $data['products'] ?? [],
                'count' => $data['count'] ?? 0,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to contact AI generation service.',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Accept an array of products and create them all at once.
     *
     * POST /api/auto-store/bulk-create
     *
     * @bodyParam products array required Array of product objects
     * @bodyParam products[].name string required Product name
     * @bodyParam products[].description string optional Product description
     * @bodyParam products[].price float required Product price
     * @bodyParam products[].currency string optional Currency code (default: USD)
     * @bodyParam products[].category string optional Product category
     * @bodyParam products[].tags array optional Array of tag strings
     * @bodyParam products[].stock int optional Stock quantity (default: 100)
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'products' => 'required|array|min:1|max:100',
            'products.*.name' => 'required|string|max:255',
            'products.*.description' => 'nullable|string',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.currency' => 'nullable|string|size:3',
            'products.*.category' => 'nullable|string|max:100',
            'products.*.tags' => 'nullable|array',
            'products.*.tags.*' => 'string|max:50',
            'products.*.stock' => 'nullable|integer|min:0',
        ]);

        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $created = [];
        $errors = [];

        foreach ($validated['products'] as $index => $item) {
            try {
                $product = Product::create([
                    'profile_id' => $profileId,
                    'name' => $item['name'],
                    'description' => $item['description'] ?? '',
                    'price' => (float) $item['price'],
                    'currency' => $item['currency'] ?? 'USD',
                    'category' => $item['category'] ?? null,
                    'tags' => $item['tags'] ?? [],
                    'stock' => (int) ($item['stock'] ?? 100),
                    'status' => 'active',
                ]);
                $created[] = $product;
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'name' => $item['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Created " . count($created) . " product(s).",
            'created' => $created,
            'errors' => $errors,
            'created_count' => count($created),
            'error_count' => count($errors),
        ], empty($errors) ? 201 : 200);
    }

    /**
     * Return a list of available categories.
     *
     * GET /api/auto-store/categories
     */
    public function suggestCategories(Request $request): JsonResponse
    {
        $categories = [
            'Electronics',
            'Fashion',
            'Fitness',
            'Health',
            'Home',
            'Kitchen',
            'Travel',
            'Accessories',
            'Drinkware',
            'Office',
            'Decor',
        ];

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }
}
