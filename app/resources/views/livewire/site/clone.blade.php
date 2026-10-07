<div>
    @if ($notice)
        <div class="mb-4 p-2 rounded bg-alert-bg text-alert text-sm">
            {{ $notice }}
        </div>
    @endif

    @if ($sites->isNotEmpty())
        <div class="mb-6" @if ($sites->contains(fn ($s) => $s->publish_status === \App\Models\WebstudioSite::PUBLISHING || $s->creation_status === \App\Models\WebstudioSite::CREATING)) wire:poll.3s @endif>
            <h2 class="text-lg font-bold text-ink mb-4">Your sites</h2>
            <div class="border border-rule rounded bg-canvas overflow-hidden">
                <ul class="divide-y divide-rule text-sm text-ink">
                    @foreach ($sites as $site)
                        <li class="p-3 flex items-center justify-between">
                            <div>
                                <div class="font-bold">{{ $site->title }}</div>
                                @if ($site->creation_status !== \App\Models\WebstudioSite::READY)
                                    <div class="text-xs">{{ $site->creation_message }}</div>
                                @else
                                    <div class="text-xs">{{ $site->publish_message }}</div>
                                @endif
                            </div>
                            <div class="flex items-center gap-4">
                                @if ($site->creation_status !== \App\Models\WebstudioSite::READY)
                                    <div class="px-2 py-1 rounded bg-paper border border-rule text-xs uppercase">{{ $site->creation_status }}</div>
                                    @if ($siteSvc->siteUrl($site) !== null)
                                        <a href="{{ $siteSvc->siteUrl($site) }}" target="_blank" rel="noopener" class="text-attention hover:underline font-bold">View site</a>
                                    @endif
                                @else
                                    <div class="px-2 py-1 rounded bg-paper border border-rule text-xs uppercase">{{ $site->publish_status }}</div>
                                    @if ($siteSvc->siteUrl($site) !== null)
                                        <a href="{{ $siteSvc->siteUrl($site) }}" target="_blank" rel="noopener" class="text-attention hover:underline font-bold">View site</a>
                                    @endif
                                    <button type="button" wire:click="openSiteEditor({{ $site->id }})" class="text-attention hover:underline font-bold">Open editor</button>
                                    @if ($site->publish_status !== \App\Models\WebstudioSite::PUBLISHING)
                                        <button type="button" wire:click="publish({{ $site->id }})" class="px-4 py-2 bg-ink text-canvas rounded font-bold hover:opacity-90">Publish</button>
                                    @endif
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($active)
        <div class="mb-6 p-4 border border-rule rounded bg-canvas">
            <div wire:poll.3s class="flex items-center gap-2 p-2 rounded bg-attention-bg text-attention text-sm" role="status">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>
                {{ $active->message }}
            </div>
            
            <div class="mt-4">
                <div role="progressbar" aria-valuenow="{{ $active->progress }}" aria-valuemin="0" aria-valuemax="100" class="h-2 bg-rule rounded overflow-hidden">
                    <div style="width: {{ $active->progress }}%" class="h-full bg-attention transition-all duration-500"></div>
                </div>
            </div>

            <div class="mt-2 text-sm text-ink">
                {{ ($active->started_at ?? $active->created_at)->diffForHumans() }}
            </div>

            @if ($active->heartbeat_at && $active->heartbeat_at->diffInSeconds(now()) > 30)
                <div class="mt-2 text-sm text-ink">
                    Still running…
                </div>
            @endif

            @if ($active->logs)
                <div class="mt-4 p-2 bg-paper border border-rule rounded text-xs font-mono text-ink">
                    @foreach(array_slice((array) $active->logs, -10) as $logLine)
                        <div>{{ $logLine }}</div>
                    @endforeach
                </div>
            @endif

            <div class="mt-4">
                <button wire:click="cancel" class="px-4 py-2 bg-paper border border-rule rounded text-ink hover:bg-canvas">Cancel</button>
            </div>
        </div>
    @else
        <div class="mb-6">
            <form wire:submit="start" class="space-y-4">
                <div>
                    <label for="url" class="block text-sm font-bold text-ink mb-1">Website address to clone</label>
                    <input type="text" id="url" wire:model="url" class="w-full p-2 border border-rule rounded bg-paper text-ink" placeholder="https://example.com" />
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" wire:model="attested" class="rounded border-rule text-attention focus:ring-attention" />
                        I may use this website's content
                    </label>
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-ink text-canvas rounded font-bold hover:opacity-90">Start cloning</button>
                </div>
            </form>
            
            <div class="mt-4 text-sm">
                <a href="{{ route('site.studio') }}" class="text-attention hover:underline">Start from a template instead</a>
            </div>
        </div>

        @if ($history->isNotEmpty())
            <div class="mt-8">
                <h2 class="text-lg font-bold text-ink mb-4">History</h2>
                <div class="border border-rule rounded bg-canvas overflow-hidden">
                    <ul class="divide-y divide-rule text-sm text-ink">
                        @foreach ($history as $item)
                            <li class="p-3 flex items-center justify-between">
                                <div>
                                    <div class="font-bold">{{ $item->host }}</div>
                                    <div class="text-xs">{{ $item->created_at->diffForHumans() }}</div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="px-2 py-1 rounded bg-paper border border-rule text-xs uppercase">{{ $item->status }}</div>
                                    @if ($jobs->editorUrl($item) !== null)
                                        <button type="button" wire:click="openEditor({{ $item->id }})" class="text-attention hover:underline font-bold">Open editor</button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    @endif
</div>
