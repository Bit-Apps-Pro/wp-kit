<?php

namespace BitApps\WPKit\Tests\Helpers;

use BitApps\WPKit\Configs\JsonConfig;
use BitApps\WPKit\Helpers\JSON;
use BitApps\WPKit\Helpers\Slug;
use BitApps\WPKit\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class JsonAndSlugTest extends TestCase
{
    public function testJSONHelperArraysAndObjectsAreEncodedThroughWordPress(): void
    {
        assertSameValue('{"name":"Ada"}', JSON::encode(['name' => 'Ada']), 'JSON encoding changed');
        assertSameValue('{"name":"Ada"}', JSON::maybeEncode(['name' => 'Ada']), 'conditional JSON encoding changed');
        assertSameValue('plain', JSON::maybeEncode('plain'), 'scalar JSON pass-through changed');
    }

    public function testJSONHelperDecodingHonorsAssociativeMode(): void
    {
        assertSameValue(['name' => 'Ada'], JSON::decode('{"name":"Ada"}', true), 'associative decode changed');
        assertSameValue(['name' => 'Ada'], JSON::maybeDecode('{"name":"Ada"}', true), 'conditional decode changed');
        assertSameValue(['already' => 'decoded'], JSON::maybeDecode(['already' => 'decoded'], true), 'decoded input changed');
    }

    public function testJSONHelperValidJSONIsReturnedAndInvalidJSONIsRejected(): void
    {
        $decoded = JSON::is('{"name":"Ada"}', true);

        assertSameValue(['name' => 'Ada'], $decoded, 'valid JSON detection changed');
        assertSameValue(false, JSON::is('{invalid'), 'invalid JSON was accepted');
    }

    public function testJSONConfigurationAssociativeDecodingPreferenceIsMutable(): void
    {
        JsonConfig::setDecodeAsArray(false);
        assertSameValue(false, JsonConfig::decodeAsArray(), 'JSON decode preference did not change');

        JsonConfig::setDecodeAsArray(true);
        assertSameValue(true, JsonConfig::decodeAsArray(), 'JSON decode preference did not restore');
    }

    public function testSlugHelperPunctuationAndWhitespaceNormalizeToLowercaseHyphens(): void
    {
        assertSameValue('hello-wp-kit', Slug::generate(' Hello, WP Kit! '), 'slug normalization changed');
        assertSameValue('already-clean', Slug::generate('Already--Clean'), 'repeated separator normalization changed');
    }
}
