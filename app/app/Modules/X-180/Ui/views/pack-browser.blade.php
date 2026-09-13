<div>
    <div class="pack-browser-view p-4">
        <h2 class="text-lg font-bold">Content packs</h2>
        
        @forelse($packs as $pack)
            <div class="mt-4 border rounded p-4">
                <h3 class="font-bold">{{ $pack->pack_name }}</h3>
                <p class="text-sm text-ink-2">{{ $pack->industry }} - {{ $pack->assets_count }} assets</p>
                
                @if($pack->assets_manifest && count($pack->assets_manifest) > 0)
                    <ul class="list-disc ml-5 mt-2">
                        @foreach($pack->assets_manifest as $asset)
                            <li>{{ $asset['title'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="mt-4">No starter pack has been seeded for you yet</p>
        @endforelse
    </div>
</div>
