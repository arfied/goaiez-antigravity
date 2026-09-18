<div>
    <h2 class="text-lg font-bold text-ink mb-4">Connect your AI</h2>
    @if($connections->isEmpty())
        <x-ui.empty-state heading="No connections yet." icon="○">
            Connect an AI to get started.
        </x-ui.empty-state>
    @else
        <div class="mb-4">
            @foreach($connections as $token)
                <div class="p-4 border border-rule rounded-[--radius-card] mb-2" data-connected="yes">
                    <p class="font-bold text-ink">{{ $token->token_name }}</p>
                    <p class="text-sm">{{ $token->role_scope }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
