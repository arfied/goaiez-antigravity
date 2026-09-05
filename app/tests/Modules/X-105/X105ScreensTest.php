<?php

declare(strict_types=1);

namespace Tests\Modules\X105;

use App\Modules\X105\Actions\OutreachStartAction;
use App\Modules\X105\Events\DemoRequested;
use App\Modules\X105\Models\LadderStep;
use App\Modules\X105\Ui\PipelineBoard;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class X105ScreensTest extends TestCase
{
    public function test_pipeline_board_shows_tenant_ladders(): void
    {
        $biz1 = TestCase::provisionTenant(['name' => 'Biz 1']);
        $biz2 = TestCase::provisionTenant(['name' => 'Biz 2']);

        $startAction = new OutreachStartAction;

        Tenancy::set($biz1->id);
        $ladder1 = $startAction->startLadder(
            businessId: $biz1->id,
            personId: 100,
            businessRating: 4.0,
            explicitDistress: false
        );

        Tenancy::set($biz2->id);
        $ladder2 = $startAction->startLadder(
            businessId: $biz2->id,
            personId: 200,
            businessRating: 4.0,
            explicitDistress: false
        );

        // GET assertOk
        Tenancy::set($biz1->id);
        $response = $this->actingAs($biz1->owner)->get(route('x-105.pipeline-board'));
        $response->assertOk();
        $response->assertSee('Ladder #'.$ladder1->id);
        $response->assertDontSee('Ladder #'.$ladder2->id);

        // Livewire test
        Livewire::actingAs($biz1->owner)
            ->test(PipelineBoard::class, ['businessId' => $biz1->id])
            ->assertSee('Ladder #'.$ladder1->id)
            ->assertDontSee('Ladder #'.$ladder2->id);
    }

    public function test_halt_action_cancels_steps(): void
    {
        $biz = TestCase::provisionTenant();
        Tenancy::set($biz->id);
        $startAction = new OutreachStartAction;
        $ladder = $startAction->startLadder($biz->id, 100, 4.0, false);

        Livewire::actingAs($biz->owner)
            ->test(PipelineBoard::class, ['businessId' => $biz->id])
            ->assertSee('wire:submit="halt('.$ladder->id.')"', false)
            ->call('halt', $ladder->id)
            ->assertHasNoErrors();

        Tenancy::set($biz->id);
        $ladder->refresh();
        $this->assertEquals('halted', $ladder->status);

        $cancelledCount = LadderStep::where('business_id', $biz->id)
            ->where('ladder_id', $ladder->id)
            ->where('status', 'cancelled')
            ->count();

        $this->assertGreaterThan(0, $cancelledCount);
    }

    public function test_request_demo_dispatches_event(): void
    {
        Event::fake([DemoRequested::class]);

        $biz = TestCase::provisionTenant();
        Tenancy::set($biz->id);
        $startAction = new OutreachStartAction;
        $ladder = $startAction->startLadder($biz->id, 100, 4.0, false);

        Livewire::actingAs($biz->owner)
            ->test(PipelineBoard::class, ['businessId' => $biz->id])
            ->assertSee('wire:submit="requestDemo('.$ladder->id.', \'Next Tuesday\')"', false)
            ->call('requestDemo', $ladder->id, 'Next Tuesday')
            ->assertHasNoErrors();

        Tenancy::set($biz->id);
        $ladder->refresh();
        $this->assertEquals('demo_requested', $ladder->status);
        Event::assertDispatched(DemoRequested::class);
    }

    public function test_action_on_other_tenant_ladder_fails(): void
    {
        $biz1 = TestCase::provisionTenant();
        $biz2 = TestCase::provisionTenant();

        $startAction = new OutreachStartAction;

        Tenancy::set($biz2->id);
        $ladder2 = $startAction->startLadder($biz2->id, 200, 4.0, false);

        Livewire::actingAs($biz1->owner)
            ->test(PipelineBoard::class, ['businessId' => $biz1->id])
            ->call('halt', $ladder2->id)
            ->assertSet('actionFailed', true);

        Tenancy::set($biz2->id);
        $ladder2->refresh();
        $this->assertEquals('active', $ladder2->status); // Nothing written
    }

    public function test_empty_state_and_sample_toggle(): void
    {
        $biz = TestCase::provisionTenant();

        Livewire::actingAs($biz->owner)
            ->test(PipelineBoard::class, ['businessId' => $biz->id])
            ->assertSee('No ladders found')
            ->assertSee('wire:click="toggleSample"', false)
            ->call('toggleSample')
            ->assertSet('isSample', true)
            ->assertSee('Ladder #9999');
    }
}
