<div>
    
    <div class="max-w-xl mx-auto px-4 py-8" wire:loading.class="opacity-50">
        @if($errorMessage)
            <x-ui.error-panel class="mb-6">
                {{ $errorMessage }}
            </x-ui.error-panel>
        @endif

        @if($refreshedToken)
            <x-ui.empty-state 
                heading="Link Expired"
                icon="!">
                This portal link had expired and has been refreshed. You can continue below.
            </x-ui.empty-state>
        @endif

        @if($link && $link->is_active)
            <div class="bg-paper rounded-xl border border-rule p-6 mb-8">
                @if($link->is_sample)
                    <div class="mb-4">
                        <x-ui.status-pill state="attention" label="Sample" />
                    </div>
                @endif
                
                @if($link->resource_type === 'job')
                    <h1 class="text-2xl font-bold text-ink mb-2">{{ $resourceTitle ?? 'Work Order' }}</h1>
                    
                    @if($isJobEnRoute)
                        <div class="bg-surface border border-accent rounded p-4 mb-6">
                            @if($jobEtaMinutes !== null)
                                <p class="font-medium text-accent">Your technician is en route, {{ $jobEtaMinutes }} minutes out</p>
                            @else
                                <p class="font-medium text-accent">Your technician is en route.</p>
                            @endif
                        </div>
                    @else
                        <p class="text-ink-2 mb-6">Your job is booked and confirmed.</p>
                    @endif
                @elseif(in_array($link->resource_type, ['estimate', 'invoice']))
                    @if($isDocumentPrepared)
                        <p class="text-ink-2 italic mb-6">This document is being prepared.</p>
                    @endif
                @endif
                
                @if($membershipStatus)
                    <div class="mb-6 border-t border-rule pt-4">
                        <p class="font-medium text-ink">Membership: {{ $membershipStatus }}</p>
                    </div>
                @endif

                <div class="flex flex-col gap-3 mt-6 border-t border-rule pt-6">
                    <button wire:click="approve" class="h-12 bg-accent text-white rounded font-medium hover:bg-accent-hover">
                        Approve
                    </button>
                    <button wire:click="requestPay" class="h-12 bg-paper border border-rule text-ink rounded font-medium hover:bg-surface">
                        Pay
                    </button>
                    <button wire:click="requestFollowUp" class="h-12 bg-paper border border-rule text-ink rounded font-medium hover:bg-surface">
                        Book a follow-up
                    </button>
                </div>
            </div>
        @else
            <x-ui.empty-state 
                heading="Invalid Link"
                icon="!">
                This portal link is invalid or no longer active.
            </x-ui.empty-state>
        @endif
    </div>
</div>
