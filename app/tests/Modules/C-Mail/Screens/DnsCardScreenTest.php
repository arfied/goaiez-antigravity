<?php

declare(strict_types=1);

namespace Tests\Modules\CMail\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CMail\Domain\TxtRecords;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Ui\DnsCard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class FakeTxtRecords implements TxtRecords
{
    public array $responses = [];

    public function txt(string $name): ?array
    {
        return $this->responses[$name] ?? null;
    }
}

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

    public function test_control_adds_domain(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        $fake = new FakeTxtRecords;
        $fake->responses = [
            'new-domain.com' => ['v=spf1 include:_spf.google.com ~all'],
            'google._domainkey.new-domain.com' => ['v=DKIM1; k=rsa; p=test'],
            '_dmarc.new-domain.com' => ['v=DMARC1; p=none;'],
        ];
        $this->app->instance(TxtRecords::class, $fake);

        Livewire::test(DnsCard::class)
            ->set('domainName', 'new-domain.com')
            ->call('submit')
            ->assertSet('success', 'Checked DNS for new-domain.com — SPF verified, DKIM verified, DMARC verified.')
            ->assertSet('domainName', '');

        $this->assertDatabaseHas((new MailDomain)->getTable(), [
            'business_id' => $biz->id,
            'domain_name' => 'new-domain.com',
            'dkim_status' => 'verified',
            'spf_status' => 'verified',
            'dmarc_status' => 'verified',
        ]);

        $this->get(route('c-mail.dns-card'))
            ->assertSee('new-domain.com')
            ->assertDontSee('No sending domain yet.');

        Livewire::test(DnsCard::class)
            ->set('domainName', '')
            ->call('submit')
            ->assertSet('error', 'Domain name is required.');

        Tenancy::forget();
    }

    public function test_lookup_all_published(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $domainName = 'all-published-4705.example';
        $fake = new FakeTxtRecords;
        $fake->responses = [
            $domainName => ['v=spf1 include:_spf.google.com ~all'],
            "google._domainkey.{$domainName}" => ['v=DKIM1; k=rsa; p=test'],
            "_dmarc.{$domainName}" => ['v=DMARC1; p=none;'],
        ];
        $this->app->instance(TxtRecords::class, $fake);

        Livewire::test(DnsCard::class)
            ->set('domainName', $domainName)
            ->call('submit');

        $this->assertDatabaseHas((new MailDomain)->getTable(), [
            'business_id' => $biz->id,
            'domain_name' => $domainName,
            'dkim_status' => 'verified',
            'spf_status' => 'verified',
            'dmarc_status' => 'verified',
        ]);

        $this->get(route('c-mail.dns-card'))
            ->assertSee('Every record for this domain is verified.');

        Tenancy::forget();
    }

    public function test_lookup_none_published(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $domainName = 'none-published-4705.example';
        $fake = new FakeTxtRecords;
        $fake->responses = [
            $domainName => [],
            "google._domainkey.{$domainName}" => [],
            "_dmarc.{$domainName}" => [],
        ];
        $this->app->instance(TxtRecords::class, $fake);

        Livewire::test(DnsCard::class)
            ->set('domainName', $domainName)
            ->call('submit');

        $this->assertDatabaseHas((new MailDomain)->getTable(), [
            'business_id' => $biz->id,
            'domain_name' => $domainName,
            'dkim_status' => 'missing',
            'spf_status' => 'missing',
            'dmarc_status' => 'missing',
        ]);

        $this->get(route('c-mail.dns-card'))
            ->assertDontSee('Every record for this domain is verified.')
            ->assertSee('_dmarc.')
            ->assertSee('v=spf1 include:');

        Tenancy::forget();
    }

    public function test_lookup_no_lookup_possible(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $domainName = 'no-lookup-4705.example';
        $fake = new FakeTxtRecords;
        // $fake->responses is empty, so txt() returns null for everything

        $this->app->instance(TxtRecords::class, $fake);

        Livewire::test(DnsCard::class)
            ->set('domainName', $domainName)
            ->call('submit');

        $this->assertDatabaseHas((new MailDomain)->getTable(), [
            'business_id' => $biz->id,
            'domain_name' => $domainName,
            'dkim_status' => 'pending',
            'spf_status' => 'pending',
            'dmarc_status' => 'pending',
        ]);

        $this->assertDatabaseMissing((new MailDomain)->getTable(), [
            'business_id' => $biz->id,
            'domain_name' => $domainName,
            'dkim_status' => 'verified',
        ]);

        Tenancy::forget();
    }

    public function test_the_copy_button_has_a_clipboard_action(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

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
            ->assertSee('navigator.clipboard.writeText', false)
            ->assertDontSee('copy-affordance');
    }
}
