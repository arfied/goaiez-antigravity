<?php

declare(strict_types=1);

namespace Tests\Modules\X108\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X108\Actions\AvailabilityRequestAction;
use App\Modules\X108\Actions\WaitlistJoinAction;
use App\Modules\X108\Models\Appointment;
use App\Modules\X108\Models\Waitlist as WaitlistModel;
use App\Modules\X108\Ui\Waitlist;
use App\Modules\X121\Models\Person;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class WaitlistScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-108.waitlist'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('Nobody is waiting')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(WaitlistJoinAction::class)->handle((int) $biz->id, 'Dana Whitfield', '+15125550199', 'Haircut', now()->addDay()->toDateString());
        Tenancy::forget();

        $this->get(route('x-108.waitlist'))
            ->assertOk()
            ->assertSee('Dana Whitfield')
            ->assertSee('Haircut')
            ->assertDontSee('Nobody is waiting');

        Livewire::test(Waitlist::class)->assertOk();
    }

    public function test_the_owner_books_a_waiting_customer_into_a_free_time_on_their_day(): void
    {
        // Fixture taken from test_screen_renders_for_tenant in this file; the person matched by phone as the public site's join
        // route leaves them.
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        $dana = Person::create(['business_id' => $biz->id, 'first_name' => 'Dana', 'last_name' => 'Whitfield', 'phone' => '+15125550199']);
        $day = now()->addDay()->toDateString();
        $entry = app(WaitlistJoinAction::class)->handle((int) $biz->id, 'Dana Whitfield', '+15125550199', 'Haircut 9961', $day);
        $slot = app(AvailabilityRequestAction::class)->handle((int) $biz->id, $day)['offered_slots'][0];

        Livewire::test(Waitlist::class)
            ->assertSee('Book '.$slot['formatted_window'])
            ->call('book', (int) $entry->id, $slot['start_time'])
            ->assertSet('error', null)
            ->assertSee('has not been told — call or text them')
            ->assertSee('Fulfilled')
            ->assertDontSeeHtml('wire:click="book('.$entry->id.',');

        $appointment = Appointment::where('business_id', $biz->id)->firstOrFail();
        $this->assertSame('Haircut 9961', $appointment->service_name);
        $this->assertSame((int) $dana->id, (int) $appointment->customer_id);
        $this->assertSame('booked', $appointment->status);
        $this->assertTrue($appointment->start_time->equalTo($slot['start_time']));
        $this->assertSame('fulfilled', WaitlistModel::findOrFail($entry->id)->status);
        // The booked time is no longer offered to anyone else.
        $this->assertNotContains($slot['start_time'], array_column(app(AvailabilityRequestAction::class)->handle((int) $biz->id, $day)['offered_slots'], 'start_time'));
    }

    public function test_a_time_that_is_no_longer_free_is_refused_and_the_request_keeps_waiting(): void
    {
        // Fixture taken from test_the_owner_books_a_waiting_customer_into_a_free_time_on_their_day.
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        $day = now()->addDay()->toDateString();
        $first = app(WaitlistJoinAction::class)->handle((int) $biz->id, 'Dana Whitfield', '+15125550199', 'Haircut', $day);
        $second = app(WaitlistJoinAction::class)->handle((int) $biz->id, 'Marcus Reyes', '+15125550198', 'Haircut', $day);
        $slot = app(AvailabilityRequestAction::class)->handle((int) $biz->id, $day)['offered_slots'][0];

        Livewire::test(Waitlist::class)->call('book', (int) $first->id, $slot['start_time'])->assertSet('error', null);
        Livewire::test(Waitlist::class)
            ->call('book', (int) $second->id, $slot['start_time'])
            ->assertSet('error', 'That time is no longer free — pick another.');

        $this->assertSame(1, Appointment::where('business_id', $biz->id)->count());
        $this->assertSame('pending', WaitlistModel::findOrFail($second->id)->status);
    }

    public function test_a_day_that_has_passed_offers_no_times(): void
    {
        // Fixture taken from test_screen_renders_for_tenant in this file.
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        $entry = app(WaitlistJoinAction::class)->handle((int) $biz->id, 'Dana Whitfield', '+15125550199', 'Haircut', now()->subDay()->toDateString());

        Livewire::test(Waitlist::class)
            ->assertSee('Their day has passed.')
            ->assertDontSeeHtml('wire:click="book(')
            ->call('book', (int) $entry->id, now()->subDay()->setHour(11)->toIso8601String())
            ->assertSet('error', 'That time is no longer free — pick another.');
        $this->assertSame(0, Appointment::where('business_id', $biz->id)->count());
    }

    public function test_a_booking_cannot_reach_another_business(): void
    {
        // Fixture taken from test_screen_renders_for_tenant in this file; the second business set with Tenancy::set (N261).
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $bizB = $this->provisionTenant();
        Tenancy::set((int) $bizB->id);
        $day = now()->addDay()->toDateString();
        $other = app(WaitlistJoinAction::class)->handle((int) $bizB->id, 'Other Tenant', '+15125550197', 'Haircut', $day);
        $slot = app(AvailabilityRequestAction::class)->handle((int) $bizB->id, $day)['offered_slots'][0];

        $this->actingAs($owner);
        Tenancy::set((int) $bizA->id);
        Livewire::test(Waitlist::class)
            ->call('book', (int) $other->id, $slot['start_time'])
            ->assertSet('error', 'That request is no longer waiting.');

        Tenancy::set((int) $bizB->id);
        $this->assertSame('pending', WaitlistModel::findOrFail($other->id)->status);
        $this->assertSame(0, Appointment::where('business_id', $bizB->id)->count());
    }
}
