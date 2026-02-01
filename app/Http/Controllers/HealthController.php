<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function ping(): Response
    {
        return response('pong!', 200)
            ->header('Content-Type', 'text/plain');
    }

    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'disk' => $this->checkDisk(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return ['status' => 'ok'];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            $response = Redis::ping();
            if ($response === true || $response == 'PONG' || (is_object($response) && method_exists($response, 'getPayload') && $response->getPayload() === 'PONG')) {
                return ['status' => 'ok'];
            }

            return [
                'status' => 'error',
                'message' => 'Unexpected response from Redis',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkDisk(): array
    {
        try {
            $path = storage_path();
            $freeBytes = disk_free_space($path);
            $totalBytes = disk_total_space($path);

            if ($freeBytes === false || $totalBytes === false) {
                return [
                    'status' => 'error',
                    'message' => 'Unable to read disk space',
                ];
            }

            $usedPercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1);
            $status = $usedPercent > 95 ? 'error' : ($usedPercent > 85 ? 'warning' : 'ok');

            return [
                'status' => $status,
                'used_percent' => $usedPercent,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
