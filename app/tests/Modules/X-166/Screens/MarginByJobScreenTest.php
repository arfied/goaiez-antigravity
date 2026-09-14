<?php

declare(strict_types=1);

namespace Tests\Modules\X166\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X166\Models\JobCost;
use App\Modules\X166\Ui\MarginByJob;
use Livewire\Livewire;
use Tests\TestCase;

class MarginByJobScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // 1. Real GET empty -> empty state
        $this->get(route('x-166.margin-by-job'))
            ->assertOk()
            ->assertSee('No costed jobs yet. A job is costed when it completes.');

        // 2. Seed a JobCost
        JobCost::create([
            'business_id' => $biz->id,
            'job_id' => 99991234, // Distinctive field
            'price_book_version' => 'v1.4',
            'tech_id' => 50,
            'service_type' => 'install',
            'source' => 'direct',
            'revenue_cents' => 1500,
            'total_cost_cents' => 500,
            'labor_cost_cents' => 250,
            'materials_cost_cents' => 250,
            'overhead_cost_cents' => 0,
            'gross_margin_cents' => 1000,
            'gross_margin_pct' => 66.6,
            'is_sample' => false,
        ]);

        // 3. Real GET -> assertSee that field
        $this->get(route('x-166.margin-by-job'))
            ->assertOk()
            ->assertSee('99991234')
            ->assertSee('v1.4');

        // 4. Livewire test
        Livewire::test(MarginByJob::class)->assertOk();
    }
}
