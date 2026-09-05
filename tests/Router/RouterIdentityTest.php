<?php

namespace BitApps\WPKit\Tests\Router;

use BitApps\WPKit\Http\Router\RouteBase;
use BitApps\WPKit\Http\Router\Router;
use BitApps\WPKit\Http\Router\StaticRouter;
use BitApps\WPKit\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RouterIdentityTest extends TestCase
{
    public function testInstanceHonoursRequestedTypeOverCurrentRouter(): void
    {
        new Router('ajax', 'example', 'v1');

        $static = Router::instance('static', 'landing');

        assertSameValue('static', $static->getRequestType(), 'instance() returned a router of the wrong type');
    }

    public function testRoutersOfDifferentTypesCoexistByType(): void
    {
        $ajax = new Router('ajax', 'example', 'v1');
        $api  = new Router('api', 'example', 'v1');

        assertSameValue($ajax, Router::instance('ajax'), 'ajax router was lost from the registry');
        assertSameValue($api, Router::instance('api'), 'api router was lost from the registry');
    }

    public function testRouteDeclarationsBindToTheCurrentRouter(): void
    {
        $ajax = new Router('ajax', 'example', 'v1');
        (new RouteBase())->get('a', static function () {
            return 'a';
        });
        $api = new Router('api', 'example', 'v1');
        (new RouteBase())->get('b', static function () {
            return 'b';
        });

        assertSameValue(1, \count($ajax->getRoutes()), 'route did not bind to the then-current ajax router');
        assertSameValue(1, \count($api->getRoutes()), 'route did not bind to the then-current api router');
    }

    public function testStaticRouterUsesInjectedRouterVerbatim(): void
    {
        $router = new Router('static', 'landing', null);
        new Router('ajax', 'other', null);
        $transport = new StaticRouter('landing', 'a_hook', 'd_hook', $router);

        assertSameValue($router, $transport->getRouter(), 'injected router was not used verbatim');
    }
}
