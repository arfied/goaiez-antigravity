<?php

declare(strict_types=1);

namespace Tests\Modules\CMail\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\WarmupCalendar;
use App\Modules\CMail\Ui\SequenceView;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class SequenceViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-mail.sequence-view'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertSee('Warm-up sequence')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(SequenceView::class)->assertOk();
    }

    public function test_no_domain_is_an_honest_empty_state(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-mail.sequence-view'))
            ->assertOk()
            ->assertSee('Add a sending domain first');
    }

    public function test_starting_warmup_writes_a_calendar_with_a_jittered_schedule(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $domain = MailDomain::create([
            'business_id' => $biz->id,
            'domain_name' => 'distinctive-7731.example',
        ]);

        Livewire::actingAs($owner)->test(SequenceView::class)
            ->set('mailDomainId', $domain->id)
            ->call('startWarmup')
            ->assertHasNoErrors();

        $cal = WarmupCalendar::where('mail_domain_id', $domain->id)->firstOrFail();

        $this->assertCount(5, $cal->schedule);
        foreach ($cal->schedule as $day => $data) {
            $this->assertLessThan($data['max'], $data['min']);
            $this->assertGreaterThanOrEqual($data['min'], $data['quantity']);
            $this->assertLessThanOrEqual($data['max'], $data['quantity']);
        }

        $this->assertSame(2, $cal->current_day);

        $this->get(route('c-mail.warmup-calendars-per'))
            ->assertOk()
            ->assertSee('Domain #'.$domain->id);
    }

    public function test_another_tenants_domain_cannot_be_warmed_from_here(): void
    {
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        $domainB = MailDomain::create([
            'business_id' => $bizB->id,
            'domain_name' => 'distinctive-7731.example',
        ]);

        $this->actingAs($ownerA);
        $this->expectException(ModelNotFoundException::class);
        Tenancy::set($bizA->id);

        Livewire::actingAs($ownerA)->test(SequenceView::class)
            ->set('mailDomainId', $domainB->id)
            ->call('startWarmup');
    }
}
