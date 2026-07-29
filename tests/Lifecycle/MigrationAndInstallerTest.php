<?php

namespace BitApps\WPKit\Tests\Lifecycle;

use BitApps\WPKit\Installer;
use BitApps\WPKit\Migration\MigrationHelper;
use BitApps\WPKit\Tests\TestCase;
use ContractMigration;
use WpDieException;
use WpKitTestState;

function contractMigrationConfiguration()
{
    return [
        'path'       => __DIR__ . '/../Fixtures/Migrations/',
        'migrations' => ['ContractMigration'],
    ];
}

function contractInstallerRequirements()
{
    return [
        'oldVersion' => '1.0.0',
        'version'    => '2.0.0',
        'php'        => '8.0',
        'wp'         => '6.0',
        'multisite'  => true,
    ];
}

function resetContractMigrationCalls()
{
    if (!class_exists('ContractMigration')) {
        MigrationHelper::getMigrationInstances(contractMigrationConfiguration());
    }

    ContractMigration::$upCalls   = 0;
    ContractMigration::$downCalls = 0;
}

/**
 * @internal
 *
 * @coversNothing
 */
final class MigrationAndInstallerTest extends TestCase
{
    public function testMigrationHelperConfiguredClassesAreLoadedAndInstantiated(): void
    {
        $instances = MigrationHelper::getMigrationInstances(contractMigrationConfiguration());

        assertSameValue(1, \count($instances), 'migration instance count changed');
        assertInstanceOf(ContractMigration::class, $instances[0], 'migration class changed');
    }

    public function testMigrationHelperMigrateAndDropInvokeLifecycleMethods(): void
    {
        resetContractMigrationCalls();

        MigrationHelper::migrate(contractMigrationConfiguration());
        MigrationHelper::drop(contractMigrationConfiguration());

        assertSameValue(1, ContractMigration::$upCalls, 'migration up invocation changed');
        assertSameValue(1, ContractMigration::$downCalls, 'migration down invocation changed');
    }

    public function testInstallerRegisterConnectsConfiguredLifecycleHooks(): void
    {
        $installer = new Installer(
            contractInstallerRequirements(),
            ['activate' => 'plugin_activate', 'uninstall' => 'plugin_uninstall'],
            [
                'migration' => contractMigrationConfiguration(),
                'drop'      => contractMigrationConfiguration(),
            ],
        );

        $installer->register();

        assertTest(isset(WpKitTestState::$actions['plugin_activate']), 'activation hook registration changed');
        assertTest(isset(WpKitTestState::$actions['plugin_uninstall']), 'uninstall hook registration changed');
    }

    public function testInstallerVersionUpgradesRunMigrationsOnOneSite(): void
    {
        resetContractMigrationCalls();
        $installer = new Installer(
            contractInstallerRequirements(),
            [],
            [
                'migration' => contractMigrationConfiguration(),
                'drop'      => contractMigrationConfiguration(),
            ],
        );

        $installer->activateOnSingleSite();

        assertSameValue(1, ContractMigration::$upCalls, 'single-site migration changed');
    }

    public function testInstallerNetworkActivationVisitsAndRestoresEverySite(): void
    {
        resetContractMigrationCalls();
        WpKitTestState::$sites = [11, 12];
        $installer             = new Installer(
            contractInstallerRequirements(),
            [],
            [
                'migration' => contractMigrationConfiguration(),
                'drop'      => contractMigrationConfiguration(),
            ],
        );

        $installer->activate(true);

        assertSameValue([11, 12], WpKitTestState::$switchedBlogs, 'network site traversal changed');
        assertSameValue(2, WpKitTestState::$restoredBlogs, 'network blog restoration changed');
        assertSameValue(2, ContractMigration::$upCalls, 'network migration count changed');
    }

    public function testInstallerUninstallDropsConfiguredMigrations(): void
    {
        resetContractMigrationCalls();
        new Installer(
            contractInstallerRequirements(),
            [],
            [
                'migration' => contractMigrationConfiguration(),
                'drop'      => contractMigrationConfiguration(),
            ],
        );

        Installer::uninstall();

        assertSameValue(1, ContractMigration::$downCalls, 'single-site uninstall changed');
    }

    public function testInstallerUnmetPHPRequirementsTerminateActivation(): void
    {
        $requirements        = contractInstallerRequirements();
        $requirements['php'] = '99.0';
        $installer           = new Installer(
            $requirements,
            [],
            [
                'migration' => contractMigrationConfiguration(),
                'drop'      => contractMigrationConfiguration(),
            ],
        );

        assertThrows(
            WpDieException::class,
            function () use ($installer) {
                $installer->checkRequirements();
            },
            'unmet PHP requirement was accepted',
        );
    }

    public function testInstallerUnmetWordPressRequirementsTerminateActivation(): void
    {
        $requirements       = contractInstallerRequirements();
        $requirements['wp'] = '99.0';
        $installer          = new Installer(
            $requirements,
            [],
            [
                'migration' => contractMigrationConfiguration(),
                'drop'      => contractMigrationConfiguration(),
            ],
        );

        assertThrows(
            WpDieException::class,
            function () use ($installer) {
                $installer->checkRequirements();
            },
            'unmet WordPress requirement was accepted',
        );
    }
}
