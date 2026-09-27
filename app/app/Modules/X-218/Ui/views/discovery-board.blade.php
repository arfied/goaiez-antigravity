<div>
    <div class="discovery-board-view p-4">
        <h2 class="text-lg font-bold">Influencer & Creator Discovery Board</h2>
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <x-ui.toast kind="success" :message="$success" />
            <x-ui.toast kind="error" :message="$error" />
            <form wire:submit.prevent="discover" class="flex flex-col gap-2">
                <input type="text" wire:model="handle" placeholder="Handle" class="border rounded p-2 text-ink flex-1 bg-surface">
                <input type="text" wire:model="platform" placeholder="Platform" class="border rounded p-2 text-ink flex-1 bg-surface">
                <input type="number" wire:model="audienceSize" placeholder="Audience Size" class="border rounded p-2 text-ink flex-1 bg-surface">
                <input type="number" step="0.01" wire:model="engagementRate" placeholder="Engagement Rate" class="border rounded p-2 text-ink flex-1 bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        <ul>
            @forelse($influencers as $influencer)
                <li>{{ $influencer->handle }}</li>
            @empty
                <x-ui.empty-state heading="No influencers discovered yet.">Discover influencers to populate this board.</x-ui.empty-state>
            @endforelse
        </ul>
    </div>
</div>
