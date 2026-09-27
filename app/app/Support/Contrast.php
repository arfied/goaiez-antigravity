<?php

declare(strict_types=1);

namespace App\Support;

/**
 * WCAG 2.x contrast ratio between two six-digit hex colours: sRGB → linear
 * (the 0.03928 / 12.92 / 2.4 curve) → relative luminance (0.2126 R + 0.7152 G
 * + 0.0722 B) → (L1 + 0.05) / (L2 + 0.05), lighter over darker. Text needs
 * 4.5:1 (AA) against its background; this is the first computed ratio in this
 * repository — every earlier "4.5:1 gate" compared a number a caller supplied.
 */
final class Contrast
{
    public const float AA_TEXT = 4.5;

    public static function ratio(string $hexA, string $hexB): float
    {
        $la = self::luminance($hexA);
        $lb = self::luminance($hexB);
        [$hi, $lo] = $la >= $lb ? [$la, $lb] : [$lb, $la];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    public static function isHex(string $hex): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    private static function luminance(string $hex): float
    {
        if (! self::isHex($hex)) {
            throw new \InvalidArgumentException("not a six-digit hex colour: {$hex}");
        }
        $rgb = [];
        foreach ([1, 3, 5] as $i) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            $rgb[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
    }
}
