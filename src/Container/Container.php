<?php

namespace BitApps\WPKit\Container;

use BitApps\WPKit\Container\Exceptions\BindingResolutionException;
use Closure;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Lightweight IoC container: bind/singleton/instance/make with constructor autowiring.
 */
class Container
{
    /**
     * @var array<string,array{concrete:mixed,shared:bool}>
     */
    protected $bindings = [];

    /**
     * @var array<string,object>
     */
    protected $instances = [];

    /**
     * @var array<string,string>
     */
    protected $aliases = [];

    /**
     * @var array<string,bool> concretes currently being built, guards against circular dependencies
     */
    protected $buildStack = [];

    /**
     * Register a binding, optionally as a shared (singleton) instance.
     *
     * @param null|Closure|string $concrete
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete ?? $abstract, 'shared' => $shared];
    }

    /**
     * Register a binding that resolves to a single shared instance.
     *
     * @param null|Closure|string $concrete
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing object instance as a shared binding.
     */
    public function instance(string $abstract, object $instance): object
    {
        return $this->instances[$abstract] = $instance;
    }

    /**
     * Register an alias for an abstract so it can be resolved under another name.
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Check whether an abstract has an explicit binding or shared instance.
     */
    public function bound(string $abstract): bool
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;

        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Check whether an abstract is resolvable, either bound or an existing class.
     */
    public function has(string $abstract): bool
    {
        return $this->bound($abstract) || class_exists($abstract);
    }

    /**
     * PSR-11-style alias for make(), resolving an entry by id.
     *
     * @return mixed
     */
    public function get(string $id)
    {
        return $this->make($id);
    }

    /**
     * Resolve an abstract into a concrete instance, autowiring constructor dependencies as needed.
     *
     * @param array<string,mixed> $parameters
     *
     * @return mixed
     */
    public function make(string $abstract, array $parameters = [])
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding  = $this->bindings[$abstract] ?? null;
        $concrete = $binding['concrete']       ?? $abstract;

        $object = $concrete instanceof Closure
            ? $concrete($this, $parameters)
            : $this->build($concrete, $parameters);

        if ($binding !== null && $binding['shared']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Instantiate a concrete class via reflection, resolving constructor parameters recursively.
     *
     * @param array<string,mixed> $parameters
     *
     * @return mixed
     */
    protected function build(string $concrete, array $parameters = [])
    {
        // Re-entering a concrete already on the stack means it depends on itself, directly or transitively.
        if (isset($this->buildStack[$concrete])) {
            throw new BindingResolutionException("Circular dependency [{$concrete}].");
        }

        $this->buildStack[$concrete] = true;

        try {
            $reflector = new ReflectionClass($concrete);
            if (!$reflector->isInstantiable()) {
                throw new BindingResolutionException("Target [{$concrete}] is not instantiable.");
            }

            $constructor = $reflector->getConstructor();
            if ($constructor === null) {
                return new $concrete();
            }

            $args = [];
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if (\array_key_exists($name, $parameters)) {
                    $args[] = $parameters[$name];

                    continue;
                }
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $args[] = $this->make($type->getName());

                    continue;
                }
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();

                    continue;
                }
                if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
                    $args[] = null;

                    continue;
                }

                throw new BindingResolutionException("Unresolvable dependency [\${$name}] in class [{$concrete}].");
            }

            return $reflector->newInstanceArgs($args);
        } finally {
            unset($this->buildStack[$concrete]);
        }
    }
}
