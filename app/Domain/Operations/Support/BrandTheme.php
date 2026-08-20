<?php

namespace App\Domain\Operations\Support;

use App\Domain\Operations\Models\SiteProfile;

final readonly class BrandTheme
{
    private const string DEFAULT_PRIMARY = '#526B3F';

    private const string DEFAULT_ACCENT = '#D6B269';

    private const string LIGHT_FOREGROUND = '#FFFFFF';

    private const string DARK_FOREGROUND = '#1F261C';

    private function __construct(
        public string $primaryColour,
        public string $primaryForeground,
        public string $accentColour,
        public string $accentForeground,
    ) {}

    public static function fromSiteProfile(SiteProfile $siteProfile): self
    {
        $primaryColour = self::normaliseColour(
            $siteProfile->primary_colour,
            self::DEFAULT_PRIMARY,
        );
        $accentColour = self::normaliseColour(
            $siteProfile->accent_colour,
            self::DEFAULT_ACCENT,
        );

        return new self(
            primaryColour: $primaryColour,
            primaryForeground: self::contrastSafeForeground($primaryColour),
            accentColour: $accentColour,
            accentForeground: self::contrastSafeForeground($accentColour),
        );
    }

    /**
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        return [
            '--wm-brand' => $this->primaryColour,
            '--wm-on-brand' => $this->primaryForeground,
            '--wm-accent' => $this->accentColour,
            '--wm-on-accent' => $this->accentForeground,
        ];
    }

    private static function normaliseColour(?string $colour, string $fallback): string
    {
        if (! is_string($colour) || preg_match('/^#[0-9a-f]{6}$/i', $colour) !== 1) {
            return $fallback;
        }

        return strtoupper($colour);
    }

    private static function contrastSafeForeground(string $background): string
    {
        $lightContrast = self::contrastRatio($background, self::LIGHT_FOREGROUND);
        $darkContrast = self::contrastRatio($background, self::DARK_FOREGROUND);

        return $lightContrast >= $darkContrast
            ? self::LIGHT_FOREGROUND
            : self::DARK_FOREGROUND;
    }

    private static function contrastRatio(string $first, string $second): float
    {
        $firstLuminance = self::relativeLuminance($first);
        $secondLuminance = self::relativeLuminance($second);

        return (max($firstLuminance, $secondLuminance) + 0.05)
            / (min($firstLuminance, $secondLuminance) + 0.05);
    }

    private static function relativeLuminance(string $colour): float
    {
        $channels = array_map(
            static fn (string $channel): float => hexdec($channel) / 255,
            str_split(substr($colour, 1), 2),
        );

        [$red, $green, $blue] = array_map(
            static fn (float $channel): float => $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);
    }
}
