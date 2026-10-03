<?php

declare(strict_types=1);

namespace App\Services\Industry;

final class SiteStyle
{
    public const FONT_STACKS = ['system-ui, sans-serif', 'Georgia, serif', 'Helvetica, Arial, sans-serif', 'Palatino, Book Antiqua, serif', 'Verdana, Geneva, sans-serif', 'Trebuchet MS, sans-serif', 'Garamond, Times New Roman, serif', 'Tahoma, Segoe UI, sans-serif'];

    public const PALETTE_KEYS = ['surface', 'card', 'ink', 'primary', 'accent'];

    /** Corner styles a site may use (the stylesheet for each lives with the themes, in X-103's SiteThemes::CORNERS). */
    public const CORNERS = ['square', 'soft', 'round'];

    public static function contrast(string $a, string $b): float
    {
        $lum1 = self::luminance($a);
        $lum2 = self::luminance($b);

        $light = max($lum1, $lum2);
        $dark = min($lum1, $lum2);

        return ($light + 0.05) / ($dark + 0.05);
    }

    /**
     * $fg moved toward $toward in 10% steps until it reads at 4.5:1 on every background — how the renderer
     * paints accent TEXT (links, labels, prices), so a pale brand accent is never unreadable. A colour that
     * already reads is returned as chosen. Any input that is not #rrggbb returns $fg unchanged.
     *
     * @param  list<string>  $backgrounds
     */
    public static function readable(string $fg, array $backgrounds, string $toward): string
    {
        foreach ([$fg, $toward, ...$backgrounds] as $hex) {
            if (! self::isHex($hex)) {
                return $fg;
            }
        }

        for ($step = 0; $step <= 10; $step++) {
            $candidate = self::mix($fg, $toward, $step / 10);
            $reads = true;
            foreach ($backgrounds as $bg) {
                if (self::contrast($candidate, $bg) < 4.5) {
                    $reads = false;
                    break;
                }
            }
            if ($reads) {
                return $candidate;
            }
        }

        return strtolower($toward);
    }

    /**
     * Text colour for a filled button: the first candidate that reads at 4.5:1 on $bg, else the one that
     * reads best. Any input that is not #rrggbb returns the first candidate — today's behaviour.
     *
     * @param  non-empty-list<string>  $candidates
     */
    public static function textOn(string $bg, array $candidates): string
    {
        foreach ([$bg, ...$candidates] as $hex) {
            if (! self::isHex($hex)) {
                return $candidates[0];
            }
        }

        $best = $candidates[0];
        foreach ($candidates as $candidate) {
            if (self::contrast($candidate, $bg) >= 4.5) {
                return $candidate;
            }
            if (self::contrast($candidate, $bg) > self::contrast($best, $bg)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    private static function isHex(mixed $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    private static function mix(string $hex, string $toward, float $t): string
    {
        $out = '#';
        foreach ([1, 3, 5] as $i) {
            $a = hexdec(substr($hex, $i, 2));
            $b = hexdec(substr($toward, $i, 2));
            $out .= sprintf('%02x', (int) round($a * (1 - $t) + $b * $t));
        }

        return $out;
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

        if (isset($style['corners']) && is_string($style['corners']) && in_array($style['corners'], self::CORNERS, true)) {
            $clean['corners'] = $style['corners'];
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
