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
            'sections' => ['hero', 'stats', 'services', 'reviews_strip', 'about', 'gallery', 'faq', 'cta_band', 'contact'],
        ],
        'calm-spa' => [
            'label' => 'Calm Spa',
            'for' => 'Day spas, massage, facials, wellness studios',
            'families' => ['medspa', 'care'],
            'palette' => ['surface' => '#f6f1ea', 'card' => '#fffdf9', 'ink' => '#2d2621', 'primary' => '#56634b', 'accent' => '#8f6447'],
            'type_pairing' => ['heading' => 'Playfair Display, Georgia, serif', 'body' => 'Nunito, system-ui, sans-serif'],
            'sections' => ['hero', 'services', 'gallery', 'reviews_strip', 'cta_band', 'booking_button', 'team', 'about', 'faq', 'contact'],
        ],
        'polish-bar' => [
            'label' => 'Polish Bar',
            'for' => 'Nail salons, lash and brow bars',
            'families' => ['care'],
            'palette' => ['surface' => '#fff8f8', 'card' => '#ffffff', 'ink' => '#2a1730', 'primary' => '#c2185b', 'accent' => '#a3245e'],
            'type_pairing' => ['heading' => 'Poppins, system-ui, sans-serif', 'body' => 'Nunito, system-ui, sans-serif'],
            'sections' => ['hero', 'services', 'gallery', 'reviews_strip', 'cta_band', 'booking_button', 'team', 'about', 'faq', 'contact'],
        ],
        'maker-market' => [
            'label' => 'Maker Market',
            'for' => 'Small shops, makers, boutiques, gift and home stores',
            'families' => [],
            'palette' => ['surface' => '#faf8f4', 'card' => '#ffffff', 'ink' => '#1c1b19', 'primary' => '#a0472a', 'accent' => '#9c4a24'],
            'type_pairing' => ['heading' => 'DM Sans, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'products', 'reviews_strip', 'about', 'gallery', 'faq', 'cta_band', 'contact'],
        ],
        'trades-clean' => [
            'label' => 'Trades Clean',
            'for' => 'Plumbers, HVAC, electricians, cleaners — light and plain, built around the phone number',
            'families' => ['trades'],
            'palette' => ['surface' => '#ffffff', 'card' => '#f2f6fb', 'ink' => '#0d2238', 'primary' => '#0b5cc2', 'accent' => '#0b5cc2'],
            'type_pairing' => ['heading' => 'Poppins, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'stats', 'services', 'reviews_strip', 'about', 'gallery', 'faq', 'cta_band', 'contact'],
        ],
        'spa-luxe' => [
            'label' => 'Spa Luxe',
            'for' => 'Day spas, massage, med-spas, wellness — dark and gold',
            'families' => ['medspa', 'care'],
            'palette' => ['surface' => '#0f0e0c', 'card' => '#1a1815', 'ink' => '#f3eee6', 'primary' => '#c8a96a', 'accent' => '#c8a96a'],
            'type_pairing' => ['heading' => 'Playfair Display, Georgia, serif', 'body' => 'Lora, Georgia, serif'],
            'sections' => ['hero', 'services', 'gallery', 'reviews_strip', 'cta_band', 'booking_button', 'team', 'about', 'faq', 'contact'],
        ],
        'nail-studio' => [
            'label' => 'Nail Studio',
            'for' => 'Nail, lash and brow studios — black and white, big type, a square photo grid',
            'families' => ['care'],
            'palette' => ['surface' => '#ffffff', 'card' => '#f4f4f2', 'ink' => '#121212', 'primary' => '#121212', 'accent' => '#9b2c58'],
            'type_pairing' => ['heading' => 'Montserrat, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'services', 'gallery', 'reviews_strip', 'cta_band', 'booking_button', 'team', 'about', 'faq', 'contact'],
        ],
        'corner-boutique' => [
            'label' => 'Corner Boutique',
            'for' => 'Boutiques, gift shops, florists and bakeries that sell online',
            'families' => [],
            'palette' => ['surface' => '#fbf7f2', 'card' => '#ffffff', 'ink' => '#2b2320', 'primary' => '#7a4b3a', 'accent' => '#9c5b48'],
            'type_pairing' => ['heading' => 'Playfair Display, Georgia, serif', 'body' => 'Nunito, system-ui, sans-serif'],
            'sections' => ['hero', 'products', 'reviews_strip', 'about', 'gallery', 'cta_band', 'faq', 'contact'],
        ],
        'trades-heritage' => [
            'label' => 'Trades Heritage',
            'for' => 'Family trades firms, roofers, builders, plumbers — cream and brick, a solid serif, stamped trust badges',
            'families' => ['trades'],
            'palette' => ['surface' => '#f7f1e6', 'card' => '#fffaf2', 'ink' => '#2a1d17', 'primary' => '#a4321f', 'accent' => '#a4321f'],
            'type_pairing' => ['heading' => 'Merriweather, Georgia, serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'stats', 'services', 'reviews_strip', 'about', 'gallery', 'faq', 'cta_band', 'contact'],
        ],
        'spa-bright' => [
            'label' => 'Spa Bright',
            'for' => 'Day spas, facials, massage, wellness and beauty studios — light, soft colour, rounded cards',
            'families' => ['medspa', 'care'],
            'palette' => ['surface' => '#fffdfb', 'card' => '#ffffff', 'ink' => '#2e2a3a', 'primary' => '#6b4fbb', 'accent' => '#b0466f'],
            'type_pairing' => ['heading' => 'Lora, Georgia, serif', 'body' => 'Nunito, system-ui, sans-serif'],
            'sections' => ['hero', 'services', 'gallery', 'reviews_strip', 'cta_band', 'booking_button', 'team', 'about', 'faq', 'contact'],
        ],
        'nail-pop' => [
            'label' => 'Nail Pop',
            'for' => 'Nail salons and nail-art studios — playful colour, a tilted photo grid, a menu of price pills',
            'families' => ['care'],
            'palette' => ['surface' => '#fdfaff', 'card' => '#ffffff', 'ink' => '#24123a', 'primary' => '#7c3aed', 'accent' => '#0e7c7e'],
            'type_pairing' => ['heading' => 'Poppins, system-ui, sans-serif', 'body' => 'Nunito, system-ui, sans-serif'],
            'sections' => ['hero', 'services', 'gallery', 'reviews_strip', 'cta_band', 'booking_button', 'team', 'about', 'faq', 'contact'],
        ],
        'shop-bold' => [
            'label' => 'Shop Bold',
            'for' => 'Online shops with a bold brand — dark, bright yellow, products up front',
            'families' => [],
            'palette' => ['surface' => '#111316', 'card' => '#1b1e23', 'ink' => '#f2f3f5', 'primary' => '#ffcc33', 'accent' => '#ffcc33'],
            'type_pairing' => ['heading' => 'DM Sans, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'products', 'reviews_strip', 'about', 'gallery', 'cta_band', 'faq', 'contact'],
        ],
        'bistro-table' => [
            'label' => 'Bistro Table',
            'for' => 'Restaurants, bistros and trattorias — cream and deep green, a printed menu, hours under the photo',
            'families' => ['food'],
            'palette' => ['surface' => '#f4ede0', 'card' => '#fbf7ef', 'ink' => '#1d2621', 'primary' => '#1f4a38', 'accent' => '#9a3b1f'],
            'type_pairing' => ['heading' => 'Playfair Display, Georgia, serif', 'body' => 'Lora, Georgia, serif'],
            'sections' => ['hero', 'services', 'gallery', 'reviews_strip', 'cta_band', 'booking_button', 'about', 'faq', 'contact'],
        ],
        'cafe-corner' => [
            'label' => 'Café Corner',
            'for' => 'Cafés, bakeries, breakfast and lunch spots, food trucks — warm and light, opening hours up front, a menu of cards',
            'families' => ['food'],
            'palette' => ['surface' => '#fbf5ec', 'card' => '#ffffff', 'ink' => '#2c211a', 'primary' => '#b5462a', 'accent' => '#a1401f'],
            'type_pairing' => ['heading' => 'Poppins, system-ui, sans-serif', 'body' => 'Nunito, system-ui, sans-serif'],
            'sections' => ['hero', 'services', 'gallery', 'reviews_strip', 'cta_band', 'booking_button', 'about', 'faq', 'contact'],
        ],
        'grill-house' => [
            'label' => 'Grill House',
            'for' => 'Grills, barbecue, burger and pizza places, taquerias — charcoal and fire orange, a menu board with big prices',
            'families' => ['food'],
            'palette' => ['surface' => '#141211', 'card' => '#1f1c1a', 'ink' => '#f4ede4', 'primary' => '#e8552b', 'accent' => '#f0a23c'],
            'type_pairing' => ['heading' => 'Montserrat, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'services', 'gallery', 'reviews_strip', 'cta_band', 'booking_button', 'about', 'faq', 'contact'],
        ],
        'garage-pro' => [
            'label' => 'Garage Pro',
            'for' => 'Auto repair shops, brake and tyre centres, mechanics — steel grey and racing red, an angled photo, numbered services',
            'families' => ['auto'],
            'palette' => ['surface' => '#f2f3f5', 'card' => '#ffffff', 'ink' => '#13161b', 'primary' => '#c8201e', 'accent' => '#b51c1a'],
            'type_pairing' => ['heading' => 'Montserrat, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'stats', 'services', 'reviews_strip', 'cta_band', 'booking_button', 'about', 'gallery', 'faq', 'contact'],
        ],
        'detail-studio' => [
            'label' => 'Detail Studio',
            'for' => 'Car detailing, ceramic coating, tint and wrap shops, car washes — near-black and electric teal, priced packages',
            'families' => ['auto'],
            'palette' => ['surface' => '#0b0d10', 'card' => '#151920', 'ink' => '#eef2f6', 'primary' => '#2bc4b6', 'accent' => '#2bc4b6'],
            'type_pairing' => ['heading' => 'DM Sans, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'stats', 'services', 'gallery', 'reviews_strip', 'cta_band', 'booking_button', 'about', 'faq', 'contact'],
        ],
        'hometown-auto' => [
            'label' => 'Hometown Auto',
            'for' => 'Family garages, tyre shops and small-town mechanics — white and royal blue, the words on a card over the photo, a checklist of services',
            'families' => ['auto'],
            'palette' => ['surface' => '#ffffff', 'card' => '#eef3fa', 'ink' => '#0f1d33', 'primary' => '#1747a6', 'accent' => '#a14a07'],
            'type_pairing' => ['heading' => 'Merriweather, Georgia, serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'stats', 'services', 'reviews_strip', 'cta_band', 'booking_button', 'about', 'gallery', 'faq', 'contact'],
        ],
        'counsel' => [
            'label' => 'Counsel',
            'for' => 'Accountants, law firms, financial advisers, consultants — ivory and navy, a brass rule, credentials up front',
            'families' => ['office'],
            'palette' => ['surface' => '#f7f5f0', 'card' => '#ffffff', 'ink' => '#16223a', 'primary' => '#16223a', 'accent' => '#8a6516'],
            'type_pairing' => ['heading' => 'Lora, Georgia, serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'stats', 'reviews_strip', 'services', 'cta_band', 'booking_button', 'team', 'about', 'faq', 'contact'],
        ],
        'clear-office' => [
            'label' => 'Clear Office',
            'for' => 'Insurance agencies, real estate offices, consultants, IT and marketing firms — white and deep teal, soft panels',
            'families' => ['office'],
            'palette' => ['surface' => '#ffffff', 'card' => '#f1f6f5', 'ink' => '#0e2422', 'primary' => '#0f6b62', 'accent' => '#0f6b62'],
            'type_pairing' => ['heading' => 'DM Sans, system-ui, sans-serif', 'body' => 'Inter, system-ui, sans-serif'],
            'sections' => ['hero', 'stats', 'reviews_strip', 'services', 'cta_band', 'booking_button', 'team', 'about', 'gallery', 'faq', 'contact'],
        ],
        'main-street' => [
            'label' => 'Main Street',
            'for' => 'Tax preparers, real estate agents, tutors, photographers, local studios — warm cream and violet, a photo in an arch',
            'families' => ['office'],
            'palette' => ['surface' => '#fffaf3', 'card' => '#ffffff', 'ink' => '#23223a', 'primary' => '#5b3fa8', 'accent' => '#b8452a'],
            'type_pairing' => ['heading' => 'Poppins, system-ui, sans-serif', 'body' => 'Nunito, system-ui, sans-serif'],
            'sections' => ['hero', 'services', 'stats', 'reviews_strip', 'cta_band', 'booking_button', 'team', 'about', 'gallery', 'faq', 'contact'],
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
