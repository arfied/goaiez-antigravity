<div>
    
    <div class="max-w-xl mx-auto px-4 py-8" wire:loading.class="opacity-50">
        @if($errorMessage)
            <x-ui.error-panel heading="We couldn't record that" class="mb-6">
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
                        @if($jobState === 'missing')
                            <p class="text-ink-2 mb-6">We couldn’t find this job. Please contact us directly.</p>
                        @elseif($jobState === 'cancelled')
                            <p class="text-ink-2 mb-6">This job was cancelled.</p>
                        @elseif($jobState === 'completed')
                            <p class="text-ink-2 mb-6">This job is complete.</p>
                        @else
                            <p class="text-ink-2 mb-6">Your job is on file. Check back here for updates.</p>
                        @endif
                    @endif
                @elseif(in_array($link->resource_type, ['estimate', 'invoice']))
                    @if($document === null)
                        <p class="text-ink-2 mb-6">We couldn’t find this document. Please contact us directly.</p>
                    @else
                        <p class="text-ink-2 mb-1">{{ $document['kind'] }} {{ $document['number'] }}</p>
                        <p class="font-medium text-ink mb-4">{{ $document['state'] }}</p>
                        @if(!empty($document['lines']))
                            <table class="w-full text-sm mb-4">
                                @foreach($document['lines'] as $line)
                                    <tr><td class="py-1 text-ink">{{ $line['label'] }}@if($line['quantity'] > 1) × {{ $line['quantity'] }}@endif</td><td class="py-1 text-right text-ink">${{ number_format($line['subtotal_cents'] / 100, 2) }}</td></tr>
                                @endforeach
                            </table>
                        @endif
                        <p class="font-medium text-ink mb-1">Total: ${{ number_format($document['total_cents'] / 100, 2) }}</p>
                        @if($document['secondary'])<p class="text-ink-2 mb-6">{{ $document['secondary'] }}</p>@endif
                    @endif
                @endif
                
                @if($membershipStatus)
                    <div class="mb-6 border-t border-rule pt-4">
                        <p class="font-medium text-ink">Membership: {{ $membershipStatus }}</p>
                    </div>
                @endif

                {{-- Approve / Pay / Book a follow-up are not rendered: the actions behind them
                     record a row nothing reads and dispatch an event nothing listens to (wave 816).
                     The component methods stay for the day they are wired. --}}
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
