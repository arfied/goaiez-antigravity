<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Enums\IndustryFamily;
use App\Models\IndustryStartingPoint;
use App\Services\Industry\IndustryResolver;
use App\Services\Industry\IndustryStartingPoints;
use App\Services\Industry\SiteStyle;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class IndustryStartingPointsTest extends TestCase
{
    use RefreshesTenantDatabase;

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

    public function test_for_and_variants(): void
    {
        $resolver = app(IndustryResolver::class);
        $sp = app(IndustryStartingPoints::class);

        $base = $sp->for(IndustryFamily::Trades);
        $this->assertSame($base['palette']['surface'], $base['palette']['card']);

        $b = $sp->variant($base, 'b');
        $this->assertSame($base['type_pairing']['heading'], $b['type_pairing']['body']);
        $this->assertSame($base['type_pairing']['body'], $b['type_pairing']['heading']);
        $this->assertNotSame($base['palette']['surface'], $b['palette']['surface']);

        $c = $sp->variant($base, 'c');
        $this->assertSame($base['palette']['primary'], $c['palette']['accent']);
        $this->assertSame($base['palette']['accent'], $c['palette']['primary']);
        $this->assertSame('hero', $c['section_order'][0]);
        $this->assertSame('reviews_strip', $c['section_order'][1]);

        $z = $sp->variant($base, 'z');
        $this->assertSame($base, $z);

        $biz = TestCase::provisionTenant(['industry' => IndustryFamily::Trades->value]);
        $biz->update(['industry' => 'trades', 'site_variant' => 'c']);

        $forBiz = $sp->forBusiness($biz->id);
        $this->assertSame($c['palette']['primary'], $forBiz['palette']['primary']);
        $this->assertSame($c['palette']['accent'], $forBiz['palette']['accent']);
    }

    public function test_authored_looks_are_readable_on_every_pair_the_renderer_paints(): void
    {
        $this->assertSame(['d', 'e', 'f'], array_keys(IndustryStartingPoints::LOOKS));

        foreach (IndustryStartingPoints::LOOKS as $id => $look) {
            $p = $look['palette'];
            $this->assertSame(SiteStyle::PALETTE_KEYS, array_keys($p), "look {$id}");
            $this->assertContains($look['type_pairing']['heading'], SiteStyle::FONT_STACKS, "look {$id}");
            $this->assertContains($look['type_pairing']['body'], SiteStyle::FONT_STACKS, "look {$id}");

            foreach ([['ink', 'surface'], ['ink', 'card'], ['primary', 'surface'], ['accent', 'surface'], ['accent', 'card']] as [$fg, $bg]) {
                $this->assertGreaterThanOrEqual(4.5, SiteStyle::contrast($p[$fg], $p[$bg]), "look {$id}: {$fg} on {$bg}");
            }
        }
    }

    public function test_an_authored_look_changes_colours_and_fonts_and_keeps_the_running_order(): void
    {
        $sp = app(IndustryStartingPoints::class);
        $base = $sp->for(IndustryFamily::Trades);

        $e = $sp->variant($base, 'e');
        $this->assertSame(IndustryStartingPoints::LOOKS['e']['palette'], $e['palette']);
        $this->assertSame(IndustryStartingPoints::LOOKS['e']['type_pairing'], $e['type_pairing']);
        $this->assertSame($base['section_order'], $e['section_order']);
        $this->assertSame($base['family'], $e['family']);
        $this->assertNotSame($base['palette'], $e['palette']);

        $biz = $this->provisionTenant(['industry' => IndustryFamily::Trades->value]);
        $biz->update(['industry' => 'trades', 'site_variant' => 'e']);
        $this->assertSame('#11151c', $sp->forBusiness($biz->id)['palette']['surface']);
    }

    public function test_every_look_has_a_name(): void
    {
        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'f'], IndustryStartingPoints::VARIANTS);
        $this->assertSame(IndustryStartingPoints::VARIANTS, array_keys(IndustryStartingPoints::LABELS));
    }
}
