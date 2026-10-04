<?php

declare(strict_types=1);

namespace App\Services\Industry;

use App\Enums\IndustryFamily;
use App\Models\Business;
use App\Models\IndustryStartingPoint;

class IndustryStartingPoints
{
    public const VARIANTS = ['a', 'b', 'c', 'd', 'e', 'f'];

    /**
     * Three designed looks. Unlike b and c, which transform the industry palette, these replace the
     * palette and the type pair outright and keep the industry's running order. Every pair the renderer
     * paints clears 4.5:1 — IndustryStartingPointsTest proves it with SiteStyle::contrast().
     */
    public const LOOKS = [
        'd' => [
            'palette' => ['surface' => '#fbf7f1', 'card' => '#f3ebe0', 'ink' => '#2a211b', 'primary' => '#9a3412', 'accent' => '#7c5e10'],
            'type_pairing' => ['heading' => 'Georgia, serif', 'body' => 'system-ui, sans-serif'],
        ],
        'e' => [
            'palette' => ['surface' => '#11151c', 'card' => '#1b212b', 'ink' => '#e8ecf2', 'primary' => '#d6b24a', 'accent' => '#7cc4d6'],
            'type_pairing' => ['heading' => 'Trebuchet MS, sans-serif', 'body' => 'Tahoma, Segoe UI, sans-serif'],
        ],
        'f' => [
            'palette' => ['surface' => '#fdfbf6', 'card' => '#f4efe4', 'ink' => '#1b2436', 'primary' => '#1e3a5f', 'accent' => '#7a5c2e'],
            'type_pairing' => ['heading' => 'Palatino, Book Antiqua, serif', 'body' => 'Georgia, serif'],
        ],
    ];

    public const LABELS = [
        'a' => 'Your industry colours',
        'b' => 'Industry colours, toned',
        'c' => 'Industry colours, reviews first',
        'd' => 'Warm',
        'e' => 'Midnight',
        'f' => 'Classic',
    ];

    /**
     * what every site was before starting points existed
     */
    public const DEFAULT = [
        'palette' => [
            'surface' => '#16191c',
            'ink' => '#f2f2f0',
            'primary' => '#f2f2f0',
            'accent' => '#f2f2f0',
        ],
        'type_pairing' => [
            'heading' => 'sans-serif',
            'body' => 'sans-serif',
        ],
        'section_order' => [
            'hero',
            'about',
            'gallery',
            'services',
            'reviews_strip',
            'booking_button',
            'booking_form',
            'faq',
            'contact',
            'form',
        ],
        'family' => null,
    ];

    public function __construct(private readonly IndustryResolver $industryResolver) {}

    /**
     * @return array{palette: array<string,string>, type_pairing: array<string,string>, section_order: list<string>, family: ?string}
     */
    public function for(?IndustryFamily $family): array
    {
        if ($family === null) {
            return self::DEFAULT;
        }

        $row = IndustryStartingPoint::where('family', $family->value)->first();

        if ($row === null) {
            return self::DEFAULT;
        }

        $palette = $row->palette;
        if (! isset($palette['card'])) {
            $palette['card'] = $palette['surface'];
        }

        return [
            'palette' => $palette,
            'type_pairing' => $row->type_pairing,
            'section_order' => $row->section_order,
            'family' => $family->value,
        ];
    }

    public function variant(array $sp, string $v): array
    {
        if (! in_array($v, self::VARIANTS, true)) {
            $v = 'a';
        }

        if ($v === 'a') {
            return $sp;
        }

        if (isset(self::LOOKS[$v])) {
            $sp['palette'] = self::LOOKS[$v]['palette'];
            $sp['type_pairing'] = self::LOOKS[$v]['type_pairing'];

            return $sp;
        }

        if ($v === 'b') {
            $heading = $sp['type_pairing']['heading'];
            $body = $sp['type_pairing']['body'];
            $sp['type_pairing']['heading'] = $body;
            $sp['type_pairing']['body'] = $heading;

            $surface = $sp['palette']['surface'] ?? '#ffffff';
            $card = $sp['palette']['card'] ?? $surface;

            $surfaceLum = $this->luminance($surface);
            $e0e0e0Lum = $this->luminance('#e0e0e0');

            $toward = $surfaceLum > $e0e0e0Lum ? '#000000' : '#ffffff';

            $sp['palette']['surface'] = $this->mix($surface, $toward, 0.35);
            $sp['palette']['card'] = $this->mix($card, $toward, 0.35);

            return $sp;
        }

        $primary = $sp['palette']['primary'];
        $accent = $sp['palette']['accent'];
        $sp['palette']['primary'] = $accent;
        $sp['palette']['accent'] = $primary;

        $order = $sp['section_order'];
        $newOrder = [];
        $others = [];

        foreach ($order as $type) {
            if ($type === 'hero' || $type === 'reviews_strip' || $type === 'gallery') {
                continue;
            }
            $others[] = $type;
        }

        $newOrder[] = 'hero';
        if (in_array('reviews_strip', $order, true)) {
            $newOrder[] = 'reviews_strip';
        }
        if (in_array('gallery', $order, true)) {
            $newOrder[] = 'gallery';
        }
        $newOrder = array_merge($newOrder, $others);
        $sp['section_order'] = $newOrder;

        return $sp;
    }

    public function forBusiness(int $businessId): array
    {
        $family = $this->industryResolver->for($businessId)['family'];
        $variantLetter = Business::whereKey($businessId)->value('site_variant') ?? 'a';

        $result = $this->variant($this->for($family), $variantLetter);

        $tokens = Business::whereKey($businessId)->value('site_tokens');
        if (is_string($tokens)) {
            $tokens = json_decode($tokens, true);
        }

        if (is_array($tokens)) {
            if (isset($tokens['theme']) && is_string($tokens['theme'])) {
                $result['theme'] = $tokens['theme'];
            }
            if (isset($tokens['corners']) && is_string($tokens['corners'])) {
                $result['corners'] = $tokens['corners'];
            }
            if (isset($tokens['template']) && is_string($tokens['template'])) {
                $result['template'] = $tokens['template'];
            }
            if (isset($tokens['palette']) && is_array($tokens['palette'])) {
                $result['palette'] = array_replace($result['palette'], $tokens['palette']);
            }
            if (isset($tokens['type_pairing']) && is_array($tokens['type_pairing'])) {
                $result['type_pairing'] = array_replace($result['type_pairing'], $tokens['type_pairing']);
            }
        }

        return $result;
    }

    private function mix(string $hex, string $toward, float $t): string
    {
        $r1 = hexdec(substr($hex, 1, 2));
        $g1 = hexdec(substr($hex, 3, 2));
        $b1 = hexdec(substr($hex, 5, 2));

        $r2 = hexdec(substr($toward, 1, 2));
        $g2 = hexdec(substr($toward, 3, 2));
        $b2 = hexdec(substr($toward, 5, 2));

        $r = (int) round($r1 * (1 - $t) + $r2 * $t);
        $g = (int) round($g1 * (1 - $t) + $g2 * $t);
        $b = (int) round($b1 * (1 - $t) + $b2 * $t);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function luminance(string $hex): float
    {
        $rgb = [];
        foreach ([1, 3, 5] as $i) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            $rgb[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
    }
}
