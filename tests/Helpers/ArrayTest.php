<?php

namespace BitApps\WPKit\Tests\Helpers;

use BitApps\WPKit\Helpers\Arr;
use BitApps\WPKit\Tests\TestCase;
use InvalidArgumentException;

/**
 * @internal
 *
 * @coversNothing
 */
final class ArrayTest extends TestCase
{
    public function testArrayHelperDotAccessCanReadSetAndForgetNestedValues(): void
    {
        $values = ['user' => ['name' => 'Ada']];

        assertSameValue('Ada', Arr::get($values, 'user.name'), 'nested get changed');
        assertSameValue(true, Arr::has($values, 'user.name'), 'nested has changed');

        Arr::set($values, 'user.role', 'admin');
        assertSameValue('admin', Arr::get($values, 'user.role'), 'nested set changed');

        Arr::forget($values, 'user.name');
        assertSameValue(false, Arr::has($values, 'user.name'), 'nested forget changed');
    }

    public function testArrayHelperAddDoesNotOverwriteExistingValues(): void
    {
        $values = ['user' => ['name' => 'Ada']];

        $values = Arr::add($values, 'user.name', 'Grace');
        $values = Arr::add($values, 'user.role', 'admin');

        assertSameValue(
            ['user' => ['name' => 'Ada', 'role' => 'admin']],
            $values,
            'conditional add behavior changed',
        );
    }

    public function testArrayHelperDotAndFlattenRetainTheirDistinctShapes(): void
    {
        $values = ['user' => ['name' => 'Ada'], 'roles' => ['admin', ['editor']]];

        assertSameValue(
            ['user.name' => 'Ada', 'roles.0' => 'admin', 'roles.1.0' => 'editor'],
            Arr::dot($values),
            'dot flattening changed',
        );
        assertSameValue(['Ada', 'admin', 'editor'], Arr::flatten($values), 'value flattening changed');
    }

    public function testArrayHelperCollectionSelectionKeepsKeysAndValues(): void
    {
        $values = ['one' => 1, 'two' => 2, 'three' => 3];

        assertSameValue(['one' => 1, 'three' => 3], Arr::only($values, ['one', 'three']), 'only changed');
        assertSameValue(['one' => 1, 'three' => 3], Arr::except($values, 'two'), 'except changed');
        assertSameValue([['one', 'two', 'three'], [1, 2, 3]], Arr::divide($values), 'divide changed');
    }

    public function testArrayHelperPullReturnsAndRemovesANestedValue(): void
    {
        $values = ['user' => ['name' => 'Ada', 'role' => 'admin']];

        $role = Arr::pull($values, 'user.role');

        assertSameValue('admin', $role, 'pull return value changed');
        assertSameValue(['user' => ['name' => 'Ada']], $values, 'pull mutation changed');
    }

    public function testArrayHelperFirstLastAndWherePreserveCallbackSemantics(): void
    {
        $values = [1, 2, 3, 4];
        $even   = function ($value) {
            return $value % 2 === 0;
        };

        assertSameValue(2, Arr::first($values, $even), 'first callback behavior changed');
        assertSameValue(4, Arr::last($values, $even), 'last callback behavior changed');
        assertSameValue([1 => 2, 3 => 4], Arr::where($values, $even), 'where key preservation changed');
    }

    public function testArrayHelperPluckSupportsNestedValuesAndKeys(): void
    {
        $values = [
            ['user' => ['id' => 'a', 'name' => 'Ada']],
            ['user' => ['id' => 'g', 'name' => 'Grace']],
        ];

        assertSameValue(
            ['a' => 'Ada', 'g' => 'Grace'],
            Arr::pluck($values, 'user.name', 'user.id'),
            'nested pluck changed',
        );
    }

    public function testArrayHelperWildcardDataAccessReturnsMatchingValues(): void
    {
        $values = [
            'users' => [
                ['name' => 'Ada'],
                ['name' => 'Grace'],
            ],
        ];

        assertSameValue(['Ada', 'Grace'], Arr::dataGet($values, 'users.*.name'), 'wildcard data access changed');
        assertSameValue('fallback', Arr::dataGet($values, 'missing', 'fallback'), 'data access default changed');
    }

    public function testArrayHelperCrossJoinReturnsEveryOrderedCombination(): void
    {
        assertSameValue(
            [[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']],
            Arr::crossJoin([1, 2], ['a', 'b']),
            'cross join changed',
        );
    }

    public function testArrayHelperQueryAndCSSClassRenderingRemainDeterministic(): void
    {
        assertSameValue('search=hello%20world&page=2', Arr::query(['search' => 'hello world', 'page' => 2]), 'query encoding changed');
        assertSameValue(
            'base active',
            Arr::toCssClasses(['base', 'active' => true, 'disabled' => false]),
            'conditional CSS classes changed',
        );
    }

    public function testArrayHelperWrapAndDeferredDefaultsRetainValueSemantics(): void
    {
        assertSameValue([], Arr::wrap(null), 'null wrapping changed');
        assertSameValue(['value'], Arr::wrap('value'), 'scalar wrapping changed');
        assertSameValue('resolved:x', Arr::value(function ($suffix) {
            return 'resolved:' . $suffix;
        }, 'x'), 'deferred value invocation changed');
    }

    public function testArrayHelperRequestingTooManyRandomValuesFailsClearly(): void
    {
        assertThrows(
            InvalidArgumentException::class,
            function () {
                Arr::random([1], 2);
            },
            'invalid random selection was accepted',
        );
    }
}
