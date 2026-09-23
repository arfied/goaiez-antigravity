<?php

declare(strict_types=1);

namespace Tests\Modules\X136;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X136\Actions\SignalScoreAction;
use App\Modules\X136\Models\DecayModel;
use App\Modules\X136\Models\Signal;
use App\Modules\X136\Models\SignalScore;
use App\Modules\X136\Ui\CoolingView;
use App\Modules\X136\Ui\SignalVolumePrecisionView;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class X136ScreensTest extends TestCase
{
    protected int $businessId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessId = TestCase::provisionTenant()->id;
        Tenancy::set($this->businessId);
    }

    public function test_cooling_view_mount_and_empty(): void
    {
        Livewire::test(CoolingView::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('Nobody is cooling');
    }

    public function test_cooling_view_sample_state(): void
    {
        Livewire::test(CoolingView::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('acme-roofing')
            ->assertSee('northside-dental')
            ->call('markDecayed', 'acme-roofing');

        $this->assertSame(0, SignalScore::where('business_id', $this->businessId)->count());
    }

    public function test_cooling_view_shows_signals(): void
    {
        $s = Signal::create([
            'business_id' => $this->businessId,
            'prospect_identifier' => 'test-prospect',
            'signal_type' => 'test_type',
        ]);

        SignalScore::create([
            'business_id' => $this->businessId,
            'signal_id' => $s->id,
            'prospect_identifier' => 'test-prospect',
            'signal_value' => 75.0,
            'cooling_status' => 'cooling',
            'is_high_intent' => true,
        ]);

        Livewire::test(CoolingView::class, ['businessId' => $this->businessId])
            ->assertSee('test-prospect')
            ->assertSee('75')
            ->assertSee('test type')
            ->assertSee('0 days quiet');
    }

    public function test_cooling_view_mark_decayed_success(): void
    {
        $s = Signal::create([
            'business_id' => $this->businessId,
            'prospect_identifier' => 'test-prospect',
            'signal_type' => 'test_type',
        ]);

        $score = SignalScore::create([
            'business_id' => $this->businessId,
            'signal_id' => $s->id,
            'prospect_identifier' => 'test-prospect',
            'signal_value' => 75.0,
            'cooling_status' => 'cooling',
            'is_high_intent' => true,
        ]);

        Livewire::test(CoolingView::class, ['businessId' => $this->businessId])
            ->call('markDecayed', 'test-prospect')
            ->assertOk();

        $this->assertSame('decayed', $score->fresh()->cooling_status);
    }

    public function test_cooling_view_mark_decayed_unknown(): void
    {
        Livewire::test(CoolingView::class, ['businessId' => $this->businessId])
            ->call('markDecayed', 'unknown-prospect')
            ->assertSee('Unknown prospect identifier')
            ->assertSee('Action failed');
    }

    /**
     * [G3-27] a signal never sends
     */
    public function test_cooling_view_never_a_send(): void
    {
        Livewire::test(CoolingView::class, ['businessId' => $this->businessId])
            ->assertSee('A signal informs, it never sends')
            ->assertDontSeeHtml('Send Message')
            ->assertDontSeeHtml('wire:click="send"');

        $this->assertSame(0, DB::table('send_permits')->count());
        $this->assertSame(0, DB::table('outreach_messages')->count());
    }

    public function test_cooling_view_get_route(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        $s = Signal::create([
            'business_id' => $biz->id,
            'prospect_identifier' => 'seeded-prospect',
            'signal_type' => 'test_type',
        ]);

        SignalScore::create([
            'business_id' => $biz->id,
            'signal_id' => $s->id,
            'prospect_identifier' => 'seeded-prospect',
            'signal_value' => 80.0,
            'cooling_status' => 'cooling',
            'is_high_intent' => true,
        ]);

        $this->get(route('x-136.cooling'))
            ->assertOk()
            ->assertSee('seeded-prospect')
            ->assertSee('80');
    }

    public function test_cooling_view_lists_cooling_only(): void
    {
        $s1 = Signal::create([
            'business_id' => $this->businessId,
            'prospect_identifier' => 'fresh-co',
            'signal_type' => 'test_type',
        ]);

        SignalScore::create([
            'business_id' => $this->businessId,
            'signal_id' => $s1->id,
            'prospect_identifier' => 'fresh-co',
            'signal_value' => 92.5,
            'cooling_status' => 'fresh',
            'is_high_intent' => true,
        ]);

        Livewire::test(CoolingView::class, ['businessId' => $this->businessId])
            ->assertDontSee('fresh-co')
            ->assertDontSee('92.5');
    }

    public function test_signal_volume_precision_mount_and_empty(): void
    {
        Livewire::test(SignalVolumePrecisionView::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('No signal stats yet');
    }

    public function test_signal_volume_precision_sample(): void
    {
        Livewire::test(SignalVolumePrecisionView::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('hiring')
            ->assertSee('permit filed')
            ->assertSee('90% Precision');
    }

    public function test_signal_volume_precision_shows_stats(): void
    {
        $action = new SignalScoreAction;
        $action->recordAndScore($this->businessId, 'p1', 'pricing_visit', [], 80.0);
        $action->recordAndScore($this->businessId, 'p2', 'pricing_visit', [], 40.0);

        DecayModel::create([
            'business_id' => $this->businessId,
            'signal_type' => 'pricing_visit',
            'half_life_days' => 14,
            'decay_rate' => 0.05,
        ]);

        Livewire::test(SignalVolumePrecisionView::class, ['businessId' => $this->businessId])
            ->assertSee('pricing visit')
            ->assertSee('Volume: 2 total')
            ->assertSee('1 high-intent')
            ->assertSee('50% Precision')
            ->assertSee('14 days / 5%');
    }

    public function test_signal_volume_precision_no_decay_model(): void
    {
        $action = new SignalScoreAction;
        $action->recordAndScore($this->businessId, 'p1', 'hiring', [], 80.0);

        DecayModel::where('business_id', $this->businessId)->delete();

        Livewire::test(SignalVolumePrecisionView::class, ['businessId' => $this->businessId])
            ->assertSee('hiring')
            ->assertSee('No decay model yet. A model needs 30 days of events.');
    }

    public function test_signal_volume_precision_get_route(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        $s1 = Signal::create([
            'business_id' => $biz->id,
            'prospect_identifier' => 'p1',
            'signal_type' => 'hiring',
        ]);
        SignalScore::create([
            'business_id' => $biz->id,
            'signal_id' => $s1->id,
            'prospect_identifier' => 'p1',
            'signal_value' => 80.0,
            'is_high_intent' => true,
        ]);

        $this->get(route('x-136.signal-volume-precision'))
            ->assertOk()
            ->assertSee('hiring');
    }
}
