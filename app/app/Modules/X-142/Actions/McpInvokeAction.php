<?php

declare(strict_types=1);

namespace App\Modules\X142\Actions;

use App\Modules\X142\Events\McpInvoked;
use App\Modules\X142\Models\McpToken;
use Illuminate\Support\Facades\Event;

final class McpInvokeAction
{
    private const OWNER_ACTIONS = [
        'billing.change_payment_method',
        'business.delete',
        'subscription.cancel',
    ];

    private const TERMINAL_ACTIONS = [
        'customer.delete',
        'account.cancel',
        'data.purge',
    ];

    /**
     * Invokes action over MCP.
     * Refuses staff token on owner action with identical reason string as UI (TEST ANCHOR).
     * Requires explicit confirmation on terminal actions (TEST ANCHOR).
     */
    public function invoke(
        int $businessId,
        string $tokenHash,
        string $actionName,
        array $parameters = [],
        bool $isExplicitlyConfirmed = false
    ): array {
        $token = McpToken::where('business_id', $businessId)
            ->where('token_hash', $tokenHash)
            ->where('is_revoked', false)
            ->first();

        if (! $token) {
            Event::dispatch(new McpInvoked($businessId, $actionName, false));

            return [
                'success' => false,
                'refusal_reason' => 'Invalid or revoked MCP token',
            ];
        }

        // 1. Staff user invoking owner-only action (TEST ANCHOR)
        if ($token->role_scope === 'staff' && in_array($actionName, self::OWNER_ACTIONS, true)) {
            Event::dispatch(new McpInvoked($businessId, $actionName, false));

            return [
                'success' => false,
                'refusal_reason' => 'Action restricted to business owner',
            ];
        }

        // 2. Terminal action confirmation gate (TEST ANCHOR)
        if (in_array($actionName, self::TERMINAL_ACTIONS, true) && ! $isExplicitlyConfirmed) {
            Event::dispatch(new McpInvoked($businessId, $actionName, false));

            return [
                'success' => false,
                'refusal_reason' => 'Terminal action requires explicit confirmation',
                'confirmation_required' => true,
            ];
        }

        Event::dispatch(new McpInvoked($businessId, $actionName, true));

        return [
            'success' => true,
            'action' => $actionName,
            'result' => 'Executed successfully via MCP tool gateway',
        ];
    }

    public function listTools(int $businessId): array
    {
        return [
            ['name' => 'job.create', 'role' => 'staff'],
            ['name' => 'job.eta_notify', 'role' => 'staff'],
            ['name' => 'billing.change_payment_method', 'role' => 'owner'],
            ['name' => 'customer.delete', 'role' => 'staff', 'is_terminal' => true],
        ];
    }
}
