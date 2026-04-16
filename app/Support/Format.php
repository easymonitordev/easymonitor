<?php

declare(strict_types=1);

namespace App\Support;

class Format
{
    /**
     * Format a millisecond value for display. Values ≥ 1000ms render as
     * seconds with one decimal ("1.2s") so long response times read naturally;
     * smaller values stay as "450ms".
     */
    public static function ms(?int $ms): string
    {
        if ($ms === null) {
            return '--';
        }

        if ($ms < 1000) {
            return $ms.'ms';
        }

        $seconds = $ms / 1000;

        return rtrim(rtrim(number_format($seconds, 1, '.', ''), '0'), '.').'s';
    }

    /**
     * Same as ms() but returns a two-part array so callers can style the unit
     * differently (e.g. small "ms" suffix next to a large number).
     *
     * @return array{0: string, 1: string}
     */
    public static function msParts(?int $ms): array
    {
        if ($ms === null) {
            return ['--', ''];
        }

        if ($ms < 1000) {
            return [(string) $ms, 'ms'];
        }

        $seconds = $ms / 1000;
        $formatted = rtrim(rtrim(number_format($seconds, 1, '.', ''), '0'), '.');

        return [$formatted, 's'];
    }
}
