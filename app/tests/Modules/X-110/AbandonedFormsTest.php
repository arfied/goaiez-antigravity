<?php

namespace Tests\Modules\X110;

use App\Models\User;
use App\Models\Business;
use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use App\Modules\X110\Ui\AbandonedForms;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class AbandonedFormsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_abandoned_forms_shows_empty_state_and_real_data()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Tenant']);
        $user = $biz->owner;
        Tenancy::set((int) $biz->id);

        $response = $this->actingAs($user)->get('/account/tracking');
        $response->assertOk();

        Livewire::actingAs($user)
            ->test(AbandonedForms::class, ['businessId' => $biz->id])
            ->assertSee('No abandoned forms yet')
            ->assertDontSee('Highest friction');

        $visit = Visit::create(['business_id' => $biz->id, 'visitor_id' => 'vis_123', 'landing_page' => '/', 'created_at' => now()]);
        $session = Session::create(['business_id' => $biz->id, 'visit_id' => $visit->id, 'session_token' => 'tok1', 'started_at' => now(), 'created_at' => now()]);
        $event = PixelEvent::create([
            'business_id' => $biz->id,
            'session_id' => $session->id,
            'event_name' => 'form.abandoned',
            'payload' => [
                'form_id' => 'signup_form',
                'abandoned_field' => 'email'
            ],
            'created_at' => now()
        ]);

        Livewire::actingAs($user)
            ->test(AbandonedForms::class, ['businessId' => $biz->id])
            ->assertDontSee('No abandoned forms yet')
            ->assertSee('Highest friction')
            ->assertSee('email')
            ->assertSee('signup_form')
            ->assertSee('vis_123');
            
        $otherBiz = TestCase::provisionTenant(['name' => 'Other Tenant']);
        $visit2 = Visit::create(['business_id' => $otherBiz->id, 'visitor_id' => 'vis_999', 'landing_page' => '/', 'created_at' => now()]);
        $session2 = Session::create(['business_id' => $otherBiz->id, 'visit_id' => $visit2->id, 'session_token' => 'tok2', 'started_at' => now(), 'created_at' => now()]);
        $event2 = PixelEvent::create([
            'business_id' => $otherBiz->id,
            'session_id' => $session2->id,
            'event_name' => 'form.abandoned',
            'payload' => [
                'form_id' => 'other_form',
                'abandoned_field' => 'password'
            ],
            'created_at' => now()
        ]);
        
        Livewire::actingAs($user)
            ->test(AbandonedForms::class, ['businessId' => $biz->id])
            ->assertDontSee('vis_999');
    }

    public function test_recovery_affordance_does_not_dispatch_send()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Tenant 2']);
        $user = $biz->owner;
        Tenancy::set((int) $biz->id);

        $visit = Visit::create(['business_id' => $biz->id, 'visitor_id' => 'vis_123', 'landing_page' => '/', 'created_at' => now()]);
        $session = Session::create(['business_id' => $biz->id, 'visit_id' => $visit->id, 'session_token' => 'tok1', 'started_at' => now(), 'created_at' => now()]);
        $event = PixelEvent::create([
            'business_id' => $biz->id,
            'session_id' => $session->id,
            'event_name' => 'form.abandoned',
            'payload' => [
                'form_id' => 'signup_form',
                'abandoned_field' => 'email'
            ],
            'created_at' => now()
        ]);

        Event::fake();

        Livewire::actingAs($user)
            ->test(AbandonedForms::class, ['businessId' => $biz->id])
            ->call('recover', $event->id)
            ->assertSee('Send pending');

        Event::assertNotDispatched('send.requested');
    }
}
