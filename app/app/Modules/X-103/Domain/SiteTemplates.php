<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

/**
 * The site templates (the boss's brief, 2026-10-04: "a template library, not a site generator"). A template is a whole page
 * designed in advance for one kind of business — its own markup, its own stylesheet, its own section order. The owner picks
 * one; the AI only fills the page's sections with words and pictures. Nothing the AI writes can change a template's layout:
 * the template reads each section's fields by name and ignores the rest, and a section the template does not list is not
 * drawn at all.
 *
 * Each template lives in Ui/views/site/templates/<id>/ as page.blade.php and style.css. Ids come only from this list, so an
 * id can never name a file outside that directory. The page reads the same section blocks every other part of the product
 * reads (pages.draft_blocks), so the Studio, the Ask box, inline editing, publishing and the search data keep working.
 */
final class SiteTemplates
{
    public const TEMPLATES = [
        'trades-pro' => [
            'label' => 'Trades Pro',
            'for' => 'Plumbers, HVAC, electricians, roofers',
            'families' => ['trades'],
            'palette' => ['surface' => '#f4f3ef', 'card' => '#ffffff', 'ink' => '#0f1c2e', 'primary' => '#f2711c', 'accent' => '#b4500e'],
            'type_pairing' => ['heading' => 'Montserrat, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'stats', 'services', 'about', 'reviews_strip', 'gallery', 'faq', 'cta_band', 'contact'],
        ],
        'calm-spa' => [
            'label' => 'Calm Spa',
            'for' => 'Day spas, massage, facials, wellness studios',
            'families' => ['medspa', 'care'],
            'palette' => ['surface' => '#f6f1ea', 'card' => '#fffdf9', 'ink' => '#2d2621', 'primary' => '#56634b', 'accent' => '#8f6447'],
            'type_pairing' => ['heading' => 'Playfair Display, Georgia, serif', 'body' => 'Nunito, system-ui, sans-serif'],
            'sections' => ['hero', 'about', 'services', 'gallery', 'reviews_strip', 'booking_button', 'faq', 'cta_band', 'contact'],
        ],
        'polish-bar' => [
            'label' => 'Polish Bar',
            'for' => 'Nail salons, lash and brow bars',
            'families' => ['care'],
            'palette' => ['surface' => '#fff8f8', 'card' => '#ffffff', 'ink' => '#2a1730', 'primary' => '#c2185b', 'accent' => '#a3245e'],
            'type_pairing' => ['heading' => 'Poppins, system-ui, sans-serif', 'body' => 'Nunito, system-ui, sans-serif'],
            'sections' => ['hero', 'services', 'gallery', 'about', 'reviews_strip', 'booking_button', 'faq', 'cta_band', 'contact'],
        ],
        'maker-market' => [
            'label' => 'Maker Market',
            'for' => 'Small shops, makers, boutiques, gift and home stores',
            'families' => [],
            'palette' => ['surface' => '#faf8f4', 'card' => '#ffffff', 'ink' => '#1c1b19', 'primary' => '#a0472a', 'accent' => '#9c4a24'],
            'type_pairing' => ['heading' => 'DM Sans, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'products', 'about', 'gallery', 'reviews_strip', 'faq', 'cta_band', 'contact'],
        ],
        'trades-clean' => [
            'label' => 'Trades Clean',
            'for' => 'Plumbers, HVAC, electricians, cleaners — light and plain, built around the phone number',
            'families' => ['trades'],
            'palette' => ['surface' => '#ffffff', 'card' => '#f2f6fb', 'ink' => '#0d2238', 'primary' => '#0b5cc2', 'accent' => '#0b5cc2'],
            'type_pairing' => ['heading' => 'Poppins, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'stats', 'services', 'about', 'gallery', 'reviews_strip', 'faq', 'cta_band', 'contact'],
        ],
        'spa-luxe' => [
            'label' => 'Spa Luxe',
            'for' => 'Day spas, massage, med-spas, wellness — dark and gold',
            'families' => ['medspa', 'care'],
            'palette' => ['surface' => '#0f0e0c', 'card' => '#1a1815', 'ink' => '#f3eee6', 'primary' => '#c8a96a', 'accent' => '#c8a96a'],
            'type_pairing' => ['heading' => 'Playfair Display, Georgia, serif', 'body' => 'Lora, Georgia, serif'],
            'sections' => ['hero', 'about', 'services', 'gallery', 'reviews_strip', 'booking_button', 'faq', 'cta_band', 'contact'],
        ],
        'nail-studio' => [
            'label' => 'Nail Studio',
            'for' => 'Nail, lash and brow studios — black and white, big type, a square photo grid',
            'families' => ['care'],
            'palette' => ['surface' => '#ffffff', 'card' => '#f4f4f2', 'ink' => '#121212', 'primary' => '#121212', 'accent' => '#9b2c58'],
            'type_pairing' => ['heading' => 'Montserrat, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'services', 'gallery', 'about', 'reviews_strip', 'booking_button', 'faq', 'cta_band', 'contact'],
        ],
        'corner-boutique' => [
            'label' => 'Corner Boutique',
            'for' => 'Boutiques, gift shops, florists and bakeries that sell online',
            'families' => [],
            'palette' => ['surface' => '#fbf7f2', 'card' => '#ffffff', 'ink' => '#2b2320', 'primary' => '#7a4b3a', 'accent' => '#9c5b48'],
            'type_pairing' => ['heading' => 'Playfair Display, Georgia, serif', 'body' => 'Nunito, system-ui, sans-serif'],
            'sections' => ['hero', 'products', 'about', 'gallery', 'reviews_strip', 'faq', 'cta_band', 'contact'],
        ],
    ];

    /**
     * Every template, the ones made for this kind of business (an IndustryFamily value) first, the rest after in list order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function forFamily(?string $family): array
    {
        $matching = array_filter(self::TEMPLATES, static fn (array $t): bool => $family !== null && in_array($family, $t['families'], true));

        return $matching + self::TEMPLATES;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(mixed $id): ?array
    {
        if (! is_string($id) || ! isset(self::TEMPLATES[$id])) {
            return null;
        }

        return ['id' => $id] + self::TEMPLATES[$id];
    }

    public static function css(string $id): string
    {
        if (! isset(self::TEMPLATES[$id])) {
            return '';
        }
        $path = __DIR__.'/../Ui/views/site/templates/'.$id.'/style.css';
        $css = is_file($path) ? file_get_contents($path) : false;

        return is_string($css) ? $css : '';
    }

    public static function view(string $id): string
    {
        return 'x-103::site.templates.'.$id.'.page';
    }
}
