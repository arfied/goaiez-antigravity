<?php

declare(strict_types=1);

namespace Tests\Modules\X01\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X01\Ui\CustomersList;
use App\Modules\X121\Models\Person;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CustomersListScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-01.customers-list'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No customers yet');

        Tenancy::setUser($owner->id);
        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Distinctive',
            'last_name' => 'Customer 4629',
            'email' => 'distinctive4629@example.test',
            'phone' => '+15555554629',
        ]);
        LeadScore::create([
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'lead_rating' => 91,
            'grade' => 'A',
        ]);
        Tenancy::forget();

        $this->get(route('x-01.customers-list'))
            ->assertOk()
            ->assertSee('Distinctive')
            ->assertSee('Customer 4629')
            ->assertSee('distinctive4629@example.test')
            ->assertDontSee('No customers yet');

        Livewire::test(CustomersList::class)->assertOk();
    }
}
