<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Crm\CrmNotes;
use App\Services\Crm\CustomerEditor;
use App\Support\Tenancy;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    $this->customer = Customer::factory()->create(['business_id' => $this->biz->id]);
});

it('a written notes limit of 5 refuses a 6 char note', function () {
    PlatformSetting::write('crm.notes.max_length', 5, 'test');

    app(CrmNotes::class)->add($this->customer, '123456');
})->throws(InvalidArgumentException::class);

it('a written max tags of 2 refuses 3 tags', function () {
    PlatformSetting::write('crm.customer.max_tags', 2, 'test');

    app(CustomerEditor::class)->retag($this->customer, ['one', 'two', 'three']);
})->throws(InvalidArgumentException::class);
