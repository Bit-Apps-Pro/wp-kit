<?php

namespace BitApps\WPKit\Tests\Http;

use BitApps\WPKit\Http\Detection\UserAgent;
use BitApps\WPKit\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserAgentTest extends TestCase
{
    public function testDeviceStringCombinesBrowserAndOS(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

        assertSameValue('Chrome|Windows', UserAgent::checkDevice(), 'device classification changed');
    }

    public function testMissingUserAgentYieldsEmptyDevice(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);

        assertSameValue('', UserAgent::checkDevice(), 'missing user agent no longer yields an empty device string');
    }
}
