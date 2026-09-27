<div>
    <h2>Teach a fact</h2>
    <p class="text-ink-2">What you record here is what the assistant answers with — prices, hours, services — exactly as you write it.</p>
    
    <div>
        <label>What it is called</label>
        <input wire:model="key">
        @error('key') <span class="text-ink-2">{{ $message }}</span> @enderror
    </div>
    
    <div>
        <label>The fact</label>
        <textarea wire:model="value"></textarea>
        @error('value') <span class="text-ink-2">{{ $message }}</span> @enderror
    </div>
    
    @if ($status = session('status'))
        <div class="text-ink-2">{{ $status }}</div>
    @endif
    
    <button type="button" wire:click="teach">Teach</button>
    
    <a href="{{ route('x-119.fact-freshness-per') }}" class="text-ink-2">See everything recorded →</a>
</div>
