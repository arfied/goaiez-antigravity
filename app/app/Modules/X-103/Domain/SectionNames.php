<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

/** What an owner calls each kind of section — the Studio inspector shows these, never the block type. */
final class SectionNames
{
    public const LABELS = [
        'about' => 'About',
        'booking_button' => 'Booking button',
        'booking_form' => 'Booking form',
        'contact' => 'Contact details',
        'cta_band' => 'Call to action',
        'faq' => 'Questions and answers',
        'form' => 'Form',
        'gallery' => 'Photos',
        'hero' => 'Top banner',
        'reviews_strip' => 'Reviews',
        'services' => 'Services',
        'stats' => 'Numbers',
        'team' => 'Team',
        'video_embed' => 'Video',
    ];

    public static function label(?string $type): string
    {
        if ($type === null || $type === '') {
            return 'Section';
        }

        return self::LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
