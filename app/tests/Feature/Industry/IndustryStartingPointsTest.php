<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Enums\IndustryFamily;
use App\Models\IndustryStartingPoint;
use App\Services\Industry\IndustryStartingPoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndustryStartingPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_six_rows(): void
    {
        $this->assertCount(6, IndustryStartingPoint::all());
        $cases = IndustryFamily::cases();
        $this->assertCount(6, $cases);

        foreach ($cases as $family) {
            $row = IndustryStartingPoint::where('family', $family->value)->first();
            $this->assertNotNull($row);
            $this->assertSame('hero', $row->section_order[0]);
            $this->assertArrayHasKey('surface', $row->palette);
            $this->assertArrayHasKey('ink', $row->palette);
            $this->assertArrayHasKey('primary', $row->palette);
            $this->assertArrayHasKey('accent', $row->palette);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $row->palette['surface']);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $row->palette['ink']);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $row->palette['primary']);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $row->palette['accent']);
        }
    }

    public function test_for_null_returns_general_default(): void
    {
        $service = app(IndustryStartingPoints::class);
        $result = $service->for(null);
        $this->assertSame([
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
        ], $result['section_order']);
    }

    public function test_for_trades_returns_specific_order(): void
    {
        $service = app(IndustryStartingPoints::class);
        $result = $service->for(IndustryFamily::Trades);
        $this->assertSame('booking_button', $result['section_order'][1]);
    }

    public function test_deleting_row_returns_default(): void
    {
        IndustryStartingPoint::where('family', IndustryFamily::Food->value)->delete();
        $service = app(IndustryStartingPoints::class);
        $result = $service->for(IndustryFamily::Food);
        $this->assertSame(IndustryStartingPoints::DEFAULT['section_order'], $result['section_order']);
    }
}
