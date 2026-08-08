<?php

namespace BitApps\WPKit\Http\Router;

use BitApps\WPKit\Http\RequestType;
use BitApps\WPKit\Http\Response;

if (!\defined('ABSPATH')) {
    exit;
}

class StaticRouter
{
    private Router $router;

    private string $pageName;

    private array $rewriteRules = [];

    private array $queryVars = [];

    private string $content = '';

    public function __construct(string $pageName, string $activationHook, string $deactivationHook, ?Router $router = null)
    {
        $this->pageName = trim($pageName, '/');
        $this->router   = $router ?: Router::instance(RequestType::STATIC_PAGE, $this->pageName);
        $this->registerHooks($activationHook, $deactivationHook);
    }

    public function flushOnActivate(): void
    {
        $this->registerRewriteRules();
        flush_rewrite_rules();
    }

    public function flushOnDeactivate(): void
    {
        flush_rewrite_rules();
    }

    public function registerRewriteRules(): void
    {
        $this->processRoutes();

        if (empty($this->rewriteRules)) {
            return;
        }

        foreach ($this->rewriteRules as $regex => $query) {
            add_rewrite_rule($regex, $query, 'top');
        }

        $this->maybeFlushRewriteRules();
    }

    public function addQueryVars($vars): array
    {
        return array_merge($vars, $this->queryVars);
    }

    public function handleRequest(): void
    {
        $requestPath = sanitize_url((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        $method      = strtoupper(sanitize_text_field($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        foreach ($this->router->getRoutes() as $route) {
            if (!\in_array($method, $route->getMethods(), true)) {
                continue;
            }

            if ($this->isRouteMatched($route, $requestPath)) {
                $result = $route->handleRequest();
                if (Response::ERROR === Response::getStatus()) {
                    return;
                }

                $this->content = (new Emitter\StaticResponseEmitter())->emit([
                    'data' => ['data' => $result],
                ]);

                // this filter needs to be added here to avoid affecting other routes
                add_filter('the_content', [$this, 'renderContent']);

                return;
            }
        }
    }

    public function renderContent(string $content): string
    {
        return $content . ($this->content ?? '');
    }

    public function loadRoutesFromFile($filePath): void
    {
        $this->router->registerFile($filePath);
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public static function isRewriteExists(?string $path = '', ?array $rewriteRules = null): bool
    {
        if (empty($path) && empty($rewriteRules)) {
            return false;
        }

        $rules = get_option('rewrite_rules');
        if (!$rules) {
            return false;
        }

        $rulesToCheck = $path ? ['^' . trim($path, '/')] : array_keys($rewriteRules);
        foreach ($rulesToCheck as $rule) {
            if (!isset($rules[$rule])) {
                return false;
            }
        }

        return true;
    }

    public function maybeFlushRewriteRules(): void
    {
        if (empty($this->rewriteRules) || self::isRewriteExists('', $this->rewriteRules)) {
            return;
        }

        flush_rewrite_rules();
    }

    private function registerHooks(string $activationHook, string $deactivationHook): void
    {
        add_action($activationHook, [$this, 'flushOnActivate']);
        add_action($deactivationHook, [$this, 'flushOnDeactivate']);
        add_action('init', [$this, 'registerRewriteRules']);
        add_action('query_vars', [$this, 'addQueryVars']);
        add_action('template_redirect', [$this, 'handleRequest']);
    }

    private function processRoutes(): void
    {
        $ruleSet = new RewriteRuleSet($this->pageName);
        foreach ($this->router->getRoutes() as $route) {
            $ruleSet->addPath($this->routePath($route));
        }

        $this->rewriteRules = $ruleSet->rules();
        $this->queryVars    = $ruleSet->queryVars();
    }

    private function routePath(RouteRegister $route): string
    {
        $prefix = trim((string) $route->getRoutePrefix(), '/');
        $path   = trim((string) $route->getPath(), '/');

        return $prefix === '' ? $path : $prefix . '/' . $path;
    }

    private function isRouteMatched(RouteRegister $route, string $requestPath): bool
    {
        $path     = $this->pageName . '/' . $this->routePath($route);
        $compiled = RoutePattern::compile($path);
        $pattern  = $compiled === null ? preg_quote($path, '~') : $compiled['regex'];

        if (!preg_match('~^/' . $pattern . '/?$~', $requestPath, $matches)) {
            return false;
        }

        foreach ($matches as $param => $value) {
            if (\is_string($param)) {
                $route->setRouteParamValue($param, $value);
            }
        }

        return true;
    }
}
