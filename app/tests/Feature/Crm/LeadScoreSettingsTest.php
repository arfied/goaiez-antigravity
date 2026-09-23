<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\X01\Domain\UnifiedInboxManager;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->location = $this->biz->locations()->first();
    $this->location->forceFill([
        'website_url' => 'https://example.test',
        'website_confirmed_at' => now(),
    ])->save();
    Mail::fake();
    Notification::fake();
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('a rating of 65 reads as the tier the lowered threshold gives it', function () {
    PlatformSetting::write('crm.lead_score.tier_hot', 60, 'test');

    $manager = app(UnifiedInboxManager::class);

    $personId = app(PersonLookupAction::class)->create($this->biz->id, [
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $score = $manager->scoreLead($this->biz->id, $personId, 65);
    expect($score->grade)->toBe('A');
});
