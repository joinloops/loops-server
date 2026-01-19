<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AdminOnlyAccess;
use App\Models\RelaySubscription;
use App\Services\RelayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class AdminRelayController extends Controller
{
    protected $relayService;

    public function __construct(RelayService $relayService)
    {
        $this->middleware(AdminOnlyAccess::class);
        $this->relayService = $relayService;
    }

    public function index(): JsonResponse
    {
        $relays = RelaySubscription::orderBy('created_at', 'desc')->get();

        return response()->json([
            'relays' => $relays,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'relay_url' => 'required|url|unique:relay_subscriptions,relay_url',
            'name' => 'nullable|string|max:255',
            'send_public_posts' => 'boolean',
            'receive_content' => 'boolean',
        ]);

        try {
            $relay = $this->relayService->subscribe($validated['relay_url']);

            if (isset($validated['name'])) {
                $relay->update(['name' => $validated['name']]);
            }

            if (isset($validated['send_public_posts'])) {
                $relay->update(['send_public_posts' => $validated['send_public_posts']]);
            }

            if (isset($validated['receive_content'])) {
                $relay->update(['receive_content' => $validated['receive_content']]);
            }

            return response()->json([
                'message' => 'Relay subscription initiated',
                'relay' => $relay,
            ], 201);

        } catch (Exception $e) {
            if (config('logging.dev_log')) {
                Log::error('Failed to subscribe to relay', [
                    'relay_url' => $validated['relay_url'],
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'error' => 'Failed to subscribe to relay',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, RelaySubscription $relay): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'send_public_posts' => 'boolean',
            'receive_content' => 'boolean',
            'status' => 'in:active,disabled',
        ]);

        $relay->update($validated);

        return response()->json([
            'message' => 'Relay updated successfully',
            'relay' => $relay->fresh(),
        ]);
    }

    public function destroy(RelaySubscription $relay): JsonResponse
    {
        try {
            $this->relayService->unsubscribe($relay);

            return response()->json([
                'message' => 'Relay unsubscribed successfully',
            ]);

        } catch (Exception $e) {
            if (config('logging.dev_log')) {
                Log::error('Failed to unsubscribe from relay', [
                    'relay_id' => $relay->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'error' => 'Failed to unsubscribe from relay',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function enable(RelaySubscription $relay): JsonResponse
    {
        $relay->enable();

        return response()->json([
            'message' => 'Relay enabled successfully',
            'relay' => $relay->fresh(),
        ]);
    }

    public function disable(RelaySubscription $relay): JsonResponse
    {
        $relay->disable();

        return response()->json([
            'message' => 'Relay disabled successfully',
            'relay' => $relay->fresh(),
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'total_relays' => RelaySubscription::count(),
            'active_relays' => RelaySubscription::where('status', 'active')->count(),
            'pending_relays' => RelaySubscription::where('status', 'pending')->count(),
            'total_sent' => RelaySubscription::sum('total_sent'),
            'total_received' => RelaySubscription::sum('total_received'),
        ];

        return response()->json(['stats' => $stats]);
    }
}
