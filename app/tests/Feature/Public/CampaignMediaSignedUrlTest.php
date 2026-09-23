<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\UserRole;
use App\Models\CampaignRecipient;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CampaignMediaSignedUrlTest extends TestCase
{
    public function test_unsigned_url_returns_403(): void
    {
        $biz = $this->provisionTenant();

        $response = $this->get("/m/{$biz->id}/999");

        $response->assertForbidden();
    }

    public function test_signed_url_with_file_present_returns_200(): void
    {
        Storage::fake('local');
        $path = 'campaigns/media/test.jpg';
        Storage::disk('local')->put($path, 'image content');

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);

        $row = CampaignRecipient::factory()->create([
            'media_path' => $path,
        ]);

        Tenancy::forget();

        $url = URL::signedRoute('campaign.media', ['business' => $biz->id, 'recipient' => $row->id]);

        $this->get($url)
            ->assertOk();
    }

    public function test_signed_url_with_wrong_tenant_returns_404(): void
    {
        Storage::fake('local');
        $path = 'campaigns/media/test.jpg';
        Storage::disk('local')->put($path, 'image content');

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id, 'name' => 'Tenant A', 'currency' => 'USD']);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id, 'name' => 'Tenant B', 'currency' => 'USD']);

        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);

        $rowB = CampaignRecipient::factory()->create([
            'media_path' => $path,
        ]);

        Tenancy::forget();

        // Signed URL naming tenant A's id with tenant B's recipient
        $url = URL::signedRoute('campaign.media', ['business' => $bizA->id, 'recipient' => $rowB->id]);

        $this->get($url)
            ->assertNotFound();
    }
}
