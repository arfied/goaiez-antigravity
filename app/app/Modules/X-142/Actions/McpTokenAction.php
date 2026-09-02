<?php

declare(strict_types=1);

namespace App\Modules\X142\Actions;

use App\Modules\X142\Models\McpToken;
use App\Modules\X142\Events\TokenIssued;
use App\Modules\X142\Events\TokenRevoked;
use Illuminate\Support\Str;

class McpTokenAction
{
    public function issue(int $businessId, string $tokenName, string $roleScope, array $permissions): McpToken
    {
        $token = McpToken::create([
            'business_id' => $businessId,
            'name' => $tokenName,
            'role_scope' => $roleScope,
            'abilities' => $permissions,
            'token_hash' => hash('sha256', Str::random(40)),
            'is_revoked' => false,
        ]);

        TokenIssued::dispatch($businessId, $token->id);

        return $token;
    }

    public function revoke(int $businessId, int $tokenId): McpToken
    {
        $token = McpToken::where('business_id', $businessId)->findOrFail($tokenId);
        $token->update(['is_revoked' => true]);

        TokenRevoked::dispatch($businessId, $token->id);

        return $token;
    }
}
