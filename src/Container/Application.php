<?php

namespace BitApps\WPKit\Container;

/**
 * Container extension that manages service provider registration and a single boot pass.
 */
class Application extends Container
{
    /**
     * @var ServiceProvider[]
     */
    protected $providers = [];

    protected bool $booted = false;

    /**
     * Register a provider, instantiating it from a class-string if needed; boots it immediately if the app already booted.
     *
     * @param class-string<ServiceProvider>|ServiceProvider $provider
     */
    public function register($provider): void
    {
        if (\is_string($provider)) {
            $provider = new $provider($this);
        }
        $this->providers[] = $provider;
        $provider->register();
        if ($this->booted) {
            $provider->boot();
        }
    }

    /**
     * Boot all registered providers exactly once; subsequent calls are no-ops.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
        $this->booted = true;
    }

    /**
     * Report whether boot() has already run.
     */
    public function booted(): bool
    {
        return $this->booted;
    }
}
