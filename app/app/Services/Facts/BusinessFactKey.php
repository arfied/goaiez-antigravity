<?php

declare(strict_types=1);

namespace App\Services\Facts;

/**
 * The owner-stated facts the website, and later the assistants, read from ONE
 * place. Each has a label the owner sees and a ceiling the screen enforces from
 * here, never from a literal in the blade.
 */
final class BusinessFactKey
{
    public const string TAGLINE = 'tagline';

    public const string DESCRIPTION = 'description';

    public const string LICENCE_NUMBER = 'licence_number';

    public const string INSURANCE = 'insurance';

    public const string SERVICE_AREA = 'service_area';

    public const string YEARS_IN_BUSINESS = 'years_in_business';

    public const string INDUSTRY = 'industry';

    /** @return array<string, array{label: string, hint: string, max: int}> */
    public static function all(): array
    {
        return [
            self::TAGLINE => ['label' => 'One line that says what you do', 'hint' => 'Under the name on your home page.', 'max' => 120],
            self::DESCRIPTION => ['label' => 'About your business', 'hint' => 'Two or three sentences in your own words; the About section uses it.', 'max' => 1200],
            self::LICENCE_NUMBER => ['label' => 'Licence number', 'hint' => 'Shown exactly as you write it.', 'max' => 80],
            self::INSURANCE => ['label' => 'Insurance', 'hint' => 'For example "Insured and bonded". We never add a claim you did not make.', 'max' => 160],
            self::SERVICE_AREA => ['label' => 'Where you work', 'hint' => 'Towns, counties or a radius, in words.', 'max' => 240],
            self::YEARS_IN_BUSINESS => ['label' => 'Years in business', 'hint' => 'A number, or leave it empty.', 'max' => 4],
            self::INDUSTRY => ['label' => 'Your industry', 'hint' => 'The closest of six; it picks the starting point for your site. Leave it empty to use what Google says about you.', 'max' => 16],
        ];
    }
}
