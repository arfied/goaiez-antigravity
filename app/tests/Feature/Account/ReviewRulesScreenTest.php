<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Livewire\Account\ReviewRules;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class ReviewRulesScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected User $owner;

    protected $biz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);
    }

    public function test_get_review_rules_renders_layout(): void
    {
        $this->get(route('setup.review-rules'))
            ->assertOk()
            ->assertSee('Who should we ask for reviews?');
    }

    public function test_review_rules_renders_empty_state_and_distinctive_value(): void
    {
        Livewire::test(ReviewRules::class)
            ->assertSee('Who we ask for reviews');
    }

    public function test_review_rules_shows_distinctive_rule_value_for_everyone(): void
    {
        Livewire::test(ReviewRules::class)
            ->assertSee('Ask only customers who rated us 5 stars');
    }
}
