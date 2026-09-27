<div>
    <div class="social-queue-view p-4">
        <h2 class="text-lg font-bold text-ink">Social queue</h2>
        
        @if($accounts->isEmpty())
            <p>Connect a Facebook Page or an Instagram account first, on <a href="{{ route('x-182.connected-accounts') }}">Connected accounts</a>.</p>
        @else
            <form wire:submit="publish">
                <select wire:model="accountId">
                    <option value="">Choose an account</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}">{{ ucfirst($a->platform) }} · {{ $a->account_handle }}</option>
                    @endforeach
                </select>
                <textarea wire:model="content"></textarea>
                <label>
                    Image or video link (https, optional — Instagram needs one)
                    <input type="url" wire:model="imageUrl">
                </label>
                <button type="submit">Publish now</button>
            </form>
        @endif

        @if($posts->isEmpty())
            <x-ui.empty-state heading="No posts queued">No post has been published from here yet.</x-ui.empty-state>
        @else
            <ul>
                @foreach($posts as $post)
                    <li>
                        {{ $post->content_text }} {{ $post->comments->count() }} comments
                        <ul class="text-sm text-ink-2">
                            @foreach($post->comments->sortBy('id')->take(20) as $c)
                                <li>
                                    {{ $c->author_name }}: {{ $c->comment_text }}
                                    @if($c->replied_at)
                                        — You replied: {{ $c->reply_text }}
                                    @elseif($c->platform_comment_id)
                                        <input type="text" wire:model="commentReply.{{ $c->id }}" maxlength="1000">
                                        <x-ui.button wire:click="replyToComment({{ $c->id }})" size="sm">Reply</x-ui.button>
                                    @endif
                                    @if($c->hidden_at)
                                        (hidden — only the commenter and your page see it)
                                        <x-ui.button wire:click="unhideComment({{ $c->id }})" size="sm">Unhide</x-ui.button>
                                    @elseif($c->platform_comment_id)
                                        <x-ui.button wire:click="hideComment({{ $c->id }})" size="sm">Hide</x-ui.button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if($post->publish_status === 'published')
                            — published on {{ ucfirst($post->account?->platform) }}
                            @if($post->platform_post_url)
                                <a href="{{ $post->platform_post_url }}">View it</a>
                            @endif
                        @elseif(in_array($post->publish_status, ['publishing', 'unconfirmed']))
                            — {{ $post->publish_status === 'publishing' ? 'Zernio is still publishing it' : 'sent, Zernio has not confirmed it yet' }}
                            <x-ui.button wire:click="checkAgain({{ $post->id }})" size="sm" variant="secondary">Check again</x-ui.button>
                        @elseif($post->publish_status === 'failed')
                            — not published: {{ $post->last_error }}
                        @elseif($post->publish_status === 'duplicate')
                            — Zernio already has this post
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        
        <h3 class="text-lg font-bold text-ink mt-8">Messages</h3>
        @if($dmThreads->isEmpty())
            <p>No Facebook or Instagram messages yet. They appear here when someone messages a connected Page or Instagram account.</p>
        @else
            <ul>
                @foreach($dmThreads as $t)
                    <li>
                        {{ $t->contact_label ?? 'Someone' }} · {{ ucfirst($t->channel) }}
                        <ul class="text-sm text-ink-2">
                            @foreach($dmTails[$t->id] ?? [] as $m)
                                <li>{{ $m->direction->value === 'inbound' ? 'They wrote' : 'You wrote' }}: {{ $m->body }}</li>
                            @endforeach
                        </ul>
                        <input type="text" wire:model="dmReply.{{ $t->id }}" maxlength="1000">
                        <x-ui.button wire:click="replyToDm({{ $t->id }})" size="sm">Reply</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
