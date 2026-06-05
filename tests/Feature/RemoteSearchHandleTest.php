<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WebfingerService;
use Tests\TestCase;

/**
 * Covers SearchController::remoteLookup() — that a bare fediverse handle
 * (user@domain) reaches WebFinger resolution instead of being rejected by the
 * URL-sanitisation gate, while real URLs are still validated for SSRF.
 *
 * No database is touched: the acting user is built with make() (auth via
 * actingAs() does not require persistence) and WebfingerService is mocked, so
 * the handle path short-circuits before any query runs.
 */
class RemoteSearchHandleTest extends TestCase
{
    private function actingUser(): User
    {
        // profile_id is read (typed int) but, with WebfingerService mocked to
        // return null, never used in a query — so any non-null id is fine.
        return User::factory()->make(['profile_id' => 1]);
    }

    public function test_bare_handle_reaches_webfinger_and_is_not_rejected_as_invalid_url(): void
    {
        $this->mock(WebfingerService::class, function ($mock) {
            $mock->shouldReceive('findOrCreateRemoteActor')->andReturnNull();
        });

        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/search/remote', ['q' => 'someone@example.com']);

        // Before the fix this returned 403 "Invalid url"; now the handle is
        // routed to WebFinger, which (mocked to find nothing) yields a clean
        // "No remote account found" rather than a URL-validation error.
        $response->assertStatus(200);
        $response->assertJsonPath('error', 'No remote account found');
    }

    public function test_hyphenated_handle_is_also_accepted(): void
    {
        $this->mock(WebfingerService::class, function ($mock) {
            $mock->shouldReceive('findOrCreateRemoteActor')->andReturnNull();
        });

        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/search/remote', ['q' => 'fa1ry-du5t@example.com']);

        $response->assertStatus(200);
        $response->assertJsonPath('error', 'No remote account found');
    }

    public function test_non_https_url_is_still_rejected_as_invalid_url(): void
    {
        // SSRF protection must be preserved: a real URL that fails the sanitiser
        // (non-https / loopback) still aborts with 403 "Invalid url".
        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/search/remote', ['q' => 'http://127.0.0.1/admin']);

        $response->assertStatus(403);
    }
}
