<?php

namespace BitApps\WPKit\Tests\Cache;

use BitApps\WPKit\Cache\Cache;
use BitApps\WPKit\Cache\CacheManager;
use BitApps\WPKit\Cache\Repository;
use BitApps\WPKit\Tests\TestCase;
use InvalidArgumentException;
use RuntimeException;

final class CacheManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::reset();
    }

    public function testDefaultStoreResolvesToTheConfiguredDefaultStoreName(): void
    {
        $manager = new CacheManager(['default' => 'array', 'prefix' => 'bit_smtp_']);

        $repository = $manager->store();

        $this->assertInstanceOf(Repository::class, $repository);
        $this->assertSame($repository, $manager->store('array'), 'null must resolve to the configured default store name');
    }

    public function testNamedArrayStoreRoundTripsAValue(): void
    {
        $manager    = new CacheManager(['default' => 'transient']);
        $repository = $manager->store('array');

        $this->assertTrue($repository->put('k', 'v', 60));
        $this->assertSame('v', $repository->get('k'));
    }

    public function testRepeatedStoreCallsReturnTheSameRepositoryInstance(): void
    {
        $manager = new CacheManager(['default' => 'array']);

        $this->assertSame($manager->store('array'), $manager->store('array'));
    }

    public function testFileStoreWithoutConfiguredPathThrows(): void
    {
        $manager = new CacheManager(['default' => 'array']);

        $this->expectException(InvalidArgumentException::class);

        $manager->store('file');
    }

    public function testFileStoreWithConfiguredPathRoundTripsAValue(): void
    {
        $directory = sys_get_temp_dir() . '/wpkit-cachemanager-test-' . uniqid('', true);
        $manager   = new CacheManager([
            'default' => 'array',
            'prefix'  => 'bit_smtp_',
            'stores'  => ['file' => ['path' => $directory]],
        ]);

        $repository = $manager->store('file');

        $this->assertTrue($repository->put('k', 'v', 60));
        $this->assertSame('v', $repository->get('k'));

        $this->removeDirectory($directory);
    }

    public function testFacadeForwardsRememberToTheDefaultStoreAfterSetManager(): void
    {
        Cache::setManager(new CacheManager(['default' => 'array']));

        $value = Cache::remember('k', 60, function () {
            return 'v';
        });

        $this->assertSame('v', $value);
        $this->assertInstanceOf(Repository::class, Cache::store('array'));
    }

    public function testFacadeUsedBeforeSetManagerThrows(): void
    {
        $this->expectException(RuntimeException::class);

        Cache::store();
    }

    /**
     * Recursively deletes a directory tree; used to clean up FileStore fixtures.
     */
    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $path) {
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
