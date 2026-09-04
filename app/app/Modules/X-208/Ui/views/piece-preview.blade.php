<div>
    <x-surface.sample-state module="**Physical mail as a channel.** ⭐⭐⭐ **BYOK — the TENANT supplies the Lob key. Zero platform liability, zero platform account, and the tenant's own postage.** ⛔ **Do-Not-Mail is checked AT GENERATION, not at send.**" screen="piece_preview" />
    <div class="piece-preview-view p-4">
        <h3 class="text-lg font-bold">Direct Postcard Previews</h3>
        @if($pieces->isEmpty())
            <p class="text-gray-500">No postcard pieces composed.</p>
        @else
            <ul>
                @foreach($pieces as $p)
                    <li>#{{ $p->id }}: {{ $p->recipient_address }} [{{ $p->postcard_format }}] ({{ $p->status }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
