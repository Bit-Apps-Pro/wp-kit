<?php

namespace BitApps\WPKit\Tests\Router;

use BitApps\WPKit\Http\Request\Request;
use BitApps\WPKit\Http\Response;
use BitApps\WPKit\Http\Router\RouteBase;
use BitApps\WPKit\Http\Router\Router;
use BitApps\WPKit\Tests\TestCase;
use ReflectionParameter;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

final class ContractSideEffectDependency
{
    public static $constructed = false;

    public function __construct()
    {
        self::$constructed = true;
    }
}

final class ContractAuthorizationProtectedAction
{
    public static $executed = false;

    public function run(ContractDeniedRequest $request, ContractSideEffectDependency $dependency)
    {
        self::$executed = true;

        return 'executed';
    }
}

final class ContractInvalidRequest extends Request
{
    public function rules()
    {
        return [
            'required_field' => ['required'],
        ];
    }

    public function messages()
    {
        return [
            'required_field.required' => 'Required field is missing',
        ];
    }

    public function attributes()
    {
        return [
            'required_field' => 'Required field',
        ];
    }
}

final class ContractValidationProtectedAction
{
    public static $executed = false;

    public function run(ContractInvalidRequest $request)
    {
        self::$executed = true;

        return 'executed';
    }
}

final class ContractRestRequestAction
{
    public static $values;

    public function run(Request $request)
    {
        self::$values = $request->all();

        return 'executed';
    }
}

final class ContractAuthorizedRequest extends Request
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return ['required_field' => ['required']];
    }
}

final class ContractAuthorizedAction
{
    public function run(ContractAuthorizedRequest $request)
    {
        return 'authorized-ok';
    }
}

/**
 * @internal
 *
 * @coversNothing
 */
final class DispatchTest extends TestCase
{
    public function testRouteDispatchAuthorizedValidRequestReachesItsAction(): void
    {
        $_GET['required_field']     = 'present';
        $_POST['required_field']    = 'present';
        $_REQUEST['required_field'] = 'present';
        new Router('static', 'contract-test', null);
        $route = (new RouteBase())->get('open', [ContractAuthorizedAction::class, 'run']);

        assertSameValue('authorized-ok', $route->handleRequest(), 'authorized valid request did not reach its action');
    }

    public function testRouteDispatchDirectParamResolutionDoesNotLeakInternalExceptions(): void
    {
        new Router('static', 'contract-test', null);
        $route = (new RouteBase())->get(
            'protected',
            [ContractAuthorizationProtectedAction::class, 'run'],
        );

        $value = $route->getParamValue(new ReflectionParameter([ContractAuthorizationProtectedAction::class, 'run'], 0));

        assertSameValue(null, $value, 'denied direct param resolution did not return null');
        assertSameValue('NOT_AUTHORIZED', Response::getCode(), 'denial response was not recorded for direct callers');
    }

    public function testRouteDispatchActionExceptionDoesNotLeakTheOutputBuffer(): void
    {
        $baseline = ob_get_level();
        new Router('static', 'contract-test', null);
        $route = (new RouteBase())->get('boom', function () {
            throw new RuntimeException('boom');
        });

        assertThrows(
            RuntimeException::class,
            function () use ($route) {
                $route->handleRequest();
            },
            'action exception did not propagate',
        );
        assertSameValue($baseline, ob_get_level(), 'output buffer leaked after an action exception');
    }

    public function testRouteDispatchFailedAuthorizationStopsActionAndDependencyResolution(): void
    {
        ContractAuthorizationProtectedAction::$executed = false;
        ContractSideEffectDependency::$constructed      = false;
        new Router('static', 'contract-test', null);
        $route = (new RouteBase())->get(
            'protected',
            [ContractAuthorizationProtectedAction::class, 'run'],
        );

        $route->handleRequest();

        assertTest(!ContractAuthorizationProtectedAction::$executed, 'protected action executed after authorization failure');
        assertTest(!ContractSideEffectDependency::$constructed, 'dependency constructed after authorization failure');
        assertSameValue('Custom authorization failure', Response::getMessage(), 'custom authorization message was lost');
    }

