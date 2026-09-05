<?php

namespace BitApps\WPKit\Settings;

/**
 * Ordered collection of SettingField definitions, keyed by field key.
 */
final class SettingsSchema
{
    /**
     * @var array<string,SettingField>
     */
    private array $fields = [];

    /**
     * Register one or more fields, preserving insertion order.
     */
    public function add(SettingField ...$fields): self
    {
        foreach ($fields as $field) {
            $this->fields[$field->key()] = $field;
        }

        return $this;
    }

    /**
     * Check whether a field is registered for the given key.
     */
    public function has(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    /**
     * Fetch the field registered for the given key, or null if none.
     */
    public function field(string $key): ?SettingField
    {
        return $this->fields[$key] ?? null;
    }

    /**
     * All registered fields, keyed by field key, in insertion order.
     *
     * @return array<string,SettingField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * Map of field key to its default value.
     *
     * @return array<string,mixed>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->fields as $key => $field) {
            $defaults[$key] = $field->default();
        }

        return $defaults;
    }

    /**
     * Unique field groups, in the order they were first seen.
     *
     * @return array<int,string>
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->fields as $field) {
            $group = $field->group();
            if ($group !== null && !\in_array($group, $groups, true)) {
                $groups[] = $group;
            }
        }

        return $groups;
    }
}
