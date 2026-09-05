<?php

namespace BitApps\WPKit\Settings;

/**
 * Typed, immutable definition of a single setting: its key, type, default, group, and cast/sanitize rules.
 */
final class SettingField
{
    public const TYPE_BOOL = 'bool';

    public const TYPE_INT = 'int';

    public const TYPE_STRING = 'string';

    public const TYPE_FLOAT = 'float';

    public const TYPE_ARRAY = 'array';

    public const TYPE_ENUM = 'enum';

    /**
     * @var null|callable
     */
    private $sanitizer;

    /**
     * @param mixed $default
     */
    private function __construct(
        private string $key,
        private string $type,
        private $default,
        private ?string $group = null,
        private ?array $choices = null,
        ?callable $sanitizer = null
    ) {
        $this->sanitizer = $sanitizer;
    }

    /**
     * Define a boolean setting.
     *
     * @param mixed $default
     */
    public static function bool(string $key, $default, ?string $group = null, ?callable $sanitizer = null): self
    {
        return new self($key, self::TYPE_BOOL, $default, $group, null, $sanitizer);
    }

    /**
     * Define an integer setting.
     *
     * @param mixed $default
     */
    public static function int(string $key, $default, ?string $group = null, ?callable $sanitizer = null): self
    {
        return new self($key, self::TYPE_INT, $default, $group, null, $sanitizer);
    }

    /**
     * Define a string setting.
     *
     * @param mixed $default
     */
    public static function string(string $key, $default, ?string $group = null, ?callable $sanitizer = null): self
    {
        return new self($key, self::TYPE_STRING, $default, $group, null, $sanitizer);
    }

    /**
     * Define a float setting.
     *
     * @param mixed $default
     */
    public static function float(string $key, $default, ?string $group = null, ?callable $sanitizer = null): self
    {
        return new self($key, self::TYPE_FLOAT, $default, $group, null, $sanitizer);
    }

    /**
     * Define an array setting.
     *
     * @param mixed $default
     */
    public static function arr(string $key, $default, ?string $group = null, ?callable $sanitizer = null): self
    {
        return new self($key, self::TYPE_ARRAY, $default, $group, null, $sanitizer);
    }

    /**
     * Define an enum setting restricted to a fixed list of choices.
     *
     * @param mixed $default
     */
    public static function enum(string $key, array $choices, $default, ?string $group = null, ?callable $sanitizer = null): self
    {
        return new self($key, self::TYPE_ENUM, $default, $group, $choices, $sanitizer);
    }

    /**
     * Return the field's unique key.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Return the field's value type (one of the TYPE_* constants).
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Return the field's default value.
     *
     * @return mixed
     */
    public function default()
    {
        return $this->default;
    }

    /**
     * Return the field's group, or null if it belongs to none.
     */
    public function group(): ?string
    {
        return $this->group;
    }

    /**
     * Return the enum field's allowed choices, or null for non-enum fields.
     */
    public function choices(): ?array
    {
        return $this->choices;
    }

    /**
     * Coerce a raw value to this field's type, then apply the sanitizer if one was given.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function cast($value)
    {
        $cast = $this->castByType($value);

        return $this->sanitizer !== null ? ($this->sanitizer)($cast) : $cast;
    }

    /**
     * Coerce a raw value to this field's type, without applying the sanitizer.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function castByType($value)
    {
        return match ($this->type) {
            self::TYPE_BOOL  => filter_var($value, \FILTER_VALIDATE_BOOLEAN),
            self::TYPE_INT   => (int) $value,
            self::TYPE_FLOAT => (float) $value,
            self::TYPE_ARRAY => (array) $value,
            self::TYPE_ENUM  => \in_array($value, $this->choices ?? [], true) ? $value : $this->default,
            default          => $value,
        };
    }
}
