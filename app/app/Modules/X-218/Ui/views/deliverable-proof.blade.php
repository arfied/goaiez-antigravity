<div>
    <div class="deliverable-proof-view p-4">
        <h3 class="text-lg font-bold">Verified Deliverable Proof & Hash Verification</h3>
        
        @if($deliverables->isEmpty())
            <x-ui.empty-state heading="No deliverables submitted.">A deliverable is verified here and its proof link stored.</x-ui.empty-state>
        @else
            <ul class="mt-4 space-y-4">
                @foreach($deliverables as $deliverable)
                    <li class="p-4 border rounded">
                        <p><strong>Live URL:</strong> {{ $deliverable->live_url }}</p>
                        <p><strong>Status:</strong> {{ $deliverable->http_status }}</p>
                        <p><strong>Artifact Hash:</strong> {{ $deliverable->artifact_hash }}</p>
                        <p><strong>Verified At:</strong> {{ $deliverable->verified_at }}</p>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8 border p-4 rounded bg-white">
            <h4 class="font-bold mb-4">Submit Deliverable</h4>
            
            @if($success)
                <div class="p-2 border mb-4 rounded bg-white">{{ $success }}</div>
            @endif
            
            @if($error)
                <div class="p-2 border mb-4 rounded bg-white">{{ $error }}</div>
            @endif

            <form wire:submit="submitProof" class="flex flex-col gap-4">
                <div>
                    <label class="block mb-1">Deal ID</label>
                    <input type="text" wire:model="dealId" class="border rounded p-2 w-full">
                </div>
                <div>
                    <label class="block mb-1">Live URL</label>
                    <input type="text" wire:model="liveUrl" class="border rounded p-2 w-full">
                </div>
                <div>
                    <label class="block mb-1">HTTP Status</label>
                    <input type="number" wire:model="httpStatus" class="border rounded p-2 w-full">
                </div>
                <div>
                    <label class="block mb-1">Artifact Hash</label>
                    <input type="text" wire:model="artifactHash" class="border rounded p-2 w-full">
                </div>
                <button type="submit" class="border rounded p-2 self-start mt-2">Submit Proof</button>
            </form>
        </div>
    </div>
</div>
