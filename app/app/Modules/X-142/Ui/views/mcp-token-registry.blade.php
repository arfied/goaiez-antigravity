<div>
    @if($success)
        <div class="mb-4 p-4 border rounded bg-surface text-ink">{{ $success }}</div>
    @endif
    @if($error)
        <div class="mb-4 p-4 border rounded bg-surface text-ink">{{ $error }}</div>
    @endif

    <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
        <h2 class="font-bold text-ink">Issue Token</h2>
        <form wire:submit.prevent="issueToken" class="flex flex-col gap-2">
            <input type="text" wire:model.defer="tokenName" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Token Name">
            <input type="text" wire:model.defer="roleScope" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Role Scope">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Issue</button>
        </form>
    </div>

    @if($tokens->where('is_revoked', false)->count() > 0)
    <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
        <h2 class="font-bold text-ink">Revoke Token</h2>
        <form wire:submit.prevent="revokeToken" class="flex flex-col gap-2">
            <select wire:model.defer="revokeTokenId" class="border rounded p-2 text-ink flex-1 bg-surface">
                <option value="0">Select a token...</option>
                @foreach($tokens->where('is_revoked', false) as $t)
                    <option value="{{ $t->id }}">{{ $t->token_name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-surface text-ink border rounded p-2">Revoke</button>
        </form>
    </div>
    @endif

    @if($tokens->isEmpty())
        <x-ui.empty-state heading="No tokens yet." icon="○">
            Tokens give external systems access to your account.
        </x-ui.empty-state>
    @else
        <div class="mb-4">
            <h1 class="font-display text-lg font-bold mb-4">MCP Token Registry</h1>
            @foreach($tokens as $token)
                <div class="p-4 border border-rule rounded-[--radius-card] mb-2" data-revoked="{{ $token->is_revoked ? 'yes' : 'no' }}">
                    <h2 class="font-bold">{{ $token->token_name }}</h2>
                    <p class="text-sm">{{ $token->role_scope }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
