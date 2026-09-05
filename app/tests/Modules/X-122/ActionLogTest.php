<?php

declare(strict_types=1);

namespace Tests\Modules\X122;

use App\Models\Business;
use App\Modules\X122\Models\ActionInvocation;
use App\Modules\X122\Ui\ActionLog;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ActionLogTest extends TestCase
{

    public function test_seeded_row_renders(): void
    {
        $business = Business::factory()->create();
        Tenancy::set((int) $business->id);

        ActionInvocation::factory()->create([
            'business_id' => $business->id,
            'action_name' => 'test.seeded.action',
        ]);

        Livewire::test(ActionLog::class)
            ->assertSee('test.seeded.action');
    }

    public function test_refusal_outranks_newer_success(): void
    {
        $business = Business::factory()->create();
        Tenancy::set((int) $business->id);

        $refused = ActionInvocation::factory()->create([
            'business_id' => $business->id,
            'action_name' => 'refused.action',
            'status' => 'refused',
            'created_at' => now()->subDay(),
        ]);

        $success = ActionInvocation::factory()->create([
            'business_id' => $business->id,
            'action_name' => 'success.action',
            'status' => 'completed',
            'created_at' => now(),
        ]);

        Livewire::test(ActionLog::class)
            ->assertSeeInOrder(['refused.action', 'success.action']);
    }

    public function test_search_narrows_list(): void
    {
        $business = Business::factory()->create();
        Tenancy::set((int) $business->id);

        ActionInvocation::factory()->create([
            'business_id' => $business->id,
            'action_name' => 'alpha.action',
        ]);

        ActionInvocation::factory()->create([
            'business_id' => $business->id,
            'action_name' => 'beta.action',
        ]);

        Livewire::test(ActionLog::class)
            ->assertSee('alpha.action')
            ->assertSee('beta.action')
            ->set('search', 'alpha')
            ->assertSee('alpha.action')
            ->assertDontSee('beta.action');
    }
}
