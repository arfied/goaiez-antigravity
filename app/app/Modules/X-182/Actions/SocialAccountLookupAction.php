<?php

declare(strict_types=1);

namespace App\Modules\X182\Actions;

use App\Modules\X182\Models\SocialAccount;

final class SocialAccountLookupAction
{
    public function forAccount(string $accountRef): ?SocialAccount
    {
        return SocialAccount::where('account_ref', $accountRef)->first();
    }
}
