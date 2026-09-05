<?php

namespace BitApps\WPKit\Http\Router;

/**
 * Resolves middleware aliases to validated, cached instances; fails closed on misconfiguration.
 */
final class MiddlewareRegistry
{
    private $_middlewares = [];

    private array $_resolved = [];

    public function register($middlewares): void
    {
        $this->_middlewares = $middlewares;
        $this->_resolved    = [];
    }

    public function resolve($name)
    {
        if (isset($this->_resolved[$name])) {
            return $this->_resolved[$name];
        }

        if (!isset($this->_middlewares[$name])) {
            throw new MiddlewareConfigurationException("Middleware [{$name}] is not registered.");
        }

        $middleware = $this->_middlewares[$name];
        if (!class_exists($middleware)) {
            throw new MiddlewareConfigurationException("Middleware class [{$middleware}] does not exist.");
        }

        if (!method_exists($middleware, 'handle')) {
            throw new MiddlewareConfigurationException("Middleware [{$middleware}] must define handle().");
        }

        return $this->_resolved[$name] = new $middleware();
    }
}
