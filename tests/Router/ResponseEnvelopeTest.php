<?php

namespace BitApps\WPKit\Tests\Router;

use BitApps\WPKit\Http\Response;
use BitApps\WPKit\Http\Router\ResponseEnvelope;
use BitApps\WPKit\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ResponseEnvelopeTest extends TestCase
{
    public function testBuildWrapsRawValuesInASuccessEnvelope(): void
    {
        $envelope = ResponseEnvelope::build(['result' => 'ok']);

        assertSameValue(Response::SUCCESS, $envelope['data']['status'], 'raw value did not become a success envelope');
        assertSameValue('SUCCESS', $envelope['data']['code'], 'success code changed');
        assertSameValue(['result' => 'ok'], $envelope['data']['data'], 'payload changed');
        assertSameValue(200, $envelope['http_status'], 'default success status changed');
    }

    public function testBuildSerializesThePassedResponseNotTheRotatedCurrent(): void
    {
        $captured = Response::success(['keep' => 'me'])->code('CAPTURED');
        Response::reset(); // rotate current away, e.g. a nested dispatch

        $envelope = ResponseEnvelope::build($captured);

        assertSameValue('CAPTURED', $envelope['data']['code'], 'build serialized the rotated current instead of the passed response');
        assertSameValue(['keep' => 'me'], $envelope['data']['data'], 'build lost the passed response payload');
    }

    public function testBuildKeepsResponseObjectsAndAttachesBufferedOutput(): void
    {
        $envelope = ResponseEnvelope::build(
            Response::error(['field' => 'bad'], 422)->code('VALIDATION'),
            'stray output',
        );

        assertSameValue('VALIDATION', $envelope['data']['code'], 'response code changed');
        assertSameValue(422, $envelope['http_status'], 'http status changed');
        assertSameValue('stray output', $envelope['data']['additional'], 'buffered output was not attached');
    }
}
