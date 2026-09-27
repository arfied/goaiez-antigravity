<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Enums\UserRole;
use App\Modules\X142\Actions\McpTokenAction;
use App\Modules\X142\Models\McpToken;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class McpTokenRegistry extends Component
{
    public string $tokenName = '';

    public string $roleScope = 'reader';

    public int $revokeTokenId = 0;

    #[Locked]
    public int $businessId = 0;

    public string $error = '';

    public string $success = '';

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function issueToken(McpTokenAction $action): void
    {
        $this->reset(['success', 'error']);

        if (trim($this->tokenName) === '') {
            $this->error = 'A token name is required.';

            return;
        }

        if (trim($this->roleScope) === '') {
            $this->error = 'A role scope is required.';

            return;
        }

        $token = $action->issue(
            Tenancy::idOrFail(),
            $this->tokenName,
            $this->roleScope,
            ['read']
        );

        $this->success = 'Registry entry for token '.$token->token_name.' was recorded (granting read). The token value is not shown and cannot be used yet.';
        $this->reset(['tokenName', 'roleScope']);
    }

    public function revokeToken(McpTokenAction $action): void
    {
        $this->reset(['success', 'error']);

        if ($this->revokeTokenId === 0) {
            $this->error = 'Please select a token to revoke.';

            return;
        }

        $token = $action->revoke(
            Tenancy::idOrFail(),
            $this->revokeTokenId
        );

        $this->success = 'Token '.$token->token_name.' has been revoked and can no longer be used.';
        $this->reset('revokeTokenId');
    }

    public function render()
    {
        $tokens = McpToken::where('business_id', $this->businessId)->orderBy('id', 'desc')->get();

        return view('x-142::mcp-token-registry', [
            'tokens' => $tokens,
        ]);
    }
}
