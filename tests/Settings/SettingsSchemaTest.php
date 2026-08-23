<?php

namespace BitApps\WPKit\Tests\Settings;

use BitApps\WPKit\Settings\SettingField;
use BitApps\WPKit\Settings\SettingsSchema;
use BitApps\WPKit\Tests\TestCase;

final class SettingsSchemaTest extends TestCase
{
    public function testDefaultsAndCasting(): void
    {
        $schema = (new SettingsSchema())->add(
            SettingField::bool('logging_enabled', true, 'general'),
            SettingField::int('retention', 30, 'general'),
            SettingField::enum('mode', ['full', 'redacted'], 'full', 'privacy')
        );
        $this->assertSame(['logging_enabled' => true, 'retention' => 30, 'mode' => 'full'], $schema->defaults());
        $this->assertTrue($schema->field('logging_enabled')->cast('1'));
        $this->assertSame(30, $schema->field('retention')->cast('30'));
        $this->assertSame(['general', 'privacy'], $schema->groups());
    }

    public function testEnumRejectsInvalid(): void
    {
        $field = SettingField::enum('mode', ['full', 'redacted'], 'full');
        $this->assertSame('full', $field->cast('nope')); // invalid → default
    }

    public function testSanitizerRunsAfterTypeCoercion(): void
    {
        // '5' is int-cast to 5, then the sanitizer adds 1 → proves coerce-then-sanitize order.
        $field = SettingField::int('x', 0, null, static fn ($v) => $v + 1);
        $this->assertSame(6, $field->cast('5'));
    }

    public function testSanitizerAppliesToStringField(): void
    {
        $field = SettingField::string('name', '', null, static fn ($v) => trim($v));
        $this->assertSame('bob', $field->cast('  bob  '));
    }
}
