<?php

namespace BitApps\WPKit\Tests\Cron;

use BitApps\WPKit\Cron\Scheduler;
use BitApps\WPKit\Tests\TestCase;
use WpKitTestState;

final class SchedulerTest extends TestCase
{
    public function testBootRegistersActionAndSchedulesOnce(): void
    {
        $s = new Scheduler();
        $s->job('demo_gc', 'daily', function () {});
        $s->boot();
        $s->boot(); // idempotent

        $this->assertArrayHasKey('demo_gc', WpKitTestState::$cron);
        // WpKitTestState::$actions is keyed [hook => [ {callback,priority,acceptedArgs}, ... ]].
        // Assert the hook has exactly one registered callback despite two boot() calls.
        $this->assertArrayHasKey('demo_gc', WpKitTestState::$actions);
        $this->assertCount(1, WpKitTestState::$actions['demo_gc']);
    }

    public function testCustomScheduleRegistered(): void
    {
        $s = (new Scheduler())->addSchedule('every_five', 300, 'Every Five');
        $s->boot();

        $schedules = apply_filters('cron_schedules', []);

        $this->assertSame(300, $schedules['every_five']['interval']);
    }

    public function testClearAllUnschedules(): void
    {
        $s = new Scheduler();
        $s->job('demo_gc', 'daily', function () {});
        $s->boot();
        $s->clearAll();

        $this->assertArrayNotHasKey('demo_gc', WpKitTestState::$cron);
    }

    public function testOnceSchedulesSingleEventAndGuardsReentry(): void
    {
        $s = new Scheduler();
        $s->once('demo_once', 1700000000, function () {});
        $s->boot();
        $s->boot(); // idempotent

        $this->assertSame(1700000000, WpKitTestState::$cron['demo_once']);
        $this->assertCount(1, WpKitTestState::$actions['demo_once']);
    }

    public function testUnscheduleClearsSingleHookOnly(): void
    {
        $s = new Scheduler();
        $s->job('demo_gc', 'daily', function () {});
        $s->job('demo_other', 'hourly', function () {});
        $s->boot();
        $s->unschedule('demo_gc');

        $this->assertArrayNotHasKey('demo_gc', WpKitTestState::$cron);
        $this->assertArrayHasKey('demo_other', WpKitTestState::$cron);
    }
}
