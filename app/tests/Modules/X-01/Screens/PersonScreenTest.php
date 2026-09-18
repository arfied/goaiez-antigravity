<?php

declare(strict_types=1);

namespace Tests\Modules\X01\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X01\Ui\Person;
use App\Modules\X121\Models\Person as PersonModel;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PersonScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-01.person'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Person not found');

        Tenancy::setUser($owner->id);
        $person = PersonModel::create([
            'business_id' => $biz->id,
            'first_name' => 'Distinctive',
            'last_name' => 'Person 4632',
        ]);
        Tenancy::forget();

        $this->get(route('x-01.person', ['person' => $person->id]))
            ->assertOk()
            ->assertSee('Distinctive')
            ->assertSee('Person 4632')
            ->assertDontSee('Person not found');

        Livewire::test(Person::class)->assertOk();
    }
}
