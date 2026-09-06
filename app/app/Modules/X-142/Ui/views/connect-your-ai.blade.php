<div>
    <x-surface.sample-state module="full read/write across every registered action *(the category leaders ship read-only MCP; ours is complete because the registry was built for it)*" screen="connect_your_ai" />
    @if($connections->isEmpty())
        <x-ui.empty-state heading="No connections yet." icon="○">
            Connect an AI to get started.
        </x-ui.empty-state>
    @else
        <div class="mb-4">
            <h1 class="font-display text-lg font-bold mb-4">Connect Your AI</h1>
            @foreach($connections as $token)
                <div class="p-4 border border-rule rounded-[--radius-card] mb-2" data-connected="yes">
                    <h2 class="font-bold">{{ $token->token_name }}</h2>
                    <p class="text-sm">{{ $token->role_scope }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
