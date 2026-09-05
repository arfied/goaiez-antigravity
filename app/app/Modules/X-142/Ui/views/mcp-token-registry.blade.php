<div>
    <x-surface.sample-state module="full read/write across every registered action *(the category leaders ship read-only MCP; ours is complete because the registry was built for it)*" screen="mcp_token_registry" />
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
