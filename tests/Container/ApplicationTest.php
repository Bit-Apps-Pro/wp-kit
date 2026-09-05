<?php

namespace BitApps\WPKit\Tests\Container;

use BitApps\WPKit\Container\Application;
use BitApps\WPKit\Container\ServiceProvider;
use BitApps\WPKit\Tests\TestCase;

final class ApplicationTest extends TestCase
{
    public function testRegisterThenBootRunsInOrderOnce(): void
    {
        RecordingProvider::$log = [];

        $app = new Application();
        $app->register(RecordingProvider::class);
        $app->boot();
        $app->boot();
        $this->assertSame(['register', 'boot'], RecordingProvider::$log);
        $this->assertTrue($app->booted());
        $this->assertSame('ok', $app->make('recorded'));
    }

    public function testRegisterAcceptsAlreadyConstructedProviderInstance(): void
    {
        RecordingProvider::$log = [];

        $app      = new Application();
        $provider = new RecordingProvider($app);
        $app->register($provider);
        $app->boot();

        $this->assertSame(['register', 'boot'], RecordingProvider::$log);
    }

    public function testRegisterAfterBootRunsThatProvidersBootImmediately(): void
    {
        RecordingProvider::$log = [];
        LateProvider::$booted   = false;

        $app = new Application();
        $app->register(RecordingProvider::class);
        $app->boot();

        $app->register(LateProvider::class);

        $this->assertTrue(LateProvider::$booted);
    }
}

class RecordingProvider extends ServiceProvider
{
    public static array $log = [];

    public function register(): void
    {
        self::$log = ['register'];
        // Container::instance() permanently shadows bind() for the same key (make() checks
        // instances first, by design — see ContainerTest::testInstanceAndClosureBinding), so
        // only bind() is exercised here to keep the 'ok' resolution meaningful.
        $this->app->bind('recorded', fn () => 'ok');
    }

    public function boot(): void
    {
        self::$log[] = 'boot';
    }
}

class LateProvider extends ServiceProvider
{
    public static bool $booted = false;

    public function register(): void
    {
    }

    public function boot(): void
    {
        self::$booted = true;
    }
}
