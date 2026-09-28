<?php

declare(strict_types=1);

namespace Tests\Modules\X142\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X142\Models\WebhookSubscription;
use App\Modules\X142\Ui\WebhooksView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class WebhooksViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-142.webhooks'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No webhooks yet.');

        Tenancy::setUser($owner->id);
        WebhookSubscription::create([
            'business_id' => $biz->id,
            'target_url' => 'https://distinctive-4628.example/hooks',
            'event_filter' => 'Distinctive event 4628',
            'secret' => 'sec_distinctive4628',
            'is_active' => true,
        ]);
        Tenancy::forget();

        $this->get(route('x-142.webhooks'))
            ->assertOk()
            ->assertSee('https://distinctive-4628.example/hooks')
            ->assertSee('Distinctive event 4628')
            ->assertDontSee('sec_distinctive4628')
            ->assertDontSee('No webhooks yet.');

        Livewire::test(WebhooksView::class)->assertOk();
    }

    public function test_control_adds_webhook(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(WebhooksView::class, ['businessId' => $biz->id])
            ->set('url', 'https://example.com/webhook')
            ->set('events', 'contact.created')
            ->call('submit')
            ->assertSet('success', 'Webhook added. We sign every delivery with the secret below in the X-Goaiez-Signature header (sha256 HMAC of the body). Copy it now.')
            ->assertSet('url', '')
            ->assertSet('events', '');

        $this->assertDatabaseHas((new WebhookSubscription)->getTable(), [
            'business_id' => $biz->id,
            'target_url' => 'https://example.com/webhook',
            'event_filter' => 'contact.created',
            'is_active' => true,
        ]);

        $this->get(route('x-142.webhooks'))
            ->assertSee('https://example.com/webhook')
            ->assertSee('contact.created')
            ->assertDontSee('No webhooks yet.');

        Livewire::test(WebhooksView::class, ['businessId' => $biz->id])
            ->set('url', '')
            ->call('submit')
            ->assertSet('error', 'URL is required.');
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-142.webhooks.admin'))->assertOk();

        Livewire::test(WebhooksView::class)->assertOk();
    }
}
