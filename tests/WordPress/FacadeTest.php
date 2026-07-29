<?php

namespace BitApps\WPKit\Tests\WordPress;

use BitApps\WPKit\Hooks\Hooks;
use BitApps\WPKit\Hooks\HooksWrapper;
use BitApps\WPKit\Http\Client\Http;
use BitApps\WPKit\Http\RequestType;
use BitApps\WPKit\Shortcode\Shortcode;
use BitApps\WPKit\Shortcode\ShortcodeWrapper;
use BitApps\WPKit\Tests\TestCase;
use BitApps\WPKit\Utils\Capabilities;
use WpKitTestState;

/**
 * @internal
 *
 * @coversNothing
 */
final class FacadeTest extends TestCase
{
    public function testHooksFacadeActionsPreservePriorityAcceptedArgumentsAndInvocation(): void
    {
        $received = null;
        $callback = function ($first, $second) use (&$received) {
            $received = [$first, $second];
        };

        assertSameValue(true, Hooks::addAction('contract_action', $callback, 20, 2), 'action registration changed');
        Hooks::doAction('contract_action', 'one', 'two', 'ignored');

        assertSameValue(['one', 'two'], $received, 'action invocation changed');
        assertSameValue(20, WpKitTestState::$actions['contract_action'][0]['priority'], 'action priority changed');
        assertSameValue(true, Hooks::removeAction('contract_action', $callback, 20), 'action removal changed');
    }

    public function testHooksFacadeFiltersTransformValuesAndRemainRemovable(): void
    {
        $callback = function ($value, $suffix) {
            return $value . $suffix;
        };

        Hooks::addFilter('contract_filter', $callback, 10, 2);
        assertSameValue('value!', Hooks::applyFilter('contract_filter', 'value', '!'), 'filter application changed');
        assertSameValue(true, Hooks::removeFilter('contract_filter', $callback), 'filter removal changed');
        assertSameValue('value', Hooks::applyFilter('contract_filter', 'value', '!'), 'removed filter still executed');
    }

    public function testHooksFacadeWrapperRemainsAvailableToInstanceConsumers(): void
    {
        $hooks = new Hooks();

        assertInstanceOf(HooksWrapper::class, $hooks->getInstance(), 'hook wrapper type changed');
    }

    public function testShortcodeFacadeRegistrationLookupRenderingAndRemovalAreForwarded(): void
    {
        $callback = function () {
            return 'shortcode';
        };

        Shortcode::addShortcode('contract', $callback);

        assertSameValue(true, Shortcode::shortcodeExists('contract'), 'shortcode lookup changed');
        assertSameValue(true, Shortcode::hasShortcode('[contract]', 'contract'), 'shortcode content detection changed');
        Shortcode::doShortcode('[contract]');
        assertSameValue(
            [['content' => '[contract]', 'ignoreHtml' => false]],
            WpKitTestState::$shortcodeRenders,
            'shortcode rendering was not forwarded',
        );

        Shortcode::removeShortcode('contract');
        assertSameValue(false, Shortcode::shortcodeExists('contract'), 'shortcode removal changed');
    }

    public function testShortcodeFacadeWrapperRemainsAvailableToInstanceConsumers(): void
    {
        $shortcode = new Shortcode();

        assertInstanceOf(ShortcodeWrapper::class, $shortcode->getInstance(), 'shortcode wrapper type changed');
    }

    public function testCapabilitiesFacadeDirectChecksForwardVariadicArguments(): void
    {
        WpKitTestState::$capabilities['edit_post'] = true;

        assertSameValue(true, Capabilities::check('edit_post', 42), 'capability check changed');
        assertSameValue(false, Capabilities::check('delete_post', 42), 'missing capability changed');
    }

    public function testCapabilitiesFacadeFilteredFallbackCapabilitiesRemainSupported(): void
    {
        WpKitTestState::$capabilities['manage_contract'] = true;
        Hooks::addFilter('edit_contract', function ($default) {
            return 'manage_contract';
        });

        assertSameValue(
            true,
            Capabilities::filter('edit_contract', 'manage_options'),
            'filtered fallback capability changed',
        );
    }

    public function testHTTPFacadeStaticVerbsForwardToTheWordPressClient(): void
    {
        $response = Http::get('https://example.com', []);

        assertSameValue(['safe' => 1, 'unsafe' => 0], WpKitTestState::$httpCalls, 'HTTP facade transport changed');
        assertSameValue(true, $response->safe, 'HTTP facade response changed');
        assertSameValue('GET', WpKitTestState::$lastHttpRequest['options']['method'], 'HTTP facade method changed');
    }

    public function testRequestTypeAdminAndFrontendDetectionRemainComplementary(): void
    {
        WpKitTestState::$isAdmin = true;
        assertSameValue(true, RequestType::is(RequestType::ADMIN), 'admin request detection changed');
        assertSameValue(false, RequestType::is(RequestType::FRONTEND), 'admin request was classified as frontend');

        WpKitTestState::$isAdmin = false;
        assertSameValue(true, RequestType::is(RequestType::FRONTEND), 'frontend request detection changed');
    }
}
