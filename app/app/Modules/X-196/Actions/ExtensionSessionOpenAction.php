<?php

declare(strict_types=1);

namespace App\Modules\X196\Actions;

use App\Modules\X196\Models\ExtensionSession;
use Illuminate\Support\Str;

final class ExtensionSessionOpenAction
{
    /**
     * Opens an extension session for a tenant. The token is the extension's
     * credential for the scan endpoint; it is returned once and never listed.
     */
    public function open(int $businessId): ExtensionSession
    {
        return ExtensionSession::create([
            'business_id' => $businessId,
            'session_token' => Str::random(48),
            'is_authenticated_scrape' => false,
            'is_active' => true,
            'is_aborted' => false,
            'actions_count' => 0,
        ]);
    }
}
