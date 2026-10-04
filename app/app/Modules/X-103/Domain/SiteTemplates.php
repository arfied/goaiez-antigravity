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
    ];

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
