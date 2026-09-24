<?php

declare(strict_types=1);

namespace App\Services\Industry;

use App\Enums\IndustryFamily;
use App\Models\IndustryStartingPoint;

class IndustryStartingPoints
{
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

        return [
            'palette' => $row->palette,
            'type_pairing' => $row->type_pairing,
            'section_order' => $row->section_order,
            'family' => $family->value,
        ];
    }
}
