<?php

namespace BitApps\WPKit\Cron;

/**
 * Registers custom cron schedules and recurring/one-off jobs, wiring them onto WordPress cron on boot.
 */
final class Scheduler
{
    protected bool $booted = false;

    /**
     * @var array<string,array{interval:int,display:string}>
     */
    private array $schedules = [];

    /**
     * @var array<string,array{recurrence:string,callback:callable,args:array}>
     */
    private array $recurringJobs = [];

    /**
     * @var array<int,array{hook:string,timestamp:int,callback:callable,args:array}>
     */
    private array $onceJobs = [];

    /**
     * Register a custom interval, merged into WordPress's cron_schedules filter on boot.
     */
    public function addSchedule(string $name, int $intervalSeconds, string $display): self
    {
        $this->schedules[$name] = ['interval' => $intervalSeconds, 'display' => $display];

        return $this;
    }

    /**
     * Register a recurring job that fires on the given hook at the given recurrence.
     */
    public function job(string $hook, string $recurrence, callable $callback, array $args = []): self
    {
        $this->recurringJobs[$hook] = compact('recurrence', 'callback', 'args');

        return $this;
    }

    /**
     * Register a one-off job that fires once at the given timestamp.
     */
    public function once(string $hook, int $timestamp, callable $callback, array $args = []): self
    {
        $this->onceJobs[] = compact('hook', 'timestamp', 'callback', 'args');

        return $this;
    }

    /**
     * Wire the cron_schedules filter, register job callbacks, and schedule pending events; safe to call repeatedly.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        add_filter('cron_schedules', fn (array $schedules): array => array_merge($schedules, $this->schedules));

        foreach ($this->recurringJobs as $hook => $job) {
            add_action($hook, $job['callback'], 10, \count($job['args']));

            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time(), $job['recurrence'], $hook, $job['args']);
            }
        }

        foreach ($this->onceJobs as $job) {
            add_action($job['hook'], $job['callback'], 10, \count($job['args']));

            if (!wp_next_scheduled($job['hook'])) {
                wp_schedule_single_event($job['timestamp'], $job['hook'], $job['args']);
            }
        }
    }

    /**
     * Clear the scheduled event for a single hook.
     */
    public function unschedule(string $hook): void
    {
        wp_clear_scheduled_hook($hook);
    }

    /**
     * Clear scheduled events for every registered recurring and one-off job.
     */
    public function clearAll(): void
    {
        foreach (array_keys($this->recurringJobs) as $hook) {
            $this->unschedule($hook);
        }

        foreach ($this->onceJobs as $job) {
            $this->unschedule($job['hook']);
        }
    }
}
