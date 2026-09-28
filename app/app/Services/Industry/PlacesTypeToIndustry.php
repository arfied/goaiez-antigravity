<?php

declare(strict_types=1);

namespace App\Services\Industry;

use App\Enums\IndustryFamily;

/**
 * Google Places types → one of the six industry families. Provenance: the
 * Places API (New) place-type table, read 2026-09-24; a type not listed here
 * maps to nothing (never a guess). The WHOLE categories array is scanned in
 * order — primaryType first, then types — and the first listed match wins,
 * so a "point_of_interest" ahead of a "plumber" costs nothing. Owner ruling
 * 2026-09-24: the six families are the industry key.
 */
final class PlacesTypeToIndustry
{
    /** @var array<string, string> Places type → IndustryFamily value */
    private const array MAP = [
        // trades
        'plumber' => 'trades', 'electrician' => 'trades', 'roofing_contractor' => 'trades', 'general_contractor' => 'trades',
        'hvac_contractor' => 'trades', 'painter' => 'trades', 'locksmith' => 'trades', 'moving_company' => 'trades',
        'landscaper' => 'trades', 'home_improvement_store' => 'trades', 'pest_control_service' => 'trades', 'cleaning_service' => 'trades',
        'house_cleaning_service' => 'trades', 'carpenter' => 'trades', 'flooring_store' => 'trades', 'window_installation_service' => 'trades',
        // auto
        'car_repair' => 'auto', 'car_dealer' => 'auto', 'car_wash' => 'auto', 'auto_parts_store' => 'auto', 'tire_shop' => 'auto',
        'car_rental' => 'auto', 'gas_station' => 'auto', 'body_shop' => 'auto', 'auto_repair_shop' => 'auto', 'towing_service' => 'auto',
        // care
        'hair_salon' => 'care', 'hair_care' => 'care', 'barber_shop' => 'care', 'nail_salon' => 'care', 'beauty_salon' => 'care',
        'pet_store' => 'care', 'veterinary_care' => 'care', 'pet_groomer' => 'care', 'dog_grooming' => 'care', 'child_care_agency' => 'care',
        // food
        'restaurant' => 'food', 'cafe' => 'food', 'bakery' => 'food', 'bar' => 'food', 'catering_service' => 'food', 'coffee_shop' => 'food',
        'pizza_restaurant' => 'food', 'food_truck' => 'food', 'event_venue' => 'food', 'banquet_hall' => 'food', 'wedding_venue' => 'food',
        // office
        'lawyer' => 'office', 'accounting' => 'office', 'insurance_agency' => 'office', 'real_estate_agency' => 'office',
        'tax_preparation_service' => 'office', 'consultant' => 'office', 'financial_planner' => 'office', 'notary_public' => 'office',
        'dentist' => 'office', 'dental_clinic' => 'office', 'doctor' => 'office', 'physiotherapist' => 'office', 'chiropractor' => 'office',
        // medspa
        'spa' => 'medspa', 'day_spa' => 'medspa', 'massage' => 'medspa', 'skin_care_clinic' => 'medspa', 'wellness_center' => 'medspa',
        'yoga_studio' => 'medspa', 'gym' => 'medspa', 'fitness_center' => 'medspa', 'sauna' => 'medspa', 'tanning_studio' => 'medspa',
    ];

    /** @param list<string> $categories */
    public function fromCategories(array $categories): ?IndustryFamily
    {
        foreach ($categories as $type) {
            if (! is_string($type)) {
                continue;
            }
            $value = self::MAP[strtolower(trim($type))] ?? null;
            if ($value !== null) {
                return IndustryFamily::from($value);
            }
        }

        return null;
    }
}
