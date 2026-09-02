<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-400 dark:text-gray-400">AI Voice Receptionist</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Autonomous AI Voice Receptionist <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-400">Conversational AI call handling, 24/7 emergency escalation, and automated appointment bridges.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button wire:click="saveSettings" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                <span wire:loading.remove>Save Voice Settings</span>
                <span wire:loading>Saving...</span>
            </button>
        </div>
    </div>

    @if ($saveNotification)
        <div class="mb-6 p-4 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm flex items-center justify-between">
            <span>{{ $saveNotification }}</span>
            <button wire:click="$set('saveNotification', null)" class="text-emerald-400 hover:underline text-xs">Dismiss</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Voice Settings Card -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Voice Receptionist Configuration</h2>

            <div class="space-y-5">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">Enable 24/7 AI Voice Answering</div>
                        <div class="text-xs text-gray-400">Pick up incoming calls after 2 rings or when lines are busy.</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input aria-label="Enable AI Voice Receptionist" type="checkbox" wire:model.live="voiceEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Voice Personality / Accent Model</label>
                    <select aria-label="Voice Personality" wire:model.live="voicePersona" class="w-full text-xs p-2.5 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                        <option value="warm_professional">Warm & Professional (Natural human cadence, clear enunciation)</option>
                        <option value="direct_concise">Direct & Concise (Fast intake, high efficiency)</option>
                        <option value="friendly_casual">Friendly & Casual (Welcoming local business tone)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Custom Voice Greeting Script</label>
                    <textarea aria-label="Custom Voice Greeting Script" wire:model="greetingText" rows="3" class="w-full text-xs p-3 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white font-sans"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Emergency Escalation Forwarding Phone Number</label>
                    <input aria-label="Emergency Call Forwarding Number" type="text" wire:model="emergencyForwardNumber" class="w-full text-xs p-2.5 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white font-mono">
                    <p class="text-[11px] text-gray-400 mt-1">If the caller reports a critical emergency, the AI will bridge the call directly to this number.</p>
                </div>
            </div>
        </div>

        <!-- Live Status Card -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2">Receptionist Status</h3>
                <div class="flex items-center gap-2 mb-4">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-ping"></span>
                    <span class="text-xs font-semibold text-emerald-400">Active & Ready for Inbound Calls</span>
                </div>
                <div class="space-y-3 text-xs text-gray-600 dark:text-gray-300">
                    <div class="flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-700">
                        <span>Average Call Duration:</span>
                        <strong class="text-gray-900 dark:text-white">52 seconds</strong>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-700">
                        <span>Autonomous Resolution:</span>
                        <strong class="text-emerald-400">86%</strong>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-700">
                        <span>Appointments Booked:</span>
                        <strong class="text-indigo-400">14 this week</strong>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                <button wire:click="saveSettings" class="w-full py-2 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-white rounded text-xs font-semibold hover:bg-gray-200">
                    Test Voice Greeting via Audio Preview 🔊
                </button>
            </div>
        </div>
    </div>

    <!-- Recent Voice Call Log Table -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Recent Inbound Voice Calls</h2>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-left text-xs">
                <thead class="bg-gray-50 dark:bg-gray-900 font-semibold text-gray-600 dark:text-gray-300">
                    <tr>
                        <th class="py-3 px-4">Time</th>
                        <th class="py-3 px-4">Caller ID</th>
                        <th class="py-3 px-4">Duration</th>
                        <th class="py-3 px-4">Detected Intent</th>
                        <th class="py-3 px-4">AI Resolution Action</th>
                        <th class="py-3 px-4 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($recentCalls as $call)
                        <tr>
                            <td class="py-3 px-4 text-gray-400">{{ $call['time'] }}</td>
                            <td class="py-3 px-4 font-mono font-medium text-gray-900 dark:text-white">{{ $call['caller'] }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-300">{{ $call['duration'] }}</td>
                            <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">{{ $call['intent'] }}</td>
                            <td class="py-3 px-4 text-indigo-400 dark:text-indigo-400">{{ $call['action'] }}</td>
                            <td class="py-3 px-4 text-right">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $call['status'] === 'escalated' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
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
