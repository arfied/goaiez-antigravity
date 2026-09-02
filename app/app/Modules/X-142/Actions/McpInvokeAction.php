<?php

declare(strict_types=1);

namespace App\Modules\X142\Actions;

use App\Modules\X142\Models\McpToken;
use App\Modules\X142\Events\McpInvoked;

class McpInvokeAction
{
    public function invoke(int $businessId, string $tokenHash, string $actionName, bool $isExplicitlyConfirmed = false): array
    {
        $token = McpToken::where('business_id', $businessId)
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$token || $token->is_revoked) {
            return ['success' => false, 'refusal_reason' => 'Invalid or revoked token'];
        }

        if ($token->role_scope === 'staff' && $actionName === 'billing.change_payment_method') {
            return ['success' => false, 'refusal_reason' => 'Action restricted to business owner'];
        }

        if ($actionName === 'customer.delete' && !$isExplicitlyConfirmed) {
            return [
                'success' => false, 
                'refusal_reason' => 'Terminal action requires explicit confirmation', 
                'confirmation_required' => true
            ];
        }

        McpInvoked::dispatch($businessId, $actionName);

        return ['success' => true];
    }
}
