<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RemoteSearchLimitException extends HttpException
{
    public function __construct(
        public readonly string $resource,
        public readonly int $limit,
        public readonly int $current,
        public readonly int $windowDays,
        public readonly ?string $availableAt = null,
    ) {
        parent::__construct(
            statusCode: 429,
            message: "You've reached your monthly limit of {$limit} remote {$resource} imports.",
        );
    }

    public function render(Request $request): JsonResponse
    {
        $headers = [];

        if ($this->availableAt) {
            $retryAfter = max(60, now()->diffInSeconds($this->availableAt, false));
            $headers['Retry-After'] = (string) $retryAfter;
        }

        return response()->json([
            'type' => null,
            'data' => null,
            'error' => [
                'code' => "monthly_{$this->resource}_limit_reached",
                'message' => $this->getMessage(),
                'limit' => $this->limit,
                'current' => $this->current,
                'remaining' => max(0, $this->limit - $this->current),
                'window_days' => $this->windowDays,
                'available_at' => $this->availableAt,
            ],
        ], 429, $headers);
    }
}
