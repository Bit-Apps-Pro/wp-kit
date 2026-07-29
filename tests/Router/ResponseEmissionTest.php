<?php

namespace BitApps\WPKit\Tests\Router;

use BitApps\WPKit\Http\Response;
use BitApps\WPKit\Http\Router\RouteBase;
use BitApps\WPKit\Http\Router\Router;
use BitApps\WPKit\Tests\TestCase;
use WP_REST_Request;
use WP_REST_Response;
use WpKitTestState;

/**
 * @internal
 *
 * @coversNothing
 */
final class ResponseEmissionTest extends TestCase
{
    public function testApiDispatchReturnsRestResponseEnvelope(): void
    {
        new Router('api', 'contract-test', 'v1');
        $route = (new RouteBase())->get('entries', static function () {
            return ['result' => 'ok'];
        });

        $response = $route->handleRequest(new WP_REST_Request());

        assertInstanceOf(WP_REST_Response::class, $response, 'api dispatch did not return a WP_REST_Response');
        assertSameValue(['result' => 'ok'], $response->get_data()['data'], 'api envelope data changed');
        assertSameValue(200, $response->get_status(), 'api status changed');
    }

    public function testAjaxDispatchSendsJsonWithStatus(): void
    {
        new Router('ajax', 'contract-test', 'v1');
        $route = (new RouteBase())->get('entries', static function () {
            return Response::success(['result' => 'ok'], 201)->header('X-Test', 'yes');
        });

        $route->handleRequest();

        assertSameValue(201, WpKitTestState::$sentJson['status'], 'ajax http status changed');
        assertSameValue(['result' => 'ok'], WpKitTestState::$sentJson['data']['data'], 'ajax payload changed');
    }

    public function testNonStandardRouterTypeFallsBackToRawData(): void
    {
        new Router('cron', 'contract-test', null);
        $route = (new RouteBase())->get('job', static function () {
            return 'raw-output';
        });

        assertSameValue('raw-output', $route->handleRequest(), 'non-api/ajax dispatch no longer returns raw data');
    }
}
