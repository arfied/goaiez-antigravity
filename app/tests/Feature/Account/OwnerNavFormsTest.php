<?php

use App\Support\Account\OwnerNav;
use App\Support\Account\OwnerNavItem;

test('Forms sits in the More menu under the website section, not in the catalogue', function () {
    $forms = collect(OwnerNav::more())->first(fn (OwnerNavItem $i) => $i->route === 'x-155.forms');

    expect($forms)->not->toBeNull()
        ->and($forms->section)->toBe('Reviews & your website')
        ->and(collect(OwnerNav::catalog())->first(fn (OwnerNavItem $i) => $i->route === 'x-155.forms'))->toBeNull();
});
