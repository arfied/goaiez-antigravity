<?php

declare(strict_types=1);

namespace Tests\Modules\CMail\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Ui\DnsCard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DnsCardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-mail.dns-card'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No sending domain yet.');

        Tenancy::setUser($owner->id);
        MailDomain::create([
            'business_id' => $biz->id,
            'domain_name' => 'distinctive-4605.example',
            'dkim_status' => 'pending',
            'spf_status' => 'pending',
            'dmarc_status' => 'pending',
        ]);
        Tenancy::forget();

        $this->get(route('c-mail.dns-card'))
            ->assertSee('distinctive-4605.example')
            ->assertDontSee('No sending domain yet.');

        Livewire::test(DnsCard::class)->assertOk();
    }
}
