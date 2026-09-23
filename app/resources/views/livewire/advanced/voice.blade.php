<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-ink hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Call handling</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Call handling</h1>
            <p class="mt-1 text-sm text-ink-2">Your forwarding mode and missed-call settings.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-8 mb-8">
        <div class="bg-card shadow-card rounded-card p-6 border border-rule flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-ink mb-2">Call handling</h3>
                <div class="space-y-3 text-xs text-ink-2">
                    <div class="flex justify-between py-1.5 border-b border-rule">
                        <span>Current mode:</span>
                        <strong class="text-ink">{{ $mode->label() }}</strong>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-rule">
                        <span>Description:</span>
                        <strong class="text-ink text-right max-w-md">{{ $mode->description() }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-card shadow-card rounded-card p-6 border border-rule">
        <h2 class="text-lg font-bold text-ink mb-4">Recent inbound calls</h2>

        @if($calls->isEmpty())
            <div class="text-sm text-ink-2">No calls yet.</div>
        @else
            <div class="overflow-x-auto" tabindex="0" aria-label="Recent inbound calls">
                <table class="min-w-full divide-y divide-rule text-left text-xs">
                    <thead class="bg-paper font-semibold text-ink-3">
                        <tr>
                            <th class="py-3 px-4 whitespace-nowrap">From</th>
                            <th class="py-3 px-4 whitespace-nowrap">When</th>
                            <th class="py-3 px-4 whitespace-nowrap">Outcome</th>
                            <th class="py-3 px-4 text-right whitespace-nowrap">Duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-rule">
                        @foreach ($calls as $call)
                            <tr>
                                <td class="py-3 px-4 whitespace-nowrap font-mono font-medium text-ink">{{ $call->from_e164 }}</td>
                                <td class="py-3 px-4 whitespace-nowrap text-ink-2">{{ $call->started_at ? $call->started_at->diffForHumans() : '-' }}</td>
                                <td class="py-3 px-4 whitespace-nowrap text-ink">{{ $call->outcome->label() }}</td>
                                <td class="py-3 px-4 whitespace-nowrap text-right text-ink-2">
                                    @if ($call->started_at && $call->ended_at)
                                        {{ $call->started_at->diffInSeconds($call->ended_at) }}s
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
