<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\ProductVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web,api')->except(['index', 'show', 'search']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Product::where('status', 'active');

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('seller')) {
            $query->where('profile_id', $request->input('seller'));
        }

        $products = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'stock' => 'nullable|integer|min:0',
            'external_url' => 'nullable|url|max:500',
        ]);

        $validated['profile_id'] = $request->user()->profile_id ?? $request->user()->id;
        $validated['currency'] = $validated['currency'] ?? 'USD';
        $validated['status'] = 'active';

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with('videos')->findOrFail($id);

        return response()->json($product);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        if ($product->profile_id !== $profileId && !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'stock' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive,sold_out',
            'external_url' => 'nullable|url|max:500',
        ]);

        $product->update($validated);

        return response()->json($product);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        if ($product->profile_id !== $profileId && !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $product->update(['status' => 'deleted']);

        return response()->json(['message' => 'Product deleted']);
    }

    public function search(Request $request): JsonResponse
    {
        $query = Product::where('status', 'active');

        if ($request->has('q')) {
            $q = $request->input('q');
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orWhere('tags', 'like', "%{$q}%");
            });
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        $products = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json($products);
    }

    public function linkVideo(Request $request, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        if ($product->profile_id !== $profileId && !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'video_id' => 'required|integer|exists:videos,id',
            'bounding_box_x' => 'nullable|numeric|min:0|max:1',
            'bounding_box_y' => 'nullable|numeric|min:0|max:1',
            'bounding_box_w' => 'nullable|numeric|min:0|max:1',
            'bounding_box_h' => 'nullable|numeric|min:0|max:1',
            'timestamp_start' => 'nullable|numeric|min:0',
            'timestamp_end' => 'nullable|numeric|min:0',
        ]);

        $link = ProductVideo::create([
            'product_id' => $productId,
            'video_id' => $validated['video_id'],
            'bounding_box_x' => $validated['bounding_box_x'] ?? null,
            'bounding_box_y' => $validated['bounding_box_y'] ?? null,
            'bounding_box_w' => $validated['bounding_box_w'] ?? null,
            'bounding_box_h' => $validated['bounding_box_h'] ?? null,
            'timestamp_start' => $validated['timestamp_start'] ?? null,
            'timestamp_end' => $validated['timestamp_end'] ?? null,
            'detection_method' => 'manual',
        ]);

        return response()->json($link, 201);
    }
}
