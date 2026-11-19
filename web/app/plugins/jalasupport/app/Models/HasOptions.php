<?php

declare(strict_types=1);

namespace JalaSupport\Models;

/**
 * Shared helpers for the string-backed ticket enums.
 */
trait HasOptions
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn(self $case): string => $case->value, self::cases());
    }

    /**
     * Value => label map, e.g. for select fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Resolve a case from its value or its human-readable label (case-insensitive).
     */
    public static function fromInput(string $input): ?self
    {
        $normalized = strtolower(trim($input));

        foreach (self::cases() as $case) {
            if ($case->value === $normalized || strtolower($case->label()) === $normalized) {
                return $case;
            }
        }

        return null;
    }
}
