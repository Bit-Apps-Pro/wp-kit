<?php

namespace BitApps\WPKit\Tests\Http;

use BitApps\WPKit\Http\Response;
use BitApps\WPKit\Tests\TestCase;
use InvalidArgumentException;

/**
 * @internal
 *
 * @coversNothing
 */
final class ResponseTest extends TestCase
{
    public function testResponseSuccessFactoryExposesDataMetadataAndChaining(): void
    {
        $response = Response::success(['id' => 42], 201)
            ->message('Created')
            ->code('ENTRY_CREATED')
            ->header('Location', '/entries/42');

        assertInstanceOf(Response::class, $response, 'success factory did not return a response');
        assertSameValue(Response::SUCCESS, $response->getStatus(), 'success status changed');
        assertSameValue(['id' => 42], $response->getData(), 'success data changed');
        assertSameValue('Created', $response->getMessage(), 'success message changed');
        assertSameValue('ENTRY_CREATED', $response->getCode(), 'success code changed');
        assertSameValue(201, $response->getHttpStatusCode(), 'success HTTP status changed');
        assertSameValue(['Location' => '/entries/42'], $response->getHeaders(), 'success headers changed');
    }

    public function testResponseErrorFactorySuppliesDefaultMetadata(): void
    {
        $response = Response::error(['field' => 'invalid']);

        assertSameValue(Response::ERROR, $response->getStatus(), 'error status changed');
        assertSameValue('ERROR', $response->getCode(), 'default error code changed');
        assertSameValue(400, $response->getHttpStatusCode(), 'default error HTTP status changed');
    }

    public function testResponseResetRemovesAllPreviousMetadata(): void
    {
        Response::error(['old' => true], 409)
            ->message('old')
            ->code('OLD')
            ->header('X-Old', 'yes');

        $response = Response::reset();

        assertInstanceOf(Response::class, $response, 'reset did not return a response');
        assertSameValue(null, Response::getStatus(), 'status was not reset');
        assertSameValue(null, Response::getData(), 'data was not reset');
        assertSameValue(null, Response::getMessage(), 'message was not reset');
        assertSameValue([], Response::getHeaders(), 'headers were not reset');
        assertSameValue(200, Response::getHttpStatusCode(), 'reset HTTP status fallback changed');
    }

    public function testResponseResetRotatesToAFreshInstance(): void
    {
        $before = Response::instance();
        Response::error(['old' => true])->message('old');

        $after = Response::reset();

        assertTest($before !== $after, 'reset did not rotate to a fresh response instance');
        assertSameValue(null, $after->getStatus(), 'rotated instance carried stale status');
    }

    public function testResponseHeadersBulkHeadersRequireAnArray(): void
    {
        assertThrows(
            InvalidArgumentException::class,
            function () {
                Response::headers('X-Test: value');
            },
            'non-array bulk headers were accepted',
        );
    }

    public function testResponseHeadersValidBulkHeadersReplaceTheCollection(): void
    {
        Response::header('X-Old', 'old');
        $response = Response::headers([
            'X-Test'    => 'value',
            'X-Numeric' => 123,
        ]);

        assertInstanceOf(Response::class, $response, 'bulk header setter did not return the response');
        assertSameValue(
            ['X-Test' => 'value', 'X-Numeric' => 123],
            Response::getHeaders(),
            'bulk headers did not replace the collection',
        );
    }

    public function testResponseHeadersNamesRejectControlCharacters(): void
    {
        assertThrows(
            InvalidArgumentException::class,
            function () {
                Response::header("X-Test\r\nInjected", 'value');
            },
            'invalid header name was accepted',
        );
    }

    public function testResponseHeadersValuesRejectControlCharacters(): void
    {
        assertThrows(
            InvalidArgumentException::class,
            function () {
                Response::header('X-Test', "value\r\nInjected: yes");
            },
            'invalid header value was accepted',
        );
    }

    public function testResponseHeadersNonScalarValuesAreRejected(): void
    {
        assertThrows(
            InvalidArgumentException::class,
            function () {
                Response::header('X-Test', ['invalid']);
            },
            'non-scalar header value was accepted',
        );
    }
}
