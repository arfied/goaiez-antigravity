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

    /**
     * @return array<int>
     */
    public function syncableFacebookAccountIds(): array
    {
        return SocialAccount::where('platform', 'facebook')
            ->where('status', 'connected')
            ->whereNotNull('account_ref')
            ->whereNotNull('location_id')
            ->pluck('id')
            ->toArray();
    }
}
