<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

/**
 * The prebuilt site themes (the boss, 2026-10-02: prebuilt themes are the base; the AI picks and customises one).
 *
 * A theme is a complete look over the SAME section markup every module reads — colours, fonts, a layout for every
 * section (BlockPatchSchema::VARIANTS) and a stylesheet — so the contact form, search data, reviews, booking and tracking keep working under every theme.
 * Each stylesheet lives beside the block templates in Ui/views/site/themes/<id>.css, styles only the published class
 * names and the colour/font variables, and loads nothing from outside the page. Ids come only from this list, so a
 * theme id can never name a file outside that directory.
 */
final class SiteThemes
{
    public const THEMES = [
        'bold-trade' => [
            'label' => 'Bold Trade',
            'for' => 'Builders, roofers, plumbers, electricians, painters',
            'palette' => ['surface' => '#f3f2ee', 'card' => '#ffffff', 'ink' => '#15171a', 'primary' => '#f5b800', 'accent' => '#a66f00'],
            'type_pairing' => ['heading' => 'Helvetica, Arial, sans-serif', 'body' => 'Helvetica, Arial, sans-serif'],
            'hero' => 'split',
            'layouts' => ['hero' => 'split', 'services' => 'cards', 'reviews_strip' => 'quote', 'faq' => 'list', 'about' => 'split', 'contact' => 'card', 'booking_button' => 'banner', 'stats' => 'row'],
        ],
        'clean-clinic' => [
            'label' => 'Clean Clinic',
            'for' => 'Dentists, physios, clinics, therapists',
            'palette' => ['surface' => '#f6f9f9', 'card' => '#ffffff', 'ink' => '#12302d', 'primary' => '#0f766e', 'accent' => '#0e7490'],
            'type_pairing' => ['heading' => 'system-ui, sans-serif', 'body' => 'system-ui, sans-serif'],
            'hero' => 'centered',
            'layouts' => ['hero' => 'centered', 'services' => 'cards', 'reviews_strip' => 'cards', 'faq' => 'cards', 'about' => 'centered', 'contact' => 'columns', 'booking_button' => 'banner', 'stats' => 'cards'],
        ],
        'warm-local' => [
            'label' => 'Warm Local',
            'for' => 'Cafes, bakeries, salons, shops',
            'palette' => ['surface' => '#fbf6ef', 'card' => '#ffffff', 'ink' => '#2b1d16', 'primary' => '#a23e2a', 'accent' => '#6b7f3a'],
            'type_pairing' => ['heading' => 'Georgia, serif', 'body' => 'Trebuchet MS, sans-serif'],
            'hero' => 'split',
            'layouts' => ['hero' => 'split', 'services' => 'list', 'reviews_strip' => 'quote', 'faq' => 'list', 'about' => 'centered', 'contact' => 'card', 'booking_button' => 'banner', 'stats' => 'row'],
        ],
        'modern-dark' => [
            'label' => 'Modern Dark',
            'for' => 'Detailing, tech repair, studios, gyms',
            'palette' => ['surface' => '#0e1014', 'card' => '#171a21', 'ink' => '#eceef3', 'primary' => '#8ea2ff', 'accent' => '#7fe0c0'],
            'type_pairing' => ['heading' => 'system-ui, sans-serif', 'body' => 'system-ui, sans-serif'],
            'hero' => 'centered',
            'layouts' => ['hero' => 'centered', 'services' => 'cards', 'reviews_strip' => 'row', 'faq' => 'columns', 'about' => 'split', 'contact' => 'columns', 'booking_button' => 'banner', 'stats' => 'cards'],
        ],
        'classic-pro' => [
            'label' => 'Classic Professional',
            'for' => 'Solicitors, accountants, estate agents, consultants',
            'palette' => ['surface' => '#fbfaf7', 'card' => '#ffffff', 'ink' => '#1a2333', 'primary' => '#1f3a5f', 'accent' => '#a07a2c'],
            'type_pairing' => ['heading' => 'Palatino, Book Antiqua, serif', 'body' => 'Helvetica, Arial, sans-serif'],
            'hero' => 'split',
            'layouts' => ['hero' => 'split', 'services' => 'list', 'reviews_strip' => 'quote', 'faq' => 'columns', 'about' => 'split', 'contact' => 'columns', 'booking_button' => 'inline', 'stats' => 'row'],
        ],
        'fresh-friendly' => [
            'label' => 'Fresh Friendly',
            'for' => 'Cleaners, gardeners, pet care, childcare',
            'palette' => ['surface' => '#f4fbf6', 'card' => '#ffffff', 'ink' => '#12291c', 'primary' => '#1f9d55', 'accent' => '#e08a00'],
            'type_pairing' => ['heading' => 'Trebuchet MS, sans-serif', 'body' => 'Tahoma, Segoe UI, sans-serif'],
            'hero' => 'split',
            'layouts' => ['hero' => 'split', 'services' => 'cards', 'reviews_strip' => 'row', 'faq' => 'cards', 'about' => 'plain', 'contact' => 'card', 'booking_button' => 'banner', 'stats' => 'cards'],
        ],
    ];

    /**
     * @return array<string, mixed>|null
     */
    public static function get(?string $id): ?array
    {
        if ($id === null || ! isset(self::THEMES[$id])) {
            return null;
        }

        return ['id' => $id] + self::THEMES[$id];
    }

    public static function css(string $id): string
    {
        if (! isset(self::THEMES[$id])) {
            return '';
        }
        $path = __DIR__.'/../Ui/views/site/themes/'.$id.'.css';
        $css = is_file($path) ? file_get_contents($path) : false;

        return is_string($css) ? $css : '';
    }
}
