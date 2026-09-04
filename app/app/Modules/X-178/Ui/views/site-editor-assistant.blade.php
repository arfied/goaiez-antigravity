<section class="site-editor-assistant-panel p-4">
    <x-surface.sample-state module="design by conversation" screen="site_editor_assistant" />
<h3 class="text-lg font-bold">Site Editor Assistant</h3>
    @if($changes->isEmpty())
        <p class="text-gray-500">No recent design changes.</p>
    @else
        <ul>
            @foreach($changes as $c)
                <li>#{{ $c->id }}: [{{ $c->change_type }}] -> {{ $c->block_ref }} (contrast: {{ $c->contrast_ratio }}:1)</li>
            @endforeach
        </ul>
    @endif
</section>
