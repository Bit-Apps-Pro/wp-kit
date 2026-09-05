<?php

namespace BitApps\WPKit\Tests\Router;

use BitApps\WPKit\Http\Request\Request;
use BitApps\WPKit\Http\Response;
use BitApps\WPKit\Http\Router\RouteBase;
use BitApps\WPKit\Http\Router\Router;
use BitApps\WPKit\Tests\TestCase;
use RuntimeException;

final class ContractDenyingMiddleware
{
    public function handle()
    {
        return Response::error(['reason' => 'denied'], 403);
    }
}

final class ContractAllowingMiddleware
{
    public static $role;

    public function handle(Request $request, $role)
    {
        self::$role = $role;

        return true;
    }
}

final class ContractMiddlewareWithoutHandle
{
}

final class ContractMiddlewareProtectedAction
{
    public static $executed = false;

    public function run()
    {
        self::$executed = true;

        return 'executed';
    }
}

final class ContractDeniedRequest extends Request
{
    public function authorize()
    {
        return false;
    }

    public function failedAuthorizationMessage()
    {
        return 'Custom authorization failure';
    }
}

final class ContractRequestDenyingMiddleware
{
    public static $executed = false;

    public function handle(ContractDeniedRequest $request)
    {
        self::$executed = true;

        return true;
    }
}

/**
 * @internal
 *
 * @coversNothing
 */
final class MiddlewareTest extends TestCase
{
    public function testRouterMiddlewareDenyingMiddlewarePreventsActionExecution(): void
    {
        ContractMiddlewareProtectedAction::$executed = false;
        $router                                      = new Router('static', 'contract-test', null);
        $router->setMiddlewares(['deny' => ContractDenyingMiddleware::class]);
        $route = (new RouteBase())
            ->middleware('deny')
            ->get('protected', [ContractMiddlewareProtectedAction::class, 'run']);

        $route->handleRequest();

        assertTest(!ContractMiddlewareProtectedAction::$executed, 'protected action executed after middleware denial');
    }

    public function testRouterMiddlewareParametersReachAnAllowingMiddleware(): void
    {
        ContractMiddlewareProtectedAction::$executed = false;
        ContractAllowingMiddleware::$role            = null;
        $router                                      = new Router('static', 'contract-test', null);
        $router->setMiddlewares(['allow' => ContractAllowingMiddleware::class]);
        $route = (new RouteBase())
            ->middleware('allow:administrator')
            ->get('protected', [ContractMiddlewareProtectedAction::class, 'run']);

        $route->handleRequest();

        assertSameValue('administrator', ContractAllowingMiddleware::$role, 'middleware parameter was not passed');
        assertTest(ContractMiddlewareProtectedAction::$executed, 'allowed action did not execute');
    }

    public function testRouterMiddlewareDeniedRequestInjectionIsTerminal(): void
    {
        ContractMiddlewareProtectedAction::$executed = false;
        ContractRequestDenyingMiddleware::$executed  = false;
        $router                                      = new Router('static', 'contract-test', null);
        $router->setMiddlewares(['request-deny' => ContractRequestDenyingMiddleware::class]);
        $route = (new RouteBase())
            ->middleware('request-deny')
            ->get('protected', [ContractMiddlewareProtectedAction::class, 'run']);

        $route->handleRequest();

        assertTest(!ContractRequestDenyingMiddleware::$executed, 'middleware executed after request authorization failed');
        assertTest(!ContractMiddlewareProtectedAction::$executed, 'action executed after request authorization failed');
    }

    public function testRouterMiddlewareDenialStopsLaterMiddlewareImmediately(): void
    {
        ContractMiddlewareProtectedAction::$executed = false;
        ContractAllowingMiddleware::$role            = null;
        $router                                      = new Router('static', 'contract-test', null);
        $router->setMiddlewares([
            'deny'  => ContractDenyingMiddleware::class,
            'allow' => ContractAllowingMiddleware::class,
        ]);
        $route = (new RouteBase())
            ->middleware('deny', 'allow:administrator')
            ->get('protected', [ContractMiddlewareProtectedAction::class, 'run']);

        $route->handleRequest();

        assertSameValue(null, ContractAllowingMiddleware::$role, 'middleware after a denial was still entered');
        assertTest(!ContractMiddlewareProtectedAction::$executed, 'action executed after middleware denial');
    }

    public function testRouterMiddlewareMissingAliasesFailClosed(): void
    {
        ContractMiddlewareProtectedAction::$executed = false;
        $router                                      = new Router('static', 'contract-test', null);
        $router->setMiddlewares([]);
        $route = (new RouteBase())
            ->middleware('missing')
            ->get('protected', [ContractMiddlewareProtectedAction::class, 'run']);

        $route->handleRequest();

        assertTest(!ContractMiddlewareProtectedAction::$executed, 'action executed without configured middleware');
        assertSameValue('MIDDLEWARE_CONFIGURATION', Response::getCode(), 'configuration error code was not returned');
    }

    public function testRouterMiddlewareDirectDenialReturnsFalseWithoutThrowing(): void
    {
        $router = new Router('static', 'contract-test', null);
        $router->setMiddlewares(['deny' => ContractDenyingMiddleware::class]);
        $route = (new RouteBase())
            ->middleware('deny')
            ->get('protected', [ContractMiddlewareProtectedAction::class, 'run']);

        assertSameValue(false, $route->handleMiddleware(), 'direct middleware denial did not report false');
    }

    public function testRouterMiddlewareDirectAllowanceReturnsTrue(): void
    {
        $router = new Router('static', 'contract-test', null);
        $router->setMiddlewares(['allow' => ContractAllowingMiddleware::class]);
        $route = (new RouteBase())
            ->middleware('allow:admin')
            ->get('protected', [ContractMiddlewareProtectedAction::class, 'run']);

        assertSameValue(true, $route->handleMiddleware(), 'direct middleware allowance did not report true');
    }

    public function testRouterMiddlewareMissingClassesAreRejected(): void
    {
        $router = new Router('static', 'contract-test', null);
        $router->setMiddlewares(['missing-class' => 'MissingContractMiddlewareClass']);

        assertThrows(
            RuntimeException::class,
            function () use ($router) {
                $router->getRegisteredMiddleware('missing-class');
            },
            'missing middleware class was accepted',
        );
    }

    public function testRouterMiddlewareClassesWithoutHandleAreRejected(): void
    {
        $router = new Router('static', 'contract-test', null);
        $router->setMiddlewares(['missing-handle' => ContractMiddlewareWithoutHandle::class]);

        assertThrows(
            RuntimeException::class,
            function () use ($router) {
                $router->getRegisteredMiddleware('missing-handle');
            },
            'middleware without handle() was accepted',
        );
    }

    public function testRouterMiddlewareResolvedInstancesAreCachedPerRouter(): void
    {
        $router = new Router('static', 'contract-test', null);
        $router->setMiddlewares(['allow' => ContractAllowingMiddleware::class]);

        $first  = $router->getRegisteredMiddleware('allow');
        $second = $router->getRegisteredMiddleware('allow');

        assertTest($first === $second, 'middleware resolver returned different instances');
    }
}
