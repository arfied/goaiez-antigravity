<?php

declare(strict_types=1);

namespace Tests\Modules\X156;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X156\Actions\IngestConnectAction;
use App\Modules\X156\Actions\IngestUploadAction;
use App\Modules\X156\Actions\IngestWebhookAction;
use App\Modules\X156\Models\IngestRejection;
use App\Modules\X156\Models\IngestRun;
use App\Modules\X156\Models\IngestSource;
use App\Modules\X156\Ui\ConnectSourceView;
use App\Modules\X156\Ui\IngestVolumeByView;
use App\Modules\X156\Ui\RejectedrowsListView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class X156ScreensTest extends TestCase
{
    protected int $businessId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessId = TestCase::provisionTenant(['name' => 'Source Tenant'])->id;
        Tenancy::set($this->businessId);

        IngestRun::where('business_id', $this->businessId)->delete();
        IngestRejection::where('business_id', $this->businessId)->delete();
        IngestSource::where('business_id', $this->businessId)->delete();
    }

    public function test_connect_source_mount_and_empty(): void
    {
        Livewire::test(ConnectSourceView::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('No sources connected yet')
            ->assertSeeHtml('wire:submit="connect"');
    }

    public function test_connect_source_creates_row(): void
    {
        Livewire::test(ConnectSourceView::class, ['businessId' => $this->businessId])
            ->set('sourceType', 'meta_lead_ad')
            ->set('sourceName', 'Meta Summer Leads')
            ->call('connect')
            ->assertSee('Meta Summer Leads');

        $this->assertDatabaseHas('ingest_sources', [
            'business_id' => $this->businessId,
            'source_type' => 'meta_lead_ad',
            'source_name' => 'Meta Summer Leads',
        ]);

        $source = IngestSource::where('business_id', $this->businessId)->first();
        $this->assertNotNull($source->secret_key);
    }

    public function test_connect_source_last_run_and_pause(): void
    {
        $source = app(IngestConnectAction::class)->connect($this->businessId, 'hubspot', 'HubSpot CRM');

        app(IngestUploadAction::class)->upload(
            $this->businessId,
            $source->id,
            [['foo' => 'bar'], ['foo' => 'baz'], ['foo' => 'qux']],
            'attest_screen_1'
        );

        Livewire::test(ConnectSourceView::class, ['businessId' => $this->businessId])
            ->assertSee('Has received data')
            ->assertSee('3 records')
            ->call('pause', $source->id)
            ->assertSee('Paused');

        $source->refresh();
        $this->assertFalse($source->is_active);
    }

    public function test_connect_source_error_state(): void
    {
        Livewire::test(ConnectSourceView::class, ['businessId' => $this->businessId])
            ->call('pause', 999999)
            ->assertSee('Action failed');

        $this->assertSame(0, IngestSource::where('business_id', $this->businessId)->count());
    }

    public function test_connect_source_sample_state(): void
    {
        $count = IngestSource::where('business_id', $this->businessId)->count();

        Livewire::test(ConnectSourceView::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('Meta lead forms')
            ->assertSee('Paused')
            ->set('sourceName', 'Should not write')
            ->call('connect');

        $this->assertEquals($count, IngestSource::where('business_id', $this->businessId)->count());
    }

    public function test_ingest_volume_by_mount_and_empty(): void
    {
        Livewire::test(IngestVolumeByView::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('No ingest runs yet');
    }

    public function test_ingest_volume_by_totals(): void
    {
        $source = app(IngestConnectAction::class)->connect($this->businessId, 'hubspot', 'HubSpot CRM');
        app(IngestUploadAction::class)->upload($this->businessId, $source->id, [['a' => 1], ['b' => 2], ['c' => 3]], 'a1');
        app(IngestUploadAction::class)->upload($this->businessId, $source->id, [['a' => 4], ['b' => 5]], 'a2');

        Livewire::test(IngestVolumeByView::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('HubSpot CRM')
            ->assertSee('5 records')
            ->assertSee('2 runs')
            ->assertSee('5 records across 2 runs');
    }

    public function test_ingest_volume_by_pause(): void
    {
        $source = app(IngestConnectAction::class)->connect($this->businessId, 'hubspot', 'HubSpot CRM');
        Livewire::test(IngestVolumeByView::class, ['businessId' => $this->businessId])
            ->call('pause', $source->id)
            ->assertSee('Paused');

        $source->refresh();
        $this->assertFalse($source->is_active);
    }

    public function test_ingest_volume_by_error_state(): void
    {
        Livewire::test(IngestVolumeByView::class, ['businessId' => $this->businessId])
            ->call('pause', 999999)
            ->assertSee('Action failed');
    }

    public function test_ingest_volume_by_sample_state(): void
    {
        Livewire::test(IngestVolumeByView::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('HubSpot CRM')
            ->assertSee('Meta lead forms')
            ->call('pause', 9501);

        $this->assertSame(0, IngestSource::where('business_id', $this->businessId)->count());
    }

    public function test_ingest_volume_by_get_shows_seeded_source(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        app(IngestConnectAction::class)->connect($biz->id, 'hubspot', 'Seeded Volume Source');

        $this->get(route('x-156.ingest-volume-by'))
            ->assertOk()
            ->assertSee('Seeded Volume Source');
    }

    public function test_rejectedrows_list_mount_and_empty(): void
    {
        Livewire::test(RejectedrowsListView::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('No rejected rows');
    }

    public function test_rejectedrows_list_shows_reasons(): void
    {
        $source = app(IngestConnectAction::class)->connect($this->businessId, 'hubspot', 'HubSpot CRM');
        app(IngestUploadAction::class)->upload($this->businessId, $source->id, [['a' => 1]], null);
        app(IngestWebhookAction::class)->handle($this->businessId, $source->id, '{}', 'sha256=bad');

        Livewire::test(RejectedrowsListView::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('Every contact write carries an attestation_id or is refused (P-069)')
            ->assertSee('HMAC signature verification failed')
            ->assertSee('Unverified')
            ->assertSee('Signature verified')
            ->assertSee('HubSpot CRM');
    }

    public function test_rejectedrows_list_pause_source(): void
    {
        $source = app(IngestConnectAction::class)->connect($this->businessId, 'hubspot', 'HubSpot CRM');
        app(IngestUploadAction::class)->upload($this->businessId, $source->id, [['a' => 1]], null);

        Livewire::test(RejectedrowsListView::class, ['businessId' => $this->businessId])
            ->call('pause', $source->id)
            ->assertSee('Paused');

        $source->refresh();
        $this->assertFalse($source->is_active);
    }

    public function test_rejectedrows_list_error_state(): void
    {
        Livewire::test(RejectedrowsListView::class, ['businessId' => $this->businessId])
            ->call('pause', 999999)
            ->assertSee('Action failed');
    }

    public function test_rejectedrows_list_sample_state(): void
    {
        Livewire::test(RejectedrowsListView::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('Every contact write carries an attestation_id or is refused (P-069)')
            ->call('pause', 9501);

        $this->assertSame(0, IngestRejection::where('business_id', $this->businessId)->count());
    }

    public function test_rejectedrows_list_get_shows_seeded_reason(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        $source = app(IngestConnectAction::class)->connect($biz->id, 'hubspot', 'Seeded Volume Source');
        app(IngestUploadAction::class)->upload($biz->id, $source->id, [['a' => 1]], null);

        $this->get(route('x-156.rejectedrows-list'))
            ->assertOk()
            ->assertSee('attestation_id');
    }
}
