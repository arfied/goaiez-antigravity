<?php

declare(strict_types=1);

namespace App\Modules\X142\Actions;

use App\Modules\X142\Events\TokenIssued;
use App\Modules\X142\Events\TokenRevoked;
use App\Modules\X142\Models\McpToken;
use Illuminate\Support\Facades\Event;

final class McpTokenAction
{
    /**
     * Issues tenant-scoped token inheriting role permissions (G4-02, G4-18).
     */
    public function issue(
        int $businessId,
        string $tokenName,
        string $roleScope = 'staff',
        array $permissions = []
    ): McpToken {
        $tokenHash = 'mcp_live_'.bin2hex(random_bytes(16));

        $token = McpToken::create([
            'business_id' => $businessId,
            'token_hash' => $tokenHash,
            'token_name' => $tokenName,
            'role_scope' => $roleScope,
            'permissions' => $permissions,
            'is_revoked' => false,
        ]);

        Event::dispatch(new TokenIssued($businessId, $token->id, $tokenName, $roleScope));

        return $token;
    }

    /**
     * Revokes token (G4-02).
     */
    public function revoke(int $businessId, int $tokenId): McpToken
    {
        $token = McpToken::where('business_id', $businessId)->findOrFail($tokenId);
        $token->update(['is_revoked' => true]);

        Event::dispatch(new TokenRevoked($businessId, $token->id));

        return $token;
    }
}
