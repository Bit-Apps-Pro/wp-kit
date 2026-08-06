<?php

namespace BitApps\WPKit\Tests\Http;

use BadMethodCallException;
use BitApps\WPKit\Http\Client\HttpClient;
use BitApps\WPKit\Tests\TestCase;
use FakeWpError;
use WP_Error;
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

    public function testHTTPClientUnsafeRemoteRequestsFailClosedWithoutAnAllowlist(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls();
        $response = $client->request('http://internal.example', 'GET', []);

        assertInstanceOf(WP_Error::class, $response, 'unsafe request without an allowlist was not rejected');
        assertSameValue('unsafe_url_not_allowed', $response->get_error_code(), 'unexpected unsafe URL error code');
        assertSameValue(['safe' => 0, 'unsafe' => 0], WpKitTestState::$httpCalls, 'rejected URL reached a transport');
    }

    public function testHTTPClientUnsafeRemoteRequestsPermitAnExactAllowlistedHost(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['internal.example']);
        $response = $client->request('http://internal.example/resource', 'GET', []);

        assertSameValue(['safe' => 0, 'unsafe' => 1], WpKitTestState::$httpCalls, 'exact host did not use unsafe transport');
        assertSameValue(false, $response->safe, 'unsafe transport response was not returned');
    }

    public function testHTTPClientUnsafeRemoteRequestsRejectADifferentHost(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['internal.example']);
        $response = $client->request('http://other.example/resource', 'GET', []);

        assertInstanceOf(WP_Error::class, $response, 'different host was not rejected');
        assertSameValue('unsafe_url_not_allowed', $response->get_error_code(), 'unexpected unsafe URL error code');
        assertSameValue(['safe' => 0, 'unsafe' => 0], WpKitTestState::$httpCalls, 'different host reached a transport');
    }

    public function testHTTPClientUnsafeRemoteRequestsRejectSubdomainsOfAnAllowlistedHost(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['example.com']);
        $response = $client->request('http://evil.example.com/resource', 'GET', []);

        assertInstanceOf(WP_Error::class, $response, 'subdomain was not rejected');
        assertSameValue('unsafe_url_not_allowed', $response->get_error_code(), 'unexpected unsafe URL error code');
        assertSameValue(['safe' => 0, 'unsafe' => 0], WpKitTestState::$httpCalls, 'subdomain reached a transport');
    }

    public function testHTTPClientUnsafeRemoteRequestsRejectHostsWithAnAllowlistedPrefix(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['example.com']);
        $response = $client->request('http://example.com.evil.test/resource', 'GET', []);

        assertInstanceOf(WP_Error::class, $response, 'host with allowlisted prefix was not rejected');
        assertSameValue('unsafe_url_not_allowed', $response->get_error_code(), 'unexpected unsafe URL error code');
        assertSameValue(['safe' => 0, 'unsafe' => 0], WpKitTestState::$httpCalls, 'prefixed host reached a transport');
    }

    public function testHTTPClientUnsafeRemoteRequestsAuthorizeTheParsedHostNotUserInfo(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['example.com']);
        $response = $client->request('http://user@example.com/resource', 'GET', []);

        assertSameValue(['safe' => 0, 'unsafe' => 1], WpKitTestState::$httpCalls, 'URL user info changed host authorization');
        assertSameValue(false, $response->safe, 'parsed host was not authorized');
    }

    public function testHTTPClientUnsafeRemoteRequestsNormalizeTheParsedHost(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['EXAMPLE.COM']);
        $response = $client->request('http://example.com./resource', 'GET', []);

        assertSameValue(['safe' => 0, 'unsafe' => 1], WpKitTestState::$httpCalls, 'normalized host did not use unsafe transport');
        assertSameValue(false, $response->safe, 'normalized host was not authorized');
    }

    public function testHTTPClientUnsafeRemoteRequestsRejectUnsupportedSchemes(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['internal.example']);
        $response = $client->request('ftp://internal.example/resource', 'GET', []);

        assertInstanceOf(WP_Error::class, $response, 'unsupported scheme was not rejected');
        assertSameValue('unsafe_url_not_allowed', $response->get_error_code(), 'unexpected unsafe URL error code');
        assertSameValue(['safe' => 0, 'unsafe' => 0], WpKitTestState::$httpCalls, 'unsupported scheme reached a transport');
    }

    public function testHTTPClientUnsafeRemoteRequestsRejectHostlessUrls(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['internal.example']);
        $response = $client->request('/resource', 'GET', []);

        assertInstanceOf(WP_Error::class, $response, 'hostless URL was not rejected');
        assertSameValue('unsafe_url_not_allowed', $response->get_error_code(), 'unexpected unsafe URL error code');
        assertSameValue(['safe' => 0, 'unsafe' => 0], WpKitTestState::$httpCalls, 'hostless URL reached a transport');
    }

    public function testHTTPClientUnsafeRemoteRequestsRejectMalformedUrls(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['internal.example']);
        $response = $client->request('http:///resource', 'GET', []);

        assertInstanceOf(WP_Error::class, $response, 'malformed URL was not rejected');
        assertSameValue('unsafe_url_not_allowed', $response->get_error_code(), 'unexpected unsafe URL error code');
        assertSameValue(['safe' => 0, 'unsafe' => 0], WpKitTestState::$httpCalls, 'malformed URL reached a transport');
    }

    public function testHTTPClientUnsafeRemoteRequestsPermitAnExactIpv6Host(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['2001:db8::1']);
        $response = $client->request('http://[2001:db8::1]/resource', 'GET', []);

        assertSameValue(['safe' => 0, 'unsafe' => 1], WpKitTestState::$httpCalls, 'exact IPv6 host did not use unsafe transport');
        assertSameValue(false, $response->safe, 'IPv6 host was not authorized');
    }

    public function testHTTPClientUnsafeRemoteRequestsAuthorizeHostsIndependentOfPort(): void
    {
        $client   = (new HttpClient())->allowUnsafeUrls(true, ['internal.example']);
        $response = $client->request('https://internal.example:8443/resource', 'GET', []);

        assertSameValue(['safe' => 0, 'unsafe' => 1], WpKitTestState::$httpCalls, 'URL port changed host authorization');
        assertSameValue(false, $response->safe, 'host with a different port was not authorized');
    }

    public function testHTTPClientUnsafeRemoteRequestsDisableRedirects(): void
    {
        $client = (new HttpClient())->allowUnsafeUrls(true, ['internal.example']);
        $client->request('http://internal.example/resource', 'GET', null, null, ['redirection' => 5]);

        assertSameValue(['safe' => 0, 'unsafe' => 1], WpKitTestState::$httpCalls, 'allowlisted host did not use unsafe transport');
        assertSameValue(0, WpKitTestState::$lastHttpRequest['options']['redirection'], 'unsafe transport retained redirects');
    }

    public function testHTTPClientUnsafeUrlAllowlistIsNormalizedAndReplaced(): void
    {
        $client = new HttpClient();

        assertSameValue(
            $client,
            $client->allowUnsafeUrls(true, ['  INTERNAL.example.  ', '[::1]', '127.0.0.1']),
            'unsafe URL configuration stopped being fluent',
        );
        assertSameValue(
            ['internal.example', '::1', '127.0.0.1'],
            $client->getAllowedUnsafeHosts(),
            'unsafe host allowlist was not normalized',
        );

        $client->allowUnsafeUrls(true);

        assertSameValue([], $client->getAllowedUnsafeHosts(), 'empty unsafe host allowlist did not replace prior entries');
    }

    public function testHTTPClientUnsafeHostAllowlistRejectsInvalidValues(): void
    {
        $client = new HttpClient();

        assertSameValue(
            $client,
            $client->setAllowedUnsafeHosts(['', '   ', ' https://internal.example ', 'host/path', '[bracketed.example]', [], true, 123, 'valid.example']),
            'unsafe host allowlist setter stopped being fluent',
        );
        assertSameValue(['valid.example'], $client->getAllowedUnsafeHosts(), 'invalid unsafe host entries were retained');
    }

    public function testHTTPClientUnsafeHostAllowlistStoresNormalizedDuplicatesOnce(): void
    {
        $client = new HttpClient();
        $client->setAllowedUnsafeHosts(['EXAMPLE.COM', 'example.com.', '  example.com  ']);

        assertSameValue(['example.com'], $client->getAllowedUnsafeHosts(), 'normalized duplicate hosts were retained');
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
            'base_uri'             => 'https://example.com/',
            'content_type'         => 'text/plain',
            'headers'              => ['X-Test' => 'value'],
            'body'                 => ['body' => 'value'],
            'form_params'          => ['form' => 'value'],
            'json'                 => ['json' => 'value'],
            'multipart'            => [['name' => 'part', 'contents' => 'value']],
            'allow_unsafe_urls'    => true,
            'allowed_unsafe_hosts' => ['internal.example'],
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
        assertSameValue(['internal.example'], $client->getAllowedUnsafeHosts(), 'unsafe host allowlist default was not applied');
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
