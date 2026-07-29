<?php

namespace BitApps\WPKit\Tests\Http;

use BitApps\WPKit\Http\Request\Request;
use BitApps\WPKit\Tests\TestCase;
use WP_REST_Request;
use WpKitTestState;

/**
 * @internal
 *
 * @coversNothing
 */
final class RequestTest extends TestCase
{
    public function testRequestQueryBodyAndFilesRemainSeparatelyObservable(): void
    {
        $_GET                    = ['query' => 'value', 'shared' => 'query'];
        $_POST                   = ['body' => 'value', 'shared' => 'body'];
        $_FILES                  = ['upload' => ['name' => 'contract.txt']];
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $request                 = new Request();

        assertSameValue(['query' => 'value', 'shared' => 'query'], $request->queryParams(), 'query values changed');
        assertSameValue(['body' => 'value', 'shared' => 'body'], $request->body(), 'body values changed');
        assertSameValue($_FILES, $request->files(), 'file collection changed');
        assertSameValue('query', $request->get('shared'), 'request source precedence changed');
    }

    public function testRequestAccessorsMagicPropertiesAndArrayAccessShareAttributes(): void
    {
        $_GET    = ['name' => 'Ada'];
        $request = new Request();

        assertSameValue('Ada', $request->get('name'), 'get accessor changed');
        assertSameValue('fallback', $request->get('missing', 'fallback'), 'default accessor changed');
        assertSameValue(true, $request->has('name'), 'has accessor changed');
        assertSameValue('Ada', $request->name, 'magic getter changed');
        assertSameValue('Ada', $request['name'], 'array getter changed');

        $request->role     = 'admin';
        $request['active'] = true;
        unset($request->role);

        assertSameValue(false, isset($request->role), 'magic unset changed');
        assertSameValue(true, $request['active'], 'array setter changed');
        assertSameValue(['name' => 'Ada', 'active' => true], $request->jsonSerialize(), 'serialized attributes changed');
    }

    public function testRequestExceptReturnsAFilteredCopy(): void
    {
        $_GET    = ['one' => 1, 'two' => 2, 'three' => 3];
        $request = new Request();

        assertSameValue(['one' => 1, 'three' => 3], $request->except('two'), 'except result changed');
        assertSameValue(2, $request->get('two'), 'except mutated the request');
    }

    public function testRequestMethodAndContentTypeReflectServerMetadata(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['CONTENT_TYPE']   = 'application/json';
        $request                   = new Request();

        assertSameValue('PATCH', $request->method(), 'request method changed');
        assertSameValue('json', $request->contentType(), 'content type changed');
    }

    public function testRequestRESTRequestReplacesGlobalsAndRetainsSourcePrecedence(): void
    {
        $_GET    = ['old' => 'query'];
        $_POST   = ['old_body' => 'body'];
        $request = new Request();
        $request->setApiRequest(
            new WP_REST_Request(
                ['body' => 'value', 'shared' => 'body'],
                ['query' => 'value', 'shared' => 'query'],
                [],
                ['json' => 'value'],
            ),
        );

        assertSameValue(
            [
                'query'  => 'value',
                'shared' => 'query',
                'body'   => 'value',
                'json'   => 'value',
            ],
            $request->all(),
            'REST request hydration or precedence changed',
        );
    }

    public function testRequestSuccessfulValidationReturnsValidatedValues(): void
    {
        $_POST                   = ['name' => 'Ada'];
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $request                 = new Request();

        $validated = $request->validate(['name' => ['required']]);

        assertSameValue(['name' => 'Ada'], $validated, 'successful validation result changed');
        assertSameValue(null, WpKitTestState::$sentJson, 'successful validation emitted an error response');
    }
}
