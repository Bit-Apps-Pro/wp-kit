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
    public function testModernEdgeIsNotClassifiedAsChrome(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120.0 Safari/537.36 Edg/120.0';

        assertSameValue('Edge|', UserAgent::checkDevice(), 'modern Edge was classified as Chrome');
    }

    public function testUserAgentIsSanitizedBeforeClassification(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = '<b>Mozilla/5.0 Firefox/120.0</b>';

        assertSameValue('Firefox|', UserAgent::checkDevice(), 'sanitized browser classification changed');
    }

    public function testKnownCrawlerKeepsItsSpecificLabel(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';

        assertSameValue('Googlebot|', UserAgent::checkDevice(), 'specific crawler was reduced to a generic bot');
    }

    public function testOperatingSystemPriorityRemainsStable(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) Chrome/120.0';

        assertSameValue('Chrome|Pixel', UserAgent::checkDevice(), 'device-specific OS priority changed');
    }

    public function testOperatingSystemDetectionEmitsNoRegexWarnings(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Legacy client 1.2.3';
        $warnings = [];
        set_error_handler(static function ($severity, $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];

            return true;
        });

        try {
            UserAgent::checkDevice();
        } finally {
            restore_error_handler();
        }

        assertSameValue([], $warnings, 'OS detection suppressed an invalid regular expression');
    }

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
