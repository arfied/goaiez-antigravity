<?php

declare(strict_types=1);

namespace Tests\Feature\Actuation;

use App\Enums\ActuationTier;
use App\Enums\SiteChangeVerdict;
use App\Enums\SpeedFixStatus;
use App\Models\PlatformSetting;
use App\Models\SiteChange;
use App\Models\SpeedFixRecord;
use App\Services\Actuation\ChangeMeasurer;
use App\Services\Actuation\SiteChanges;
use App\Services\Actuation\SiteMeasurements;
use App\Services\Actuation\SpeedFixes;
use App\Services\Actuation\T3FaqBlock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActuationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_t3_faq_block_honours_max_items(): void
    {
        PlatformSetting::write('actuation.faq.max_items', 2, 'test');

        $result = T3FaqBlock::fromFields([
            'faq' => [
                ['q' => 'A?', 'a' => 'A'],
                ['q' => 'B?', 'a' => 'B'],
                ['q' => 'C?', 'a' => 'C'],
            ],
        ]);

        $this->assertNull($result);
    }

    public function test_speed_fixes_honours_min_hours(): void
    {
        PlatformSetting::write('speed.min_hours_between_fixes', 10, 'test');

        $location = \Tests\Support\speedLocation();
        SpeedFixRecord::factory()->create([
            'location_id' => $location->id,
            'applied_at' => CarbonImmutable::now()->subHours(8),
            'status' => SpeedFixStatus::Settled,
            'verdict' => SiteChangeVerdict::Kept,
        ]);

        $service = app(SpeedFixes::class);
        $next = $service->next($location, ActuationTier::T3, CarbonImmutable::now());

        $this->assertNull($next);
    }

    public function test_site_changes_honours_undo_minutes(): void
    {
        PlatformSetting::write('sites.undo.in_progress_minutes', 5, 'test');

        $change = SiteChange::factory()->applied()->create([
            'undo_requested_at' => CarbonImmutable::now()->subMinutes(6),
        ]);

        $service = app(SiteChanges::class);

        // This is a bit abstract since the state checker is private, but the intent is clear
        $this->assertFalse($service->isStillLive((int) $change->id));
    }

    public function test_site_measurements_honours_revert_ceiling(): void
    {
        PlatformSetting::write('sites.revert.attempt_ceiling', 3, 'test');

        $change = SiteChange::factory()->applied()->create([
            'revert_attempts' => 3,
        ]);

        // Attempting to revert again will fail because it reached the ceiling
        $service = app(SiteMeasurements::class);
        $service->sweep(CarbonImmutable::now());

        $change->refresh();
        $this->assertEquals(3, $change->revert_attempts);
    }

    public function test_change_measurer_honours_baseline_days(): void
    {
        PlatformSetting::write('sites.measure.baseline_days', 5, 'test');

        $location = \Tests\Support\speedLocation();
        $change = SiteChange::factory()->applied()->create([
            'location_id' => $location->id,
            'applied_at' => CarbonImmutable::now()->subDays(10),
        ]);

        $service = app(ChangeMeasurer::class);
        $verdict = $service->measure($change, CarbonImmutable::now());

        $this->assertNotNull($verdict);
    }
}
