<?php

declare(strict_types=1);

namespace App\Services\Industry;

final class SiteStyle
{
    public const FONT_STACKS = ['system-ui, sans-serif', 'Georgia, serif', 'Helvetica, Arial, sans-serif', 'Palatino, Book Antiqua, serif', 'Verdana, Geneva, sans-serif', 'Trebuchet MS, sans-serif', 'Garamond, Times New Roman, serif', 'Tahoma, Segoe UI, sans-serif'];

    public const PALETTE_KEYS = ['surface', 'card', 'ink', 'primary', 'accent'];

    public static function contrast(string $a, string $b): float
    {
        $lum1 = self::luminance($a);
        $lum2 = self::luminance($b);

        $light = max($lum1, $lum2);
        $dark = min($lum1, $lum2);

        return ($light + 0.05) / ($dark + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $rgb = [];
        foreach ([1, 3, 5] as $i) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            $rgb[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
    }

    public static function validate(array $style, array $base): array
    {
        $clean = [];

        if (isset($style['palette']) && is_array($style['palette'])) {
            foreach (self::PALETTE_KEYS as $key) {
                if (isset($style['palette'][$key]) && is_string($style['palette'][$key]) && preg_match('/^#[0-9a-fA-F]{6}$/', $style['palette'][$key])) {
                    $clean['palette'][$key] = strtolower($style['palette'][$key]);
                }
            }
        }

        if (isset($style['type_pairing']) && is_array($style['type_pairing'])) {
            foreach (['heading', 'body'] as $key) {
                if (isset($style['type_pairing'][$key]) && is_string($style['type_pairing'][$key]) && in_array($style['type_pairing'][$key], self::FONT_STACKS, true)) {
                    $clean['type_pairing'][$key] = $style['type_pairing'][$key];
                }
            }
        }

        if (empty($clean)) {
            return ['ok' => false, 'reason' => 'No usable colour or font in that change.'];
        }

        $surface = $clean['palette']['surface'] ?? $base['palette']['surface'] ?? null;
        $ink = $clean['palette']['ink'] ?? $base['palette']['ink'] ?? null;

        if ($surface && $ink) {
            $contrast = self::contrast($surface, $ink);
            if ($contrast < 4.5) {
                $rounded = number_format($contrast, 1);

                return ['ok' => false, 'reason' => "That colour change would make text hard to read (contrast {$rounded}:1, needs 4.5:1)."];
            }
        }

        return ['ok' => true, 'style' => $clean];
    }
}
