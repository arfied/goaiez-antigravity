<div>
    <div class="prompt-editor-view p-4">
        <h3 class="text-lg font-bold">Push Prompt Copy Editor</h3>
        @if($prompts->isEmpty())
            <p class="text-gray-500">No prompt templates configured.</p>
        @else
            <ul>
                @foreach($prompts as $p)
                    <li>#{{ $p->id }}: {{ $p->prompt_title }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
