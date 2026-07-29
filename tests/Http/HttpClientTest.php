<?php

namespace BitApps\WPKit\Tests\Http;

use BadMethodCallException;
use BitApps\WPKit\Http\Client\HttpClient;
use BitApps\WPKit\Tests\TestCase;
use FakeWpError;
use WpKitTestState;

/**
 * @internal
 *
 * @coversNothing
 */
final class HttpClientTest extends TestCase
{
    public function testHTTPClientSafeRemoteRequestsAreTheDefault(): void
    {
        $client   = new HttpClient();
        $response = $client->request('https://example.com', 'GET', []);

        assertSameValue(['safe' => 1, 'unsafe' => 0], WpKitTestState::$httpCalls, 'safe request function was not used');
        assertSameValue(true, $response->safe, 'JSON response was not decoded');
    }

    public function testHTTPClientUnsafeRemoteRequestsRequireExplicitOptIn(): void
    {
        $client = new HttpClient();
        $client->allowUnsafeUrls();
        $response = $client->request('http://internal.example', 'GET', []);

        assertSameValue(['safe' => 0, 'unsafe' => 1], WpKitTestState::$httpCalls, 'unsafe opt-in was ignored');
        assertSameValue(false, $response->safe, 'unsafe transport response was not returned');
    }

    public function testHTTPClientWordPressErrorsAreReturnedUnchanged(): void
    {
        $client   = new HttpClient();
        $response = $client->request('https://example.com/error', 'GET', []);

        assertInstanceOf(FakeWpError::class, $response, 'WordPress error was decoded or replaced');
    }

    public function testHTTPClientConstructorAppliesSupportedDefaults(): void
    {
        $client = new HttpClient([
            'base_uri'          => 'https://example.com/',
            'content_type'      => 'text/plain',
            'headers'           => ['X-Test' => 'value'],
            'body'              => ['body' => 'value'],
            'form_params'       => ['form' => 'value'],
            'json'              => ['json' => 'value'],
            'multipart'         => [['name' => 'part', 'contents' => 'value']],
            'allow_unsafe_urls' => true,
        ]);

        assertSameValue('https://example.com/', $client->getBaseUri(), 'base URI default was not applied');
        assertSameValue(['value'], $client->getHeader('X-Test'), 'header default was not applied');
        assertSameValue(['body' => 'value'], $client->getBody(), 'body default was not applied');
        assertSameValue(['form' => 'value'], $client->getFormParams(), 'form default was not applied');
        assertSameValue(['json' => 'value'], $client->getJson(), 'JSON default was not applied');
        assertSameValue(
            [['name' => 'part', 'contents' => 'value']],
            $client->getMultipart(),
            'multipart default was not applied',
        );
    }

    public function testHTTPClientFluentConfigurationRetainsRequestValues(): void
    {
        $client = new HttpClient();
        $result = $client
            ->setBaseUri('https://example.com/')
            ->setOptions(['redirection' => 2])
            ->setParams(['internal' => 'value'])
            ->setQueryParams(['page' => 1])
            ->setQueryParam('tag', 'one')
            ->setQueryParam('tag', 'two')
            ->setBody('raw-body');

        assertSameValue($client, $result, 'fluent configuration stopped returning the client');
        assertSameValue(['redirection' => 2], $client->getOptions(), 'options changed');
        assertSameValue('value', $client->getParam('internal'), 'generic parameter changed');
        assertSameValue(false, $client->getParam('missing'), 'missing generic parameter contract changed');
        assertSameValue(['page' => 1, 'tag' => ['one', 'two']], $client->getQueryParams(), 'query parameters changed');
        assertSameValue('raw-body', $client->getBody(), 'body changed');
    }

    public function testHTTPClientExplicitRequestOptionsOverrideDefaults(): void
    {
        $client = new HttpClient();
        $client->setHeaders(['X-Default' => 'default']);
        $client->request(
            'https://example.com',
            'patch',
            'payload',
            ['X-Request' => 'request'],
            ['timeout'   => 5],
        );

        assertSameValue('https://example.com', WpKitTestState::$lastHttpRequest['url'], 'request URL changed');
        assertSameValue(
            [
                'method'  => 'PATCH',
                'headers' => ['X-Request' => 'request'],
                'body'    => 'payload',
                'timeout' => 5,
            ],
            WpKitTestState::$lastHttpRequest['options'],
            'request option precedence changed',
        );
        assertSameValue(['X-Transport' => 'safe'], $client->getResponseHeaders(), 'response headers changed');
        assertSameValue(200, $client->getResponseCode(), 'response code changed');
    }

    public function testHTTPClientPayloadBuilderPassesStringsThroughUnchanged(): void
    {
        $client = new HttpClient();
        $client->setBody('raw-body');

        assertSameValue('raw-body', $client->getPreparedPayload(), 'string body was transformed');
    }

    public function testHTTPClientPayloadBuilderMergesStructuredBodiesAsJSON(): void
    {
        $client = new HttpClient();
        $client
            ->setBody(['body' => 1])
            ->setJson(['json' => 2])
            ->setFormParams(['form' => 3]);

        assertSameValue(
            '{"body":1,"json":2,"form":3}',
            $client->getPreparedPayload(),
            'structured payload merge order changed',
        );
    }

    public function testHTTPClientExplicitMultipartBoundariesRemainStable(): void
    {
        $client = new HttpClient();

        assertSameValue($client, $client->setBoundary('contract'), 'boundary setter stopped being fluent');
        assertSameValue('-------contract', $client->getBoundary(), 'explicit boundary changed');
    }

    public function testHTTPClientUnsupportedDynamicMethodsFailClearly(): void
    {
        $client = new HttpClient();

        assertThrows(
            BadMethodCallException::class,
            function () use ($client) {
                $client->trace('/resource');
            },
            'unsupported dynamic method was accepted',
        );
    }
}
