<?php

declare(strict_types=1);

use App\Models\PlatformSetting;
use App\Services\Legal\SmsTermsAlignment;

it('sms terms alignment honours max name length', function () {
    PlatformSetting::write('legal.sms_terms.max_name_length', 10, 'test');

    // We can just test the accessor
    expect(SmsTermsAlignment::maxNameLength())->toBe(10);
});
