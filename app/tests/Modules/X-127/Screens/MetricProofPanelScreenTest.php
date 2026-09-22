<?php

declare(strict_types=1);

namespace Tests\Modules\X127\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X127\Ui\MetricProofPanel;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    public function test_a_matching_value_verifies_the_metric(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(MetricProofPanel::class)
            ->set('metricKey', 'mrr')
            ->set('publishedValue', '1000')
            ->set('liveQuery', 'q')
            ->call('recordMetric');

        Livewire::test(MetricProofPanel::class)
            ->set('verifyKey', 'mrr')
            ->set('verifyLiveValue', '1000')
            ->call('verifyMetric')
            ->assertSet('verifyError', null)
            ->assertSet('verifySuccess', 'Metric mrr checks out at 1000. The published claim matches the live value.');

        $this->assertDatabaseHas('published_metrics', [
            'business_id' => $biz->id,
            'metric_key' => 'mrr',
            'status' => 'verified',
        ]);
    }

    public function test_a_drifted_value_pulls_the_claim_from_publication(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(MetricProofPanel::class)
            ->set('metricKey', 'mrr')
            ->set('publishedValue', '1000')
            ->set('liveQuery', 'q')
            ->call('recordMetric');

        Livewire::test(MetricProofPanel::class)
            ->set('verifyKey', 'mrr')
            ->set('verifyLiveValue', '1100')
            ->call('verifyMetric')
            ->assertSet('verifyError', null)
            ->assertSet('verifySuccess', 'Metric mrr did NOT match — published 1000, live 1100. The claim has been PULLED from publication.');

        $this->assertDatabaseHas('published_metrics', [
            'business_id' => $biz->id,
            'metric_key' => 'mrr',
            'status' => 'pulled_drifted',
            'published_value' => null,
        ]);
    }

    public function test_refuses_an_empty_live_value(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(MetricProofPanel::class)
            ->set('metricKey', 'mrr')
            ->set('publishedValue', '1000')
            ->set('liveQuery', 'q')
            ->call('recordMetric');

        Livewire::test(MetricProofPanel::class)
            ->set('verifyKey', 'mrr')
            ->set('verifyLiveValue', '   ')
            ->call('verifyMetric')
            ->assertSet('verifyError', 'Enter the value you measured just now.');

        $this->assertDatabaseHas('published_metrics', [
            'business_id' => $biz->id,
            'metric_key' => 'mrr',
            'status' => 'published',
        ]);
    }

    public function test_verifying_another_tenants_metric_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        Tenancy::set($bizB->id);

        Livewire::test(MetricProofPanel::class)
            ->set('metricKey', 'mrr')
            ->set('publishedValue', '1000')
            ->set('liveQuery', 'q')
            ->call('recordMetric');

        Tenancy::set($bizA->id);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(MetricProofPanel::class)
            ->set('verifyKey', 'mrr')
            ->set('verifyLiveValue', '1000')
            ->call('verifyMetric');
    }

    public function test_the_panel_shows_a_pulled_claim(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(MetricProofPanel::class)
            ->set('metricKey', 'mrr')
            ->set('publishedValue', '1000')
            ->set('liveQuery', 'q')
            ->call('recordMetric');

        Livewire::test(MetricProofPanel::class)
            ->set('verifyKey', 'mrr')
            ->set('verifyLiveValue', '1100')
            ->call('verifyMetric');

        $this->get(route('x-127.metric-proof-panel'))
            ->assertOk()
            ->assertSee('mrr: [PULLED] [pulled_drifted]')
            ->assertDontSee('mrr: 1000 ');
    }
}
