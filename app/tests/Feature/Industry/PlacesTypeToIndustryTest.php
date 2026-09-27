<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Enums\IndustryFamily;
use App\Services\Industry\PlacesTypeToIndustry;
use PHPUnit\Framework\TestCase;

class PlacesTypeToIndustryTest extends TestCase
{
    public function test_maps_places_types_to_industry_family(): void
    {
        $map = new PlacesTypeToIndustry;

        $this->assertEquals(IndustryFamily::Trades, $map->fromCategories(['point_of_interest', 'plumber']));
        $this->assertEquals(IndustryFamily::Auto, $map->fromCategories(['car_repair']));
        $this->assertEquals(IndustryFamily::Trades, $map->fromCategories(['PLUMBER ']));
        $this->assertNull($map->fromCategories(['unknown_type']));
        $this->assertNull($map->fromCategories([]));

        $this->assertEquals(IndustryFamily::Medspa, $map->fromCategories(['spa']));
        $this->assertEquals(IndustryFamily::Office, $map->fromCategories(['lawyer']));
        $this->assertEquals(IndustryFamily::Food, $map->fromCategories(['restaurant']));
        $this->assertEquals(IndustryFamily::Care, $map->fromCategories(['hair_salon']));
    }
}
