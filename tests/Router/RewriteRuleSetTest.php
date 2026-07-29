<?php

namespace BitApps\WPKit\Tests\Router;

use BitApps\WPKit\Http\Router\RewriteRuleSet;
use BitApps\WPKit\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RewriteRuleSetTest extends TestCase
{
    public function testAddPathBuildsProgressiveRulesAndQueryVars(): void
    {
        $set = new RewriteRuleSet('landing');
        $set->addPath('entries/{id}');

        assertSameValue(
            [
                '^landing/?$'                 => 'index.php?pagename=landing',
                '^landing/entries/?$'         => 'index.php?pagename=landing',
                '^landing/entries/([^/]+)/?$' => 'index.php?pagename=landing&id=$matches[1]',
            ],
            $set->rules(),
            'rewrite rule chain changed',
        );
        assertSameValue(['id'], $set->queryVars(), 'query vars changed');
    }

    public function testRulesAccumulateAcrossPaths(): void
    {
        $set = new RewriteRuleSet('landing');
        $set->addPath('alpha/{a}');
        $set->addPath('beta/{b}');

        assertTest(isset($set->rules()['^landing/alpha/([^/]+)/?$']), 'first path lost its rule');
        assertTest(isset($set->rules()['^landing/beta/([^/]+)/?$']), 'second path lost its rule');
        assertSameValue(['a', 'b'], $set->queryVars(), 'query vars did not accumulate');
    }

    public function testEmptySetHasNoRulesOrQueryVars(): void
    {
        $set = new RewriteRuleSet('landing');

        assertSameValue([], $set->rules(), 'empty set produced rules');
        assertSameValue([], $set->queryVars(), 'empty set produced query vars');
    }
}
