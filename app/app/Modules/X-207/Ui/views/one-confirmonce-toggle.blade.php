<div>
    <div class="confirm-toggle-view p-4">
        <h2 class="text-lg font-bold text-ink">Push prompts</h2>
        @if($prompts->isEmpty())
            <p class="text-ink-2">No push prompts configured.</p>
        @else
            <ul>
                @foreach($prompts as $p)
                    <li>{{ $p->prompt_title }} [{{ $p->is_active ? 'active' : 'inactive' }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
