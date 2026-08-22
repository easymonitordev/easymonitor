<?php

declare(strict_types=1);

namespace App\Enums;

enum AssertionType: string
{
    case None = 'none';
    case KeywordPresent = 'keyword_present';
    case KeywordAbsent = 'keyword_absent';

    /**
     * Human-readable label for the assertion selector.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'None — status code only',
            self::KeywordPresent => 'Response body must contain keyword',
            self::KeywordAbsent => 'Response body must not contain keyword',
        };
    }

    /**
     * Short summary shown on the monitor page, e.g. 'Body contains "OK"'.
     */
    public function summary(string $keyword): string
    {
        return match ($this) {
            self::None => '',
            self::KeywordPresent => __('Body contains ":keyword"', ['keyword' => $keyword]),
            self::KeywordAbsent => __('Body does not contain ":keyword"', ['keyword' => $keyword]),
        };
    }

    /**
     * All enum values, for validation rules.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
