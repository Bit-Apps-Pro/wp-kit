<?php

namespace BitApps\WPKit\Tests\Cache;

use BitApps\WPKit\Cache\Repository;
use BitApps\WPKit\Cache\Stores\ArrayStore;
use BitApps\WPKit\Tests\TestCase;

final class RepositoryTest extends TestCase
{
    public function testRememberComputesOnceThenCaches(): void
    {
        $repo  = new Repository(new ArrayStore());
        $calls = 0;
        $make  = function () use (&$calls) {
            $calls++;

            return 'value';
        };
        $this->assertSame('value', $repo->remember('k', 60, $make));
        $this->assertSame('value', $repo->remember('k', 60, $make));
        $this->assertSame(1, $calls);
    }

    public function testPullReturnsAndForgets(): void
    {
        $repo = new Repository(new ArrayStore());
        $repo->put('k', 'v', 60);
        $this->assertSame('v', $repo->pull('k'));
        $this->assertNull($repo->get('k'));
    }

    public function testIncrement(): void
    {
        $repo = new Repository(new ArrayStore());
        $repo->put('n', 1, 60);
        $this->assertSame(3, $repo->increment('n', 2));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $repo = new Repository(new ArrayStore());

        $this->assertSame('fallback', $repo->get('missing', 'fallback'));
    }

    public function testHasReflectsPresence(): void
    {
        $repo = new Repository(new ArrayStore());

        $this->assertFalse($repo->has('k'));
        $repo->put('k', 'v', 60);
        $this->assertTrue($repo->has('k'));
    }

    public function testRememberForeverComputesOnceThenCaches(): void
    {
        $repo  = new Repository(new ArrayStore());
        $calls = 0;
        $make  = function () use (&$calls) {
            $calls++;

            return 'value';
        };
        $this->assertSame('value', $repo->rememberForever('k', $make));
        $this->assertSame('value', $repo->rememberForever('k', $make));
        $this->assertSame(1, $calls);
    }

    /**
     * Regression for M4: expiry must be computed from an injectable clock, not the
     * global time(), so tests can deterministically advance past a TTL.
     */
    public function testEntryExpiresWhenClockAdvancesPastTtl(): void
    {
        $now   = 1000;
        $clock = function () use (&$now) {
            return $now;
        };
        $repo = new Repository(new ArrayStore($clock));

        $repo->put('k', 'v', 60);
        $this->assertSame('v', $repo->get('k'));

        $now += 61;

        $this->assertNull($repo->get('k'));
    }
}
