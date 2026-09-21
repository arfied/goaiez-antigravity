<?php

declare(strict_types=1);

namespace Tests\Modules\X127\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X127\Ui\MetricProofPanel;
use Livewire\Livewire;
use Tests\TestCase;

class MetricProofPanelScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-127.metric-proof-panel'))->assertOk();

        Livewire::test(MetricProofPanel::class)->assertOk();
    }

    public function test_can_record_metric(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(MetricProofPanel::class)
            ->set('metricKey', 'dau')
            ->set('publishedValue', '1500')
            ->set('liveQuery', 'SELECT count(*) FROM users')
            ->call('recordMetric')
            ->assertSet('error', null)
            ->assertSet('success', 'Recorded metric. The live query is stored for proof; nothing runs it yet. Note that if a metric already exists, it is updated rather than duplicated.');

        $this->assertDatabaseHas('published_metrics', [
            'business_id' => $biz->id,
            'metric_key' => 'dau',
            'status' => 'published',
        ]);

        $this->get(route('x-127.metric-proof-panel'))
            ->assertOk()
            ->assertSee('dau')
            ->assertSee('published')
            ->assertDontSee('No public metrics published.');
    }

    public function test_refuses_empty_metric(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(MetricProofPanel::class)
            ->set('metricKey', '')
            ->set('publishedValue', '1500')
            ->set('liveQuery', 'SELECT count(*) FROM users')
            ->call('recordMetric')
            ->assertSet('error', 'All fields are required.');

        $this->assertDatabaseMissing('published_metrics', [
            'business_id' => $biz->id,
        ]);
    }
}
