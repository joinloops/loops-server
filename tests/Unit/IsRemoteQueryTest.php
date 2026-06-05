<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\SearchController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Covers SearchController::isRemoteQuery() — the predicate that decides whether
 * a search string should be resolved as a remote fediverse actor.
 *
 * Pure logic (regex / filter_var), so it runs without the app container or DB:
 * the controller is created via newInstanceWithoutConstructor() and the
 * protected method invoked by reflection.
 */
class IsRemoteQueryTest extends TestCase
{
    private function isRemoteQuery(string $query): bool
    {
        $ref = new ReflectionClass(SearchController::class);
        $controller = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('isRemoteQuery');
        $method->setAccessible(true);

        return $method->invoke($controller, $query);
    }

    public function test_recognises_bare_handles(): void
    {
        $this->assertTrue($this->isRemoteQuery('mtv@divine.video'));
        $this->assertTrue($this->isRemoteQuery('@mtv@divine.video'));
        $this->assertTrue($this->isRemoteQuery('dansup@pixelfed.social'));
    }

    public function test_recognises_hyphenated_usernames(): void
    {
        // Regression: hyphens are valid in fediverse local-parts. Before the fix
        // the username class was [a-zA-Z0-9_] and these returned false.
        $this->assertTrue($this->isRemoteQuery('fa1ry-du5t@divine.video'));
        $this->assertTrue($this->isRemoteQuery('x-x@example.com'));
    }

    public function test_recognises_urls(): void
    {
        $this->assertTrue($this->isRemoteQuery('https://divine.video/ap/users/mtv'));
    }

    public function test_rejects_plain_search_terms(): void
    {
        $this->assertFalse($this->isRemoteQuery('just a search'));
        $this->assertFalse($this->isRemoteQuery('hashtag'));
        $this->assertFalse($this->isRemoteQuery('nobody'));
    }
}
