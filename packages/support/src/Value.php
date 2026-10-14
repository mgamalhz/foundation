<?php

namespace Mgamalhz\Support;

use BackedEnum;
use UnitEnum;

/**
 * Turns the values packages receive (backed enums, pure enums, scalars) into
 * the plain strings they store, so callers can pass whichever form they have.
 */
final class Value
{
    public static function key(BackedEnum|UnitEnum|string|int|null $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            default => (string) $value,
        };
    }

    /**
     * Recursively replace the values of sensitive keys (password, token, ...)
     * so they never reach a log table.
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $keys
     * @return array<array-key, mixed>
     */
    public static function redact(array $data, array $keys, string $mask = '[redacted]'): array
    {
        $keys = array_map('strtolower', $keys);

        foreach ($data as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), $keys, true)) {
                $data[$key] = $mask;
            } elseif (is_array($item)) {
                $data[$key] = self::redact($item, $keys, $mask);
            }
        }

        return $data;
    }
}
