<div>
    <x-surface.sample-state module="⭐⭐⭐ **IT IS THE WIZARD, and it asks for two things: a business name and a phone number. The AI FINDS GBP" screen="test_call" />
    <div class="test-call p-4">
        <h3 class="text-lg font-bold">Direct Test Call</h3>
        <p class="text-gray-500">Test call placed directly to your new live line.</p>
        <ul>
            @foreach($runs as $run)
                <li wire:key="run-{{ $run->id }}">{{ $run->business_name }}</li>
            @endforeach
        </ul>
    </div>
</div>
