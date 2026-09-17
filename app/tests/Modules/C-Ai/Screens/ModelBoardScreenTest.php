<?php

declare(strict_types=1);

namespace Tests\Modules\CAi\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAi\Models\AiCall;
use App\Modules\CAi\Ui\ModelBoard;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ModelBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(ModelBoard::class)->assertOk();

        $this->get(route('c-ai.model-board'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No AI calls yet.');

        DB::statement("SET app.business_id = '{$biz->id}'");

        AiCall::create([
            'business_id' => $biz->id,
            'model_requested' => 'default_primary',
            'model_served' => 'default_primary',
            'ttft_ms' => 200,
            'latency_ms' => 300,
            'tokens_in' => 150,
            'tokens_out' => 50,
            'cost_cents' => 10,
            'prompt_version' => 1,
            'task' => 'distinctive_task_4601',
            'provider' => 'simulated',
            'model' => 'default_primary',
            'task_id' => null,
            'fallback_reason' => null,
            'prompt_id' => null,
            'usage_unavailable' => false,
        ]);

        DB::statement('RESET app.business_id');

        $this->get(route('c-ai.model-board'))
            ->assertOk()
            ->assertSee('default_primary')
            ->assertSee('200ms');
    }
}
