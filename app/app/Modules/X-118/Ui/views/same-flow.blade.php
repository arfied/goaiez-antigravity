<div>
    <x-surface.sample-state module="⭐⭐⭐ **IT IS THE WIZARD, and it asks for two things: a business name and a phone number. The AI FINDS GBP" screen="same_flow" />
    <div class="same-flow p-4">
        <h3 class="text-lg font-bold">Standard Flow</h3>
        <ul>
            @foreach($runs as $run)
                <li wire:key="run-{{ $run->id }}">{{ $run->business_name }}</li>
            @endforeach
        </ul>
    </div>
</div>
