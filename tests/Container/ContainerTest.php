<?php

namespace BitApps\WPKit\Tests\Container;

use BitApps\WPKit\Container\Container;
use BitApps\WPKit\Container\Exceptions\BindingResolutionException;
use BitApps\WPKit\Tests\TestCase;

interface WpKitGreeter
{
    public function greet(): string;
}

class WpKitHello implements WpKitGreeter
{
    public function greet(): string
    {
        return 'hi';
    }
}

class WpKitConsumer
{
    public function __construct(public WpKitGreeter $g)
    {
    }
}

class NeedsScalar
{
    public function __construct(public string $name)
    {
    }
}

class WpKitCircularA
{
    public function __construct(public WpKitCircularB $b)
    {
    }
}

class WpKitCircularB
{
    public function __construct(public WpKitCircularA $a)
    {
    }
}

final class ContainerTest extends TestCase
{
    public function testBindResolvesFreshEachTime(): void
    {
        $c = new Container();
        $c->bind(WpKitGreeter::class, WpKitHello::class);
        $this->assertNotSame($c->make(WpKitGreeter::class), $c->make(WpKitGreeter::class));
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $c = new Container();
        $c->singleton(WpKitGreeter::class, WpKitHello::class);
        $this->assertSame($c->make(WpKitGreeter::class), $c->make(WpKitGreeter::class));
    }

    public function testAutowiresConstructorByTypeHint(): void
    {
        $c = new Container();
        $c->bind(WpKitGreeter::class, WpKitHello::class);
        $consumer = $c->make(WpKitConsumer::class);
        $this->assertSame('hi', $consumer->g->greet());
    }

    public function testInstanceAndClosureBinding(): void
    {
        $c = new Container();
        $c->instance('flag', (object) ['x' => 1]);
        $this->assertSame(1, $c->make('flag')->x);
        $c->bind('made', fn (Container $app) => new WpKitHello());
        $this->assertInstanceOf(WpKitHello::class, $c->make('made'));
    }

    public function testUnresolvableScalarThrows(): void
    {
        $c = new Container();
        $this->expectException(BindingResolutionException::class);
        $c->make(NeedsScalar::class);
    }

    public function testCircularDependencyThrows(): void
    {
        $c = new Container();
        $this->expectException(BindingResolutionException::class);
        $c->make(WpKitCircularA::class);
    }
}
