<?php

namespace BitApps\WPKit;

if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\WPKit\Hooks\Hooks;
use BitApps\WPKit\Migration\MigrationHelper;

/**
 * Class handling plugin activation, deactivation, uninstall.
 *
 * @since 1.0.0
 */
final class Installer
{
    private $_migration;

    private static $_drop;

    /**
     * Sets necessary elements
     *
     * @param array $_requirements
     * @param array $_hooks
     * @param array $migration
     */
    public function __construct(private $_requirements, private $_hooks, array $migration)
    {
        $this->_migration = $migration['migration'];
        self::$_drop      = $migration['drop'];
    }

    public function register(): void
    {
        if (isset($this->_hooks['activate'])) {
            Hooks::addAction($this->_hooks['activate'], [$this, 'activate']);
        }

        if (isset($this->_hooks['uninstall'])) {
            // Only a static class method or function can be used in an uninstall hook.
            Hooks::addAction($this->_hooks['uninstall'], [self::class, 'uninstall']);
        }

        // On multisite, a subsite created after activation never runs the activation-time
        // provisioning loop; provision its schema when WordPress initialises the new site. Priority
        // 20 runs after core's own priority-10 handler that creates the blog's options/core tables.
        if (!empty($this->_requirements['multisite'])) {
            add_action('wp_initialize_site', [$this, 'provisionNewSite'], 20);
        }
    }

    public function activate($isNetworkActivation): void
    {
        $this->checkRequirements();
        if (
            isset($this->_requirements['multisite']) && $this->_requirements['multisite']
                                                     && $isNetworkActivation
        ) {
            $this->activateOnMultiSite();
        } else {
            $this->activateOnSingleSite();
        }
    }

    public function activateOnSingleSite(): void
    {
        if (version_compare($this->_requirements['oldVersion'], $this->_requirements['version'], '<')) {
            MigrationHelper::migrate($this->_migration);
        }
    }

    public function activateOnMultiSite(): void
    {
        $sites = get_sites((['fields' => 'ids', 'network_id' => get_current_network_id()]));
        foreach ($sites as $site) {
            switch_to_blog($site);
            $this->activateOnSingleSite();
            restore_current_blog();
        }
    }

    /**
     * Provision the plugin schema on a subsite created after network activation, which the
     * activation-time loop never covers. Idempotent (migrations are CREATE TABLE IF NOT EXISTS);
     * gated to network-active multisite installs so tables are never created on a site not running
     * the plugin.
     *
     * @param object $newSite the WP_Site for the just-created blog
     */
    public function provisionNewSite($newSite): void
    {
        if (!is_multisite() || !$this->isNetworkActive() || !isset($newSite->blog_id)) {
            return;
        }

        switch_to_blog((int) $newSite->blog_id);

        try {
            MigrationHelper::migrate($this->_migration);
        } finally {
            // Restore in a finally so a migration throw can't leave the wrong blog switched.
            restore_current_blog();
        }
    }

    /**
     * Whether the plugin is active network-wide; gates subsite provisioning to network activations so
     * a new subsite never gets tables for a plugin that is not actually running network-wide.
     */
    public function isNetworkActive(): bool
    {
        if (empty($this->_requirements['basename'])) {
            return false;
        }

        if (!\function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active_for_network($this->_requirements['basename']);
    }

    public static function uninstall(): void
    {
        if (is_multisite()) {
            self::uninstallFromAllSite();
        } else {
            self::uninstallFromSingleSite();
        }
    }

    public static function uninstallFromSingleSite(): void
    {
        MigrationHelper::drop(self::$_drop);
    }

    public static function uninstallFromAllSite(): void
    {
        $sites = get_sites((['fields' => 'ids', 'network_id' => get_current_network_id()]));

        foreach ($sites as $site) {
            switch_to_blog($site);
            self::uninstallFromSingleSite();
            restore_current_blog();
        }
    }

    public function checkRequirements(): void
    {
        if (version_compare(PHP_VERSION, $this->_requirements['php'], '<')) {
            // Str From WP install script
            wp_die(
                esc_html(
                    \sprintf(
                        // translators: 1: Current PHP version, 2: Version required by the uploaded plugin.
                        'The PHP version on your server is %1$s, however the uploaded plugin requires %2$s.',
                        PHP_VERSION,
                        $this->_requirements['php']
                    )
                ),
                esc_html('Requirements Not Met')
            );
        }

        if (version_compare(get_bloginfo('version'), $this->_requirements['wp'], '<')) {
            wp_die(
                esc_html(
                    \sprintf(
                        // translators: 1: Current WordPress version, 2: Version required by the uploaded plugin.
                        'Your WordPress version is %1$s, however the uploaded plugin requires %2$s.',
                        get_bloginfo('version'),
                        $this->_requirements['wp']
                    ),
                    esc_html('Requirements Not Met')
                )
            );
        }
    }
}
