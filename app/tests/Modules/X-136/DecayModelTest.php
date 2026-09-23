<?php

declare(strict_types=1);

namespace Tests\Modules\X136;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X136\Actions\DecayModelSetAction;
use App\Modules\X136\Actions\SignalScoreAction;
use App\Modules\X136\Models\DecayModel;
use App\Modules\X136\Ui\SignalVolumePrecisionView;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DecayModelTest extends TestCase
{
    protected int $businessId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessId = TestCase::provisionTenant()->id;
        Tenancy::set($this->businessId);
    }

    public function test_ensure_on_score_creates_row_with_registry_defaults(): void
    {
        // change the registry value in the test via DefaultsRegistry::set() and prove the new default is used
        app(DefaultsRegistry::class)->set('signals.decay.half_life_days', 22, 'test');
        app(DefaultsRegistry::class)->set('signals.decay.rate_pct', 8.5, 'test');

        $action = new SignalScoreAction;
        $action->recordAndScore($this->businessId, 'prospect-1', 'test_type', [], 80.0);

        $model = DecayModel::where('business_id', $this->businessId)
            ->where('signal_type', 'test_type')
            ->first();

        $this->assertNotNull($model);
        $this->assertSame(22, $model->half_life_days);
        // Using assertEquals with delta for decimal
        $this->assertEqualsWithDelta(0.085, $model->decay_rate, 0.001);
    }

    public function test_set_action_happy_path_and_both_refusals(): void
    {
        $action = new DecayModelSetAction;

        // Refusal: half_life_days < 1
        $result = $action->handle($this->businessId, 'type1', 0, 0.05);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('refused', $result);

        // Refusal: decay_rate < 0.001 or > 0.999
        $result = $action->handle($this->businessId, 'type1', 10, 1.5);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('refused', $result);

        // Happy path
        $result = $action->handle($this->businessId, 'type1', 14, 0.05);
        $this->assertInstanceOf(DecayModel::class, $result);
        $this->assertSame(14, $result->half_life_days);
        $this->assertEqualsWithDelta(0.05, $result->decay_rate, 0.001);

        // Update happy path
        $result = $action->handle($this->businessId, 'type1', 20, 0.10);
        $this->assertInstanceOf(DecayModel::class, $result);
        $this->assertSame(20, $result->half_life_days);
        $this->assertEqualsWithDelta(0.10, $result->decay_rate, 0.001);
    }

    public function test_screen_real_get_shows_model_and_savedecay_changes_what_renders(): void
    {
        app(DefaultsRegistry::class)->set('signals.decay.half_life_days', 14, 'test');
        app(DefaultsRegistry::class)->set('signals.decay.rate_pct', 5.0, 'test'); // wait, rate_pct is 5.0 usually?

        // the view renders 14 days / 5% when rate is 0.05 because $decay->decay_rate * 100
        $action = new SignalScoreAction;
        $action->recordAndScore($this->businessId, 'p2', 'hiring', [], 80.0);

        // "real GET shows '14 days / 5%' for a scored type"
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        app(DefaultsRegistry::class)->set('signals.decay.half_life_days', 14, 'test');
        app(DefaultsRegistry::class)->set('signals.decay.rate_pct', 5.0, 'test');

        $action->recordAndScore($biz->id, 'p2', 'hiring', [], 80.0);

        // Ensure the DecayModel exists with 14 and 0.05
        DecayModel::where('business_id', $biz->id)->update([
            'half_life_days' => 14,
            'decay_rate' => 0.05,
        ]);

        $this->get(route('x-136.signal-volume-precision'))
            ->assertOk()
            ->assertSee('14 days / 5%');

        Livewire::actingAs($owner)
            ->test(SignalVolumePrecisionView::class, ['businessId' => $biz->id])
            ->set('editHalfLife.hiring', 25)
            ->set('editDecayRate.hiring', 0.12)
            ->call('saveDecay', 'hiring')
            ->assertSee('25 days / 12%');
    }

    public function test_screen_no_tenant_403(): void
    {
        Tenancy::forget();
        Livewire::test(SignalVolumePrecisionView::class, ['businessId' => 0])
            ->assertForbidden();
    }
}
