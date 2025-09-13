<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $getenv = static function (string $key, $default = null) {
            $val = $_SERVER[$key] ?? $_ENV[$key] ?? (getenv($key) !== false ? getenv($key) : null);

            return $val ?? $default;
        };

        $enabledRaw = $getenv('TRUST_PROXIES_ENABLED', 'false');
        $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOL);

        if (! $enabled) {
            return;
        }

        $proxiesRaw = $getenv('TRUST_PROXIES', '*');
        if (is_string($proxiesRaw)) {
            $proxiesRaw = trim($proxiesRaw);
            if ($proxiesRaw === '' || $proxiesRaw === '*') {
                $trustedProxies = '*';
            } elseif (str_contains($proxiesRaw, ',')) {
                $trustedProxies = array_values(array_filter(array_map('trim', explode(',', $proxiesRaw))));
                if ($trustedProxies === []) {
                    $trustedProxies = '*';
                }
            } else {
                $trustedProxies = $proxiesRaw;
            }
        } elseif (is_array($proxiesRaw) && $proxiesRaw !== []) {
            $trustedProxies = $proxiesRaw;
        } else {
            $trustedProxies = '*';
        }

        $trustedHeadersRaw = strtolower((string) $getenv('TRUST_PROXY_HEADERS', 'all'));
        $headersMap = [
            'all' => Request::HEADER_X_FORWARDED_FOR
                          | Request::HEADER_X_FORWARDED_HOST
                          | Request::HEADER_X_FORWARDED_PORT
                          | Request::HEADER_X_FORWARDED_PROTO,
            'aws' => Request::HEADER_X_FORWARDED_AWS_ELB,
            'forwarded' => Request::HEADER_FORWARDED,
            'cloudflare' => Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
            'for' => Request::HEADER_X_FORWARDED_FOR,
            'proto' => Request::HEADER_X_FORWARDED_PROTO,
            'host' => Request::HEADER_X_FORWARDED_HOST,
            'port' => Request::HEADER_X_FORWARDED_PORT,
        ];
        $headers = is_numeric($trustedHeadersRaw)
            ? (int) $trustedHeadersRaw
            : ($headersMap[$trustedHeadersRaw] ?? $headersMap['all']);

        $middleware->trustProxies(at: $trustedProxies, headers: $headers);

        if (config('app.force_https', true)) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'data' => [],
                    'error' => [
                        'code' => 404,
                        'message' => 'Record not found.',
                    ],
                ], 404);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'data' => [],
                    'error' => [
                        'code' => 403,
                        'message' => $e->getMessage() ?? 'This action is not authorized.',
                    ],
                ], 403);
            }
        });
    })->create();
