<?php

declare(strict_types=1);

namespace Tests\Modules\X189\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X189\Models\BrandedMedia;
use App\Modules\X189\Ui\PreviewPerDestination;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PreviewPerDestinationScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-189.preview-per-destination'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No branded media generated.');

        Tenancy::setUser($owner->id);
        BrandedMedia::create([
            'business_id' => $biz->id,
            'source_asset_url' => 'https://example.test/source_distinctive_4570.jpg',
            'license_source' => 'client_upload',
            'output_media_url' => 'https://example.test/branded_distinctive_4570.jpg',
            'overlay_layer' => ['logo' => 'top-left'],
            'destination' => 'instagram',
        ]);
        Tenancy::forget();

        $this->get(route('x-189.preview-per-destination'))
            ->assertOk()
            ->assertSee('[instagram]')
            ->assertSee('branded_distinctive_4570.jpg')
            ->assertDontSee('No branded media generated.');

        Livewire::test(PreviewPerDestination::class)->assertOk();
    }

    public function test_can_brand_asset(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setTenant($biz->id);

        $this->get(route('x-189.preview-per-destination'))
            ->assertOk()
            ->assertSee('Branded media');

        Livewire::test(PreviewPerDestination::class)
            ->set('sourceAssetUrl', 'https://example.com/test_asset.jpg')
            ->set('licenseSource', 'client_upload')
            ->set('destination', 'social_test')
            ->call('brandAsset')
            ->assertSet('error', null)
            ->assertSet('success', 'Recorded branding for asset; no image was actually produced.');

        $this->assertDatabaseHas((new BrandedMedia)->getTable(), [
            'business_id' => $biz->id,
            'source_asset_url' => 'https://example.com/test_asset.jpg',
            'destination' => 'social_test',
        ]);

        $this->get(route('x-189.preview-per-destination'))
            ->assertSee('social_test')
            ->assertSee('test_asset.jpg');
    }

    public function test_refuses_empty_license_source(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setTenant($biz->id);

        Livewire::test(PreviewPerDestination::class)
            ->set('sourceAssetUrl', 'https://example.com/test_asset.jpg')
            ->set('licenseSource', null)
            ->set('destination', 'social_test')
            ->call('brandAsset')
            ->assertSet('error', 'An asset with no license_source is refused at ingest')
            ->assertSet('success', null);

        $this->assertDatabaseMissing((new BrandedMedia)->getTable(), [
            'business_id' => $biz->id,
            'source_asset_url' => 'https://example.com/test_asset.jpg',
        ]);
    }
}
