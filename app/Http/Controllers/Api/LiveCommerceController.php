<?php

namespace App\Http\Controllers\Api;

use App\Models\LiveStream;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LiveCommerceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web,api')->except(['listStreams', 'getActiveStreams', 'getStream']);
    }

    /**
     * Create a new live stream
     */
    public function createStream(Request $request): JsonResponse
    {
        $profileId = $request->user()->profile_id ?? $request->user()->id;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_viewers' => 'nullable|integer|min:0',
            'thumbnail_url' => 'nullable|url|max:500',
            'scheduled_at' => 'nullable|date',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
            'chat_enabled' => 'nullable|boolean',
        ]);

        $validated['profile_id'] = $profileId;
        $validated['status'] = 'pending';
        $validated['stream_key'] = $this->generateStreamKey($profileId);
        $validated['chat_enabled'] = $validated['chat_enabled'] ?? true;

        $stream = LiveStream::create($validated);

        return response()->json($stream, 201);
    }

    /**
     * Start a live stream (change status from pending to live)
     */
    public function startStream(Request $request, int $id): JsonResponse
    {
        $stream = LiveStream::findOrFail($id);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        if ($stream->profile_id !== $profileId && !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!in_array($stream->status, ['pending', 'scheduled'])) {
            return response()->json(['message' => 'Stream cannot be started from current status.'], 422);
        }

        $stream->update([
            'status' => 'live',
            'started_at' => now(),
        ]);

        return response()->json($stream);
    }

    /**
     * End a live stream
     */
    public function endStream(Request $request, int $id): JsonResponse
    {
        $stream = LiveStream::findOrFail($id);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        if ($stream->profile_id !== $profileId && !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($stream->status !== 'live') {
            return response()->json(['message' => 'Stream is not currently live.'], 422);
        }

        $validated = $request->validate([
            'recording_url' => 'nullable|url|max:500',
        ]);

        $stream->update([
            'status' => 'ended',
            'ended_at' => now(),
            'recording_url' => $validated['recording_url'] ?? $stream->recording_url,
        ]);

        return response()->json($stream);
    }

    /**
     * Get a single stream by ID
     */
    public function getStream(int $id): JsonResponse
    {
        $stream = LiveStream::with('profile')->findOrFail($id);

        return response()->json($stream);
    }

    /**
     * List all streams (paginated), with optional filtering
     */
    public function listStreams(Request $request): JsonResponse
    {
        $query = LiveStream::with('profile');

        if ($request->has('profile_id')) {
            $query->where('profile_id', $request->input('profile_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $streams = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($streams);
    }

    /**
     * Get currently active (live) streams
     */
    public function getActiveStreams(): JsonResponse
    {
        $streams = LiveStream::with('profile')
            ->where('status', 'live')
            ->orderBy('viewer_count', 'desc')
            ->get();

        return response()->json($streams);
    }

    /**
     * Add a product to a live stream
     */
    public function addProductToStream(Request $request, int $id): JsonResponse
    {
        $stream = LiveStream::findOrFail($id);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        if ($stream->profile_id !== $profileId && !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $productIds = $stream->product_ids ?? [];
        $productId = (int) $validated['product_id'];

        if (in_array($productId, $productIds)) {
            return response()->json(['message' => 'Product is already linked to this stream.'], 409);
        }

        $productIds[] = $productId;
        $stream->update(['product_ids' => $productIds]);

        return response()->json($stream);
    }

    /**
     * Remove a product from a live stream
     */
    public function removeProductFromStream(Request $request, int $id): JsonResponse
    {
        $stream = LiveStream::findOrFail($id);

        $profileId = $request->user()->profile_id ?? $request->user()->id;
        if ($stream->profile_id !== $profileId && !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|integer',
        ]);

        $productIds = $stream->product_ids ?? [];
        $productId = (int) $validated['product_id'];

        $productIds = array_values(array_filter($productIds, fn($id) => (int) $id !== $productId));
        $stream->update(['product_ids' => $productIds]);

        return response()->json($stream);
    }

    /**
     * Update viewer count for a stream
     */
    public function updateViewerCount(Request $request, int $id): JsonResponse
    {
        $stream = LiveStream::findOrFail($id);

        $validated = $request->validate([
            'viewer_count' => 'required|integer|min:0',
        ]);

        $stream->update(['viewer_count' => $validated['viewer_count']]);

        return response()->json($stream);
    }

    /**
     * Record a chat message for a live stream
     */
    public function recordChatMessage(Request $request, int $id): JsonResponse
    {
        $stream = LiveStream::findOrFail($id);

        if (!$stream->chat_enabled) {
            return response()->json(['message' => 'Chat is disabled for this stream.'], 422);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:500',
        ]);

        // Store the message as a simple record — can be extended to a dedicated chat table later
        $message = [
            'user_id' => $request->user()->profile_id ?? $request->user()->id,
            'username' => $request->user()->username ?? 'anonymous',
            'message' => $validated['message'],
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($message, 201);
    }

    /**
     * Generate a unique stream key for RTMP ingestion
     */
    private function generateStreamKey(int $profileId): string
    {
        return 'ls_' . md5($profileId . '_' . time() . '_' . bin2hex(random_bytes(8)));
    }
}
