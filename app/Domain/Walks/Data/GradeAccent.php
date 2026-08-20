<?php

namespace App\Domain\Walks\Data;

final class GradeAccent
{
    public static function from(?string $colour): ?string
    {
        if (! is_string($colour) || preg_match('/^#[0-9a-f]{6}$/i', $colour) !== 1) {
            return null;
        }

        return strtoupper($colour);
    }

    public static function style(?string $colour): string
    {
        $accent = self::from($colour);

        if ($accent === null) {
            return '';
        }

        return '--wm-grade-accent: '.$accent;
    }
}
