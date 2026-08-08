<?php

namespace BitApps\WPKit\Tests\Router;

use BitApps\WPKit\Http\Router\RouteBase;
use BitApps\WPKit\Http\Router\Router;
use BitApps\WPKit\Http\Router\StaticRouter;
use BitApps\WPKit\Http\Router\Emitter\StaticResponseEmitter;
use BitApps\WPKit\Tests\TestCase;
use UnexpectedValueException;
use WpKitTestState;

/**
 * @internal
 *
 * @coversNothing
 */
final class StaticRoutingTest extends TestCase
{
    public function testStaticPostRouteDoesNotExecuteOnGet(): void
    {
        $called = false;
        new Router('static', 'landing', null);
        (new RouteBase())->post('submit', static function () use (&$called) {
            $called = true;

            return 'submitted';
        });
        new StaticRouter('landing', 'plugin_activate', 'plugin_deactivate');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/landing/submit';

        do_action('template_redirect');

        assertSameValue(false, $called, 'POST static action executed for GET');
    }

    public function testNullStaticOutputRendersAsEmptyContent(): void
    {
        $this->makeTransport(['empty' => static fn () => null]);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/landing/empty';

        do_action('template_redirect');

        assertSameValue('page', apply_filters('the_content', 'page'), 'null output did not normalize to empty HTML');
    }

    public function testArrayStaticOutputFailsWithAContractException(): void
    {
        assertThrows(
            UnexpectedValueException::class,
            static fn () => (new StaticResponseEmitter())->emit(['data' => ['data' => ['invalid']]]),
            'array static output was accepted',
        );
    }

    public function testRewriteRulesMapPageAndParameterSegmentsToQueryVars(): void
    {
        $this->makeTransport(['entries/{id}' => static function () {
            return 'ok';
        }]);

        do_action('init');

        $rules = WpKitTestState::$rewriteRules;
        assertSameValue('index.php?pagename=landing', $rules['^landing/?$']['query'] ?? null, 'page rewrite rule changed');
        assertSameValue('index.php?pagename=landing&id=$matches[1]', $rules['^landing/entries/([^/]+)/?$']['query'] ?? null, 'parameter rewrite rule changed');
        assertTest(!isset($rules['^landing/entries/?$']), 'undeclared intermediate route was registered');
    }

    public function testRewriteRulesForMultiParameterRouteChainQueryVars(): void
    {
        $this->makeTransport(['books/{author}/chapters/{chapter}' => static function () {
            return 'ok';
        }]);

        do_action('init');

        $rules = WpKitTestState::$rewriteRules;
        assertSameValue(
            'index.php?pagename=landing&author=$matches[1]&chapter=$matches[2]',
            $rules['^landing/books/([^/]+)/chapters/([^/]+)/?$']['query'] ?? null,
            'full rewrite rule did not chain both parameters',
        );
        assertTest(!isset($rules['^landing/books/([^/]+)/chapters/?$']), 'undeclared intermediate route was registered');
    }

    public function testInitWithoutRoutesRegistersNoRulesAndSkipsFlush(): void
    {
        $this->makeTransport();

        do_action('init');

        assertSameValue([], WpKitTestState::$rewriteRules, 'rules were registered without any routes');
        assertSameValue(0, WpKitTestState::$rewriteFlushes, 'rewrite rules were flushed without any routes');
    }

    public function testRewriteRulesForEveryRouteAreRegisteredTogether(): void
    {
        $this->makeTransport([
            'alpha/{a}' => static function () {
                return 'a';
            },
            'beta/{b}' => static function () {
                return 'b';
            },
        ]);

        do_action('init');

        assertTest(isset(WpKitTestState::$rewriteRules['^landing/alpha/([^/]+)/?$']), 'first route lost its rewrite rule');
        assertTest(isset(WpKitTestState::$rewriteRules['^landing/beta/([^/]+)/?$']), 'second route lost its rewrite rule');
    }

    public function testQueryVarsGainEachRouteParameterOnce(): void
    {
        $transport = $this->makeTransport([
            'entries/{id}' => static function () {
                return 'a';
            },
            'authors/{id}' => static function () {
                return 'b';
            },
        ]);

        do_action('init');

        assertSameValue(['page', 'id'], $transport->addQueryVars(['page']), 'route parameters were not merged into query vars exactly once');
    }

    public function testMatchedRequestRendersRouteOutputThroughContentFilter(): void
    {
        $this->makeTransport(['entries/{id}' => static function ($id) {
            return '<section>entry ' . $id . '</section>';
        }]);
        $_SERVER['REQUEST_URI'] = '/landing/entries/42';

        do_action('template_redirect');

        assertSameValue(
            '<main>page</main><section>entry 42</section>',
            apply_filters('the_content', '<main>page</main>'),
            'matched static route output was not appended to page content',
        );
    }

