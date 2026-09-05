<?php

namespace BitApps\WPKit\Tests\Router;

use BitApps\WPKit\Http\Router\Route;
use BitApps\WPKit\Http\Router\RouteBase;
use BitApps\WPKit\Http\Router\Router;
use BitApps\WPKit\Http\Router\RouteRegister;
use BitApps\WPKit\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RouteDefinitionTest extends TestCase
{
    public function testRouteDefinitionRouterMetadataRemainsObservable(): void
    {
        $router = new Router('api', 'example', 'v2');

        assertSameValue('api', $router->getRequestType(), 'request type changed');
        assertSameValue('example', $router->getNamespace(), 'namespace changed');
        assertSameValue('v2/', $router->getVersion(), 'version path changed');
        assertSameValue('example/v2', $router->getAjaxPrefix(), 'AJAX prefix changed');
    }

    public function testRouteDefinitionFluentAttributesAreRetainedByTheRoute(): void
    {
        $router = new Router('ajax', 'example', 'v1');
        $action = function () {
            return 'ok';
        };
        $route = (new RouteBase())
            ->prefix('admin')
            ->middleware('auth', 'capability:edit_posts')
            ->noAuth()
            ->ignoreToken()
            ->match('get,post', 'entries', $action)
            ->name('entry.index');

        assertInstanceOf(RouteRegister::class, $route, 'route builder did not return a registered route');
        assertSameValue(['GET', 'POST'], $route->getMethods(), 'HTTP methods were not normalized');
        assertSameValue('entries', $route->getPath(), 'route path changed');
        assertSameValue($action, $route->getAction(), 'route action changed');
        assertSameValue('entry.index', $route->getName(), 'route name changed');
        assertSameValue('admin', $route->getRoutePrefix(), 'route prefix changed');
        assertSameValue(['auth', 'capability:edit_posts'], $route->getMiddleware(), 'route middleware changed');
        assertSameValue(true, $route->isNoAuth(), 'public AJAX flag changed');
        assertSameValue(true, $route->isTokenIgnored(), 'token flag changed');
        assertSameValue([$route], $router->getRoutes(), 'route was not added to its router');
    }

    public function testRouteDefinitionRouteLevelMiddlewareExtendsGroupMiddleware(): void
    {
        new Router('ajax', 'example', null);
        $route = (new RouteBase())
            ->middleware('group-auth')
            ->get('entries', function () {
                return 'ok';
            })
            ->middleware('route-audit');

        assertSameValue(
            ['group-auth', 'route-audit'],
            $route->getMiddleware(),
            'route middleware did not extend base middleware',
        );
    }

    public function testRouteDefinitionStaticFacadeRegistersRoutes(): void
    {
        $router = new Router('ajax', 'example', null);
        $route  = Route::get('facade', function () {
            return 'ok';
        });

        assertInstanceOf(RouteRegister::class, $route, 'static route facade did not return a route');
        assertSameValue($route, $router->getRoute(0), 'static route facade did not register on the active router');
    }

    public function testRouteDefinitionGroupsApplySharedPrefixAndMiddleware(): void
    {
        $router = new Router('ajax', 'example', null);

        (new RouteBase())
            ->prefix('admin')
            ->middleware('auth')
            ->group(function () {
                Route::get('entries', function () {
                    return 'ok';
                });
            });

        $route = $router->getRoute(0);
        assertSameValue('admin', $route->getRoutePrefix(), 'group prefix was not inherited');
        assertSameValue(['auth'], $route->getMiddleware(), 'group middleware was not inherited');
    }

    public function testRouteDefinitionPlaceholdersExposeRegexMetadata(): void
    {
        new Router('ajax', 'example', null);
        $route = (new RouteBase())->get('entries/{id}/{slug?}', function () {
            return 'ok';
        });

        $regex = $route->regex();

        assertTest(\is_string($regex), 'placeholder route did not produce a regex');
        assertSameValue(['required' => true], $route->getRouteParam('id'), 'required placeholder metadata changed');
        assertSameValue(['required' => false], $route->getRouteParam('slug'), 'optional placeholder metadata changed');
        assertSameValue(false, $route->getRouteParam('missing'), 'missing placeholder contract changed');
    }

    public function testRouteDefinitionRouteParameterValuesRetainScalarZero(): void
    {
        new Router('ajax', 'example', null);
        $route = (new RouteBase())->get('entries/{id}', function () {
            return 'ok';
        });
        $route->setRouteParamValue('id', '0');

        assertSameValue('0', $route->getRouteParamValue('id'), 'zero route parameter was not retained');
        assertSameValue(['id' => '0'], $route->getRouteParamValues(), 'route parameter collection changed');
    }

    public function testRouteDefinitionRegisteredRouteLookupPreservesNames(): void
    {
        $router = new Router('ajax', 'example', null);
        $route  = (new RouteBase())->get('entries', function () {
            return 'ok';
        });
        $router->addRegisteredRoute('entry.index', $route);

        assertSameValue($route, $router->getRegisteredRoute('entry.index'), 'named route lookup changed');
        assertSameValue(['entry.index' => $route], $router->getRegisteredRoutes(), 'named route collection changed');
        assertSameValue(null, $router->getRegisteredRoute('missing'), 'missing named route contract changed');
    }
}
