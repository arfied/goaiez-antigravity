<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-ink-2 hover:text-ink hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">AI Voice Receptionist</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Autonomous AI Voice Receptionist <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Conversational AI call handling, 24/7 emergency escalation, and automated appointment bridges.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button wire:click="saveSettings" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                <span wire:loading.remove>Save Voice Settings</span>
                <span wire:loading>Saving...</span>
            </button>
        </div>
    </div>

    @if ($saveNotification)
        <div class="mb-6 p-4 rounded-md bg-ok-bg border border-ok text-ok text-sm flex items-center justify-between">
            <span>{{ $saveNotification }}</span>
            <button wire:click="$set('saveNotification', null)" class="text-ok hover:underline text-xs">Dismiss</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Voice Settings Card -->
        <div class="lg:col-span-2 bg-card shadow-card rounded-card p-6 border border-rule">
            <h2 class="text-lg font-bold text-ink mb-4">Voice Receptionist Configuration</h2>

            <div class="space-y-5">
                <div class="flex items-center justify-between pb-4 border-b border-rule">
                    <div>
                        <div class="text-sm font-semibold text-ink">Enable 24/7 AI Voice Answering</div>
                        <div class="text-xs text-ink-2">Pick up incoming calls after 2 rings or when lines are busy.</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input aria-label="Enable AI Voice Receptionist" type="checkbox" wire:model.live="voiceEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-paper peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-rule after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-ink-2 mb-1">Voice Personality / Accent Model</label>
                    <select aria-label="Voice Personality" wire:model.live="voicePersona" class="w-full text-xs p-2.5 rounded-md border border-rule bg-paper text-ink">
                        <option value="warm_professional">Warm & Professional (Natural human cadence, clear enunciation)</option>
                        <option value="direct_concise">Direct & Concise (Fast intake, high efficiency)</option>
                        <option value="friendly_casual">Friendly & Casual (Welcoming local business tone)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-ink-2 mb-1">Custom Voice Greeting Script</label>
                    <textarea aria-label="Custom Voice Greeting Script" wire:model="greetingText" rows="3" class="w-full text-xs p-3 rounded-md border border-rule bg-paper text-ink font-sans"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-ink-2 mb-1">Emergency Escalation Forwarding Phone Number</label>
                    <input aria-label="Emergency Call Forwarding Number" type="text" wire:model="emergencyForwardNumber" class="w-full text-xs p-2.5 rounded-md border border-rule bg-paper text-ink font-mono">
                    <p class="text-[11px] text-ink-2 mt-1">If the caller reports a critical emergency, the AI will bridge the call directly to this number.</p>
                </div>
            </div>
        </div>

        <!-- Live Status Card -->
        <div class="bg-card shadow-card rounded-card p-6 border border-rule flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-ink mb-2">Receptionist Status</h3>
                <div class="flex items-center gap-2 mb-4">
                    <span class="h-2.5 w-2.5 rounded-full bg-ok animate-ping"></span>
                    <span class="text-xs font-semibold text-ok">Active & Ready for Inbound Calls</span>
                </div>
                <div class="space-y-3 text-xs text-ink-2">
                    <div class="flex justify-between py-1.5 border-b border-rule">
                        <span>Average Call Duration:</span>
                        <strong class="text-ink">52 seconds</strong>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-rule">
                        <span>Autonomous Resolution:</span>
                        <strong class="text-ok">86%</strong>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-rule">
                        <span>Appointments Booked:</span>
                        <strong class="text-ink">14 this week</strong>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-rule">
                <button wire:click="saveSettings" class="w-full py-2 bg-paper border border-rule text-ink rounded text-xs font-semibold hover:bg-card">
                    Test Voice Greeting via Audio Preview 🔊
                </button>
            </div>
        </div>
    </div>

    <!-- Recent Voice Call Log Table -->
    <div class="bg-card shadow-card rounded-card p-6 border border-rule">
        <h2 class="text-lg font-bold text-ink mb-4">Recent Inbound Voice Calls</h2>

        <div class="overflow-x-auto" tabindex="0" aria-label="Recent voice calls log">
            <table class="min-w-full divide-y divide-rule text-left text-xs">
                <thead class="bg-paper font-semibold text-ink-3">
                    <tr>
                        <th class="py-3 px-4 whitespace-nowrap">Time</th>
                        <th class="py-3 px-4 whitespace-nowrap">Caller ID</th>
                        <th class="py-3 px-4 whitespace-nowrap">Duration</th>
                        <th class="py-3 px-4 whitespace-nowrap">Detected Intent</th>
                        <th class="py-3 px-4 whitespace-nowrap">AI Resolution Action</th>
                        <th class="py-3 px-4 text-right whitespace-nowrap">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-rule">
                    @foreach ($recentCalls as $call)
                        <tr>
                            <td class="py-3 px-4 whitespace-nowrap text-ink-2">{{ $call['time'] }}</td>
                            <td class="py-3 px-4 whitespace-nowrap font-mono font-medium text-ink">{{ $call['caller'] }}</td>
                            <td class="py-3 px-4 whitespace-nowrap text-ink-2">{{ $call['duration'] }}</td>
                            <td class="py-3 px-4 whitespace-nowrap font-medium text-ink">{{ $call['intent'] }}</td>
                            <td class="py-3 px-4 whitespace-nowrap text-ink">{{ $call['action'] }}</td>
                            <td class="py-3 px-4 whitespace-nowrap text-right">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $call['status'] === 'escalated' ? 'bg-attention-bg text-attention' : 'bg-ok-bg text-ok' }}">
                                    {{ ucfirst($call['status']) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
