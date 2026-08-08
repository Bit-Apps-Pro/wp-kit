<?php

namespace BitApps\WPKit\Tests\Router;

use BitApps\WPKit\Http\Router\RoutePattern;
use BitApps\WPKit\Tests\TestCase;
use InvalidArgumentException;

/**
 * @internal
 *
 * @coversNothing
 */
final class RoutePatternTest extends TestCase
{
    public function testPlaceholdersExposeTokenOffsetsAndRequirements(): void
    {
        assertSameValue(
            [
                ['token' => '{id}', 'offset' => 8, 'name' => 'id', 'required' => true],
                ['token' => '{slug?}', 'offset' => 22, 'name' => 'slug', 'required' => false],
            ],
            RoutePattern::placeholders('entries/{id}/comments/{slug?}'),
            'placeholder metadata was not reusable by rewrite generation',
        );
    }

    public function testCompileBuildsNamedGroupsAndParamMetadata(): void
    {
        $compiled = RoutePattern::compile('entries/{id}/comments/{slug?}');

        assertSameValue('entries\/(?P<id>[^\/]+)\/comments(?:\/(?P<slug>[^\/]+))?', $compiled['regex'], 'compiled regex changed');
        assertSameValue(
            ['id' => ['required' => true], 'slug' => ['required' => false]],
            $compiled['params'],
            'param metadata changed',
        );
    }

    public function testCompileQuotesRegexMetacharactersInLiteralSegments(): void
    {
        $compiled = RoutePattern::compile('files/{name}.json');

        assertSameValue('files\/(?P<name>[^\/]+)\.json', $compiled['regex'], 'literal metacharacters were not quoted');
    }

    public function testCompileRejectsDuplicateParameterNames(): void
    {
        assertThrows(
            InvalidArgumentException::class,
            static function () {
                RoutePattern::compile('posts/{id}/related/{id}');
            },
            'duplicate parameter names were accepted',
        );
    }

    public function testCompileRejectsInvalidParameterNames(): void
    {
        assertThrows(
            InvalidArgumentException::class,
            static function () {
                RoutePattern::compile('posts/{1a}');
            },
            'digit-leading parameter name was accepted',
        );
    }

    public function testCompileReturnsNullWithoutPlaceholders(): void
    {
        assertSameValue(null, RoutePattern::compile('entries/list'), 'placeholderless path produced a compilation');
    }
}
