<?php

declare(strict_types=1);

namespace Tests\Modules\CMail\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\WarmupCalendar;
use App\Modules\CMail\Ui\WarmupCalendarsPer;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class WarmupCalendarsPerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-mail.warmup-calendars-per'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No domains warming up.');

        Tenancy::setUser($owner->id);
        $domain = MailDomain::create([
            'business_id' => $biz->id,
            'domain_name' => 'warm-4606.example',
            'dkim_status' => 'pending',
            'spf_status' => 'pending',
            'dmarc_status' => 'pending',
        ]);
        WarmupCalendar::create([
            'business_id' => $biz->id,
            'mail_domain_id' => $domain->id,
            'current_day' => 3,
            'daily_allowance' => 200,
            'sent_today' => 120,
            'is_warmed' => false,
        ]);
        Tenancy::forget();

        $this->get(route('c-mail.warmup-calendars-per'))
            ->assertSee('Day 3 (120/200)')
            ->assertDontSee('No domains warming up.');

        Livewire::test(WarmupCalendarsPer::class)->assertOk();
    }
}
