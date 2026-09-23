<div class="space-y-6 sm:space-y-8">
    <div class="mb-8">
        <nav class="flex mb-2" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                <li class="text-ink-2">/</li>
                <li class="text-ink-2">Advanced Settings</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-ink sm:text-3xl">AI models by task</h1>
        <p class="mt-1 text-sm text-ink-2">Manage your AI model assignments and account preferences.</p>
    </div>

    <div class="space-y-6">
        <div class="bg-card shadow rounded-lg p-6 border border-rule">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-ink">AI models by task</h2>
                @php $matrix = 'x-219.assignment-matrix'; @endphp
                <a href="{{ route($matrix) }}" class="text-sm text-indigo-400 hover:text-indigo-300 hover:underline">Change assignments</a>
            </div>
            
            <div class="space-y-4">
                @foreach($tasks as $task)
                <div class="flex items-center justify-between p-3 bg-paper rounded-lg">
                    <div>
                        <div class="font-medium text-ink text-sm">{{ $task['label'] }}</div>
                        <div class="text-xs text-ink-2">source: {{ $task['has_tenant_assignment'] ? 'your assignment' : 'platform default' }}</div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-ok-bg text-ok">{{ $task['effective_model'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div class="bg-card shadow rounded-lg p-6 border border-rule">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-ink">Account settings</h2>
                @php $accSettings = 'account.settings'; @endphp
                <a href="{{ route($accSettings) }}" class="text-sm text-indigo-400 hover:text-indigo-300 hover:underline">Manage</a>
            </div>
            <p class="text-sm text-ink-2">Configure owner notifications, timezone, and other account preferences.</p>
        </div>
    </div>
</div>
