<?php

namespace BitApps\WPKit\Http\Router;

use BitApps\WPKit\Http\RequestType;

final class Router
{
    private $_routes = [];

    private $_registeredRoutes = [];

    private $_middlewareRegistry;

    private $_namespace;

    private $_version;

    private $_requestType;

    private static $_instance;

    // keyed by type only — two routers of the same type share one slot, last constructed wins
    private static $_registry = [];

    public function __construct($type, $namespace, $version)
    {
        $this->_namespace          = $namespace;
        $this->_version            = $version;
        $this->_requestType        = $type;
        $this->_middlewareRegistry = new MiddlewareRegistry();
        self::$_instance           = $this;
        self::$_registry[$type]    = $this;
    }

    public function getRequestType()
    {
        return $this->_requestType;
    }

    public function getVersion()
    {
        return empty($this->_version) ? '' : $this->_version . '/';
    }

    public function getNamespace()
    {
        return $this->_namespace;
    }

    public function getAjaxPrefix()
    {
        return $this->getNamespace() . (empty($this->_version) ? '' : '/' . $this->_version);
    }

    public function getRoutes()
    {
        return $this->_routes;
    }

    public function getRoute($routeIndex)
    {
        return isset($this->_routes[$routeIndex]) ? $this->_routes[$routeIndex] : null;
    }

    public function addRoute(RouteRegister $route)
    {
        $this->_routes[] = $route;
    }

    public function addRegisteredRoute($name, RouteRegister $route)
    {
        $this->_registeredRoutes[$name] = $route;
    }

    public function getRegisteredRoute($routeName)
    {
        return isset($this->_registeredRoutes[$routeName]) ? $this->_registeredRoutes[$routeName] : null;
    }

    public function getRegisteredRoutes()
    {
        return $this->_registeredRoutes;
    }

    public static function instance($type = null, $namespace = null, $version = null)
    {
        if ($type === null) {
            if (\is_null(self::$_instance)) {
                self::$_instance = new self(RequestType::AJAX, $namespace, $version);
            }

            return self::$_instance;
        }

        if (isset(self::$_registry[$type])) {
            return self::$_registry[$type];
        }

        // creates, registers, AND makes the new router current — declare routes before constructing transports
        return new self($type, $namespace, $version);
    }

    public static function reset()
    {
        self::$_instance = null;
        self::$_registry = [];
    }

    public function registerFile($routeFile)
    {
        self::$_instance = $this;

        include_once $routeFile;
    }

    public function register()
    {
        if ($this->getRequestType() === RequestType::AJAX) {
            $ajaxRouter = new AjaxRouter($this);
            $ajaxRouter->registerRoutes();
        } elseif ($this->getRequestType() === RequestType::API) {
            $ajaxRouter = new APIRouter($this);
            $ajaxRouter->registerRoutes();
        }
    }

    public function setMiddlewares($middlewares)
    {
        $this->_middlewareRegistry->register($middlewares);
    }

    public function getRegisteredMiddleware($name)
    {
        return $this->_middlewareRegistry->resolve($name);
    }
}
