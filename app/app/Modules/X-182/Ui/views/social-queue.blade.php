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
                        @if($post->publish_status === 'published')
                            — published on {{ ucfirst($post->account?->platform) }}
                            @if($post->platform_post_url)
                                <a href="{{ $post->platform_post_url }}">View it</a>
                            @endif
                        @elseif($post->publish_status === 'publishing')
                            — Zernio is still publishing it
                        @elseif($post->publish_status === 'unconfirmed')
                            — sent, Zernio has not confirmed it yet
                        @elseif($post->publish_status === 'failed')
                            — not published: {{ $post->last_error }}
                        @elseif($post->publish_status === 'duplicate')
                            — Zernio already has this post
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
