<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

/**
 * The modern fonts a site may use, served from our own server (public/site-fonts) — never from a font provider, so a
 * visitor's browser asks no third party for anything (the boss, 2026-10-03: "why does this still look horrible" — every site
 * could only use eight system fonts). Each family is its latin subset in regular (400) and bold (700), under the SIL Open Font
 * License; the licences sit beside the files. Published pages, custom domains and the Studio preview are all served by this
 * application, so a same-origin path reaches the files from all three.
 */
final class SiteFonts
{
    /** Family name => file name stem in public/site-fonts. */
    public const FAMILIES = [
        'Inter' => 'inter',
        'Poppins' => 'poppins',
        'Montserrat' => 'montserrat',
        'Nunito' => 'nunito',
        'DM Sans' => 'dm-sans',
        'Playfair Display' => 'playfair-display',
        'Lora' => 'lora',
        'Merriweather' => 'merriweather',
    ];

    public const WEIGHTS = [400, 700];

    public const URL_PREFIX = '/site-fonts/';

    /** The @font-face rules for whichever of these families lead the heading or body stack, or '' when neither does. */
    public static function faceCss(array $typePairing): string
    {
        $families = [];
        foreach (['heading', 'body'] as $role) {
            $stack = $typePairing[$role] ?? null;
            if (! is_string($stack)) {
                continue;
            }
            $first = trim(explode(',', $stack)[0], " \t\"'");
            if (isset(self::FAMILIES[$first])) {
                $families[$first] = self::FAMILIES[$first];
            }
        }

        $css = '';
        foreach ($families as $family => $stem) {
            foreach (self::WEIGHTS as $weight) {
                $css .= '@font-face { font-family: "'.$family.'"; font-style: normal; font-weight: '.$weight.'; font-display: swap; src: url("'
                    .self::URL_PREFIX.$stem.'-'.$weight.'.woff2") format("woff2"); }'."\n";
            }
        }

        return $css;
    }
}