    public function testOptionalParameterRouteCapturesProvidedValue(): void
    {
        $this->makeTransport(['entries/{slug?}' => static function ($slug) {
            return 'entry ' . $slug;
        }]);
        $_SERVER['REQUEST_URI'] = '/landing/entries/hello';

        do_action('template_redirect');

        assertSameValue('page-entry hello', apply_filters('the_content', 'page-'), 'optional param value was not captured');
    }

    public function testOptionalParameterRouteMatchesWithoutValue(): void
    {
        $this->makeTransport(['entries/{slug?}' => static function ($slug) {
            return 'x';
        }]);
        $_SERVER['REQUEST_URI'] = '/landing/entries/';

        do_action('template_redirect');

        assertTest(isset(WpKitTestState::$filters['the_content']), 'optional-param route did not match without a value');
    }

    public function testOptionalParameterRouteMatchesWithoutTrailingSlash(): void
    {
        $this->makeTransport(['entries/{slug?}' => static function ($slug) {
            return 'x';
        }]);
        $_SERVER['REQUEST_URI'] = '/landing/entries';

        do_action('template_redirect');

        assertTest(isset(WpKitTestState::$filters['the_content']), 'optional-param route did not match without a trailing slash');
    }

    public function testMatchedRequestIgnoresQueryString(): void
    {
        $this->makeTransport(['entries/{id}' => static function ($id) {
            return 'entry ' . $id;
        }]);
        $_SERVER['REQUEST_URI'] = '/landing/entries/42?utm_source=mail&preview=1';

        do_action('template_redirect');

        assertSameValue('page-entry 42', apply_filters('the_content', 'page-'), 'query string broke static route matching');
    }

    public function testUnmatchedRequestDoesNotTouchPageContent(): void
    {
        $this->makeTransport(['entries/{id}' => static function () {
            return 'x';
        }]);
        $_SERVER['REQUEST_URI'] = '/elsewhere/7';

        do_action('template_redirect');

        assertTest(!isset(WpKitTestState::$filters['the_content']), 'content filter was registered for an unmatched request');
    }

    public function testActivationRegistersRewriteRulesAndFlushes(): void
    {
        $this->makeTransport(['entries/{id}' => static function () {
            return 'x';
        }]);

        do_action('plugin_activate');

        assertTest(isset(WpKitTestState::$rewriteRules['^landing/entries/([^/]+)/?$']), 'activation did not register route rewrite rules before flushing');
        assertTest(WpKitTestState::$rewriteFlushes > 0, 'activation did not flush rewrite rules');
    }

    public function testDeactivationOnlyFlushesRewriteRules(): void
    {
        $this->makeTransport(['entries/{id}' => static function () {
            return 'x';
        }]);

        do_action('plugin_deactivate');

        assertSameValue([], WpKitTestState::$rewriteRules, 'deactivation registered rewrite rules');
        assertSameValue(1, WpKitTestState::$rewriteFlushes, 'deactivation must flush exactly once');
    }

    public function testInitFlushesOnceWhenStaticRulesAreMissingFromPersistedRules(): void
    {
        $this->makeTransport(['entries/{id}' => static function () {
            return 'x';
        }]);

        do_action('init');

        assertSameValue(1, WpKitTestState::$rewriteFlushes, 'missing persisted rules did not trigger exactly one flush');
    }

    public function testInitSkipsFlushWhenStaticRulesAlreadyPersisted(): void
    {
        $this->makeTransport(['entries/{id}' => static function () {
            return 'x';
        }]);
        WpKitTestState::$options['rewrite_rules'] = [
            '^landing/?$'                 => 'index.php?pagename=landing',
            '^landing/entries/([^/]+)/?$' => 'index.php?pagename=landing&id=$matches[1]',
        ];

        do_action('init');

        assertSameValue(0, WpKitTestState::$rewriteFlushes, 'already persisted rules still triggered a flush');
    }

    public function testIsRewriteExistsChecksPersistedRulesByPath(): void
    {
        WpKitTestState::$options['rewrite_rules'] = ['^landing/custom' => 'index.php?pagename=landing'];

        assertTest(StaticRouter::isRewriteExists('landing/custom'), 'persisted path rule was not found');
        assertTest(!StaticRouter::isRewriteExists('landing/other'), 'missing path rule was reported as existing');
        assertTest(!StaticRouter::isRewriteExists(''), 'empty path was reported as existing');
    }

    public function testPersistedRewriteCheckRequiresEveryRule(): void
    {
        WpKitTestState::$options['rewrite_rules'] = [
            '^landing/?$' => 'index.php?pagename=landing',
        ];

        assertTest(
            !StaticRouter::isRewriteExists('', [
                '^landing/?$'       => 'index.php?pagename=landing',
                '^landing/about/?$' => 'index.php?pagename=landing',
            ]),
            'partial rewrite set was accepted as complete',
        );
    }

    private function makeTransport(array $routes = []): StaticRouter
    {
        new Router('static', 'landing', null);
        foreach ($routes as $path => $action) {
            (new RouteBase())->get($path, $action);
        }

        return new StaticRouter('landing', 'plugin_activate', 'plugin_deactivate');
    }
}
