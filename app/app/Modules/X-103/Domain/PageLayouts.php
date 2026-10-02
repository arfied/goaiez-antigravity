<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

/**
 * Named running orders for the sections a page ALREADY has. A layout never adds, removes or rewrites a
 * block — SectionOrder::apply only reorders — so choosing one can never put invented content on a page.
 * A null order means "the industry starting point's order" for that business.
 */
final class PageLayouts
{
    public const LAYOUTS = [
        'book' => [
            'label' => 'Book first',
            'explanation' => 'Puts your booking button and booking form straight under the headline, then your services and reviews.',
            'order' => ['hero', 'stats', 'booking_button', 'booking_form', 'services', 'reviews_strip', 'gallery', 'about', 'team', 'faq', 'cta_band', 'contact', 'form'],
        ],
        'proof' => [
            'label' => 'Reviews first',
            'explanation' => 'Leads with what your customers say and your photos, then your services and how to book.',
            'order' => ['hero', 'stats', 'reviews_strip', 'gallery', 'services', 'booking_button', 'booking_form', 'about', 'team', 'faq', 'cta_band', 'contact', 'form'],
        ],
        'story' => [
            'label' => 'Story first',
            'explanation' => 'Leads with who you are and your team, then your work, your services and your reviews.',
            'order' => ['hero', 'stats', 'about', 'team', 'gallery', 'services', 'reviews_strip', 'booking_button', 'booking_form', 'faq', 'cta_band', 'contact', 'form'],
        ],
        'services' => [
            'label' => 'Services first',
            'explanation' => 'Leads with what you offer and how to book, then your reviews and your story.',
            'order' => ['hero', 'stats', 'services', 'booking_button', 'booking_form', 'reviews_strip', 'about', 'gallery', 'team', 'faq', 'cta_band', 'contact', 'form'],
        ],
        'industry' => [
            'label' => 'Industry order',
            'explanation' => 'Your industry\'s usual running order — the one a new site starts from.',
            'order' => null,
        ],
    ];
}