    public function testRouteDispatchFailedValidationPreventsActionExecution(): void
    {
        ContractValidationProtectedAction::$executed = false;
        new Router('static', 'contract-test', null);
        $route = (new RouteBase())->get('protected', [ContractValidationProtectedAction::class, 'run']);

        $route->handleRequest();

        assertTest(!ContractValidationProtectedAction::$executed, 'protected action executed after validation failure');
        assertSameValue('VALIDATION', Response::getCode(), 'validation error code was not returned');
    }

    public function testRouteDispatchDirectRequestAccessBuildsTheBaseRequest(): void
    {
        new Router('static', 'contract-test', null);
        $route = (new RouteBase())->get('request', function () {
            return 'executed';
        });

        assertInstanceOf(Request::class, $route->getRequest(), 'base request was not created');
    }

    public function testRouteDispatchRESTDataHydratesAnInjectedRequest(): void
    {
        ContractRestRequestAction::$values = null;
        new Router('api', 'contract-test', 'v1');
        $route   = (new RouteBase())->get('rest-request', [ContractRestRequestAction::class, 'run']);
        $request = new WP_REST_Request(
            ['body_value' => 'body'],
            ['query_value' => 'query'],
            ['route_value' => 'route'],
            ['json_value'  => 'json'],
        );

        $response = $route->handleRequest($request);

        assertSameValue(
            [
                'query_value' => 'query',
                'body_value'  => 'body',
                'json_value'  => 'json',
                'route_value' => 'route',
            ],
            ContractRestRequestAction::$values,
            'REST values did not hydrate the injected request',
        );
        assertInstanceOf(WP_REST_Response::class, $response, 'API dispatch did not return a REST response');
    }

    public function testRouteDispatchMissingHandlersProduceAnErrorResponse(): void
    {
        new Router('static', 'contract-test', null);
        $route = (new RouteBase())->get('missing-action', ['MissingContractAction', 'run']);

        $route->handleRequest();

        assertSameValue('Route action doesn\'t exists', Response::getMessage(), 'missing action response was not generated');
    }

    public function testRouteDispatchClosureAuthorizationFailurePreventsInvocation(): void
    {
        $closureExecuted = false;
        new Router('static', 'contract-test', null);
        $route = (new RouteBase())->get(
            'closure-denied',
            function (ContractDeniedRequest $request) use (&$closureExecuted) {
                $closureExecuted = true;

                return 'executed';
            },
        );

        $route->handleRequest();

        assertTest(!$closureExecuted, 'closure executed after authorization failed');
    }

    public function testRouteDispatchClosureActionsReturnTheirData(): void
    {
        new Router('static', 'contract-test', null);
        $route = (new RouteBase())->get('closure', function () {
            return ['result' => 'executed'];
        });

        $response = $route->handleRequest();

        assertSameValue(['result' => 'executed'], $response, 'static dispatch did not return action data');
    }

    public function testRouteDispatchResponseMetadataResetsBetweenRequests(): void
    {
        Response::error([])->message('stale error');
        new Router('static', 'contract-test', null);
        $route = (new RouteBase())->get('response-reset', function () {
            return 'executed';
        });

        $route->handleRequest();

        assertSameValue(null, Response::getMessage(), 'stale response message leaked into the next request');
    }

    public function testRouteDispatchActionOutputIsCapturedAsAdditionalResponseData(): void
    {
        new Router('api', 'contract-test', 'v1');
        $route = (new RouteBase())->get('output', function () {
            echo 'diagnostic';

            return 'executed';
        });

        $response = $route->handleRequest(new WP_REST_Request());
        $data     = $response->get_data();

        assertSameValue('diagnostic', $data['additional'], 'buffered action output was not preserved');
        assertSameValue('executed', $data['data'], 'action response data was not preserved');
    }
}
