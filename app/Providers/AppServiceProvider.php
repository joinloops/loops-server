<?php

namespace App\Providers;

use App\Services\UserActivityService;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('app_version', function () {
            return '1.0.0-beta.5';
        });

        $this->app->singleton('user_agent', function () {
            $version = app('app_version');
            $url = config('app.url');

            return "Loops/{$version} (Laravel/".app()->version()."; +{$url})";
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $enabled = config('loops.api.rate_limits.enabled');
            if (! $enabled) {
                return;
            }

            $user = $request->user();
            $actor = $user
                ? "u:{$user->id}"
                : 'ip:'.$request->ip();

            $tooMany = fn ($req, array $headers) => response()->json([
                'message' => 'Too many requests',
                'retry_after' => $headers['Retry-After'] ?? null,
            ], 429)->withHeaders($headers);

            $limits = function (int $perMinute, int $perHour) use ($actor, $tooMany) {
                return [
                    Limit::perMinute($perMinute)->by("m:$actor")->response($tooMany),
                    Limit::perHour($perHour)->by("h:$actor")->response($tooMany),
                ];
            };

            if ($user?->is_admin) {
                return;
            }

            if ($user) {
                return $limits(perMinute: config('loops.api.rate_limits.users.per_minute'), perHour: config('loops.api.rate_limits.users.per_hour'));
            }

            return $limits(perMinute: config('loops.api.rate_limits.guests.per_minute'), perHour: config('loops.api.rate_limits.guests.per_hour'));
        });

        RateLimiter::for('autocomplete', function (Request $request) {
            $user = $request->user();
            if ($user->is_admin) {
                return;
            }

            return [
                Limit::perMinute(30)->by('minute:'.$user->id),
                Limit::perDay(300)->by('day:'.$user->id),
            ];
        });

        RateLimiter::for('searchV1', function (Request $request) {
            $user = $request->user();
            if ($user->is_admin) {
                return;
            }

            return [
                Limit::perMinute(30)->by('minute:'.$user->id),
                Limit::perDay(500)->by('day:'.$user->id),
            ];
        });

        Event::listen(Login::class, function ($e) {
            app(UserActivityService::class)->markActive($e->user);
        });

        Passport::authorizationView('auth.oauth.authorize');
        Passport::tokensCan([
            'user:read' => 'Retrieve the user info',
            'user:write' => 'Update the user info',
            'video:create' => 'Create video',
            'video:read' => 'Retrieve video',
        ]);

        $this->configureSecureUrls();

    }

    protected function configureSecureUrls()
    {
        URL::forceHttps(true);
    }
}
