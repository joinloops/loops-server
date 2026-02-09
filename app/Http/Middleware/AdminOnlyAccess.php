<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminOnlyAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(401, 'Unauthenticated');
        }

        if ($request->user()->is_admin !== true || $request->user()->status !== 1) {
            Log::channel('admin_security')->warning('Unauthorized admin access attempt', [
                'user_id' => $request->user()->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->path(),
                'method' => $request->method(),
                'timestamp' => now()->toIso8601String(),
            ]);

            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
