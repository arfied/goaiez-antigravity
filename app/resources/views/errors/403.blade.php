<x-marketing.layout>
    <div class="flex min-h-[60vh] flex-col items-center justify-center px-4 py-16 text-center">
        <h1 class="font-display text-6xl font-bold text-ink">403</h1>
        <p class="mt-4 text-xl text-ink-2">You don't have permission to access this page.</p>
        <div class="mt-8 flex gap-4">
            <x-ui.button :href="route('home')" variant="secondary">Go to the home page</x-ui.button>
            <x-ui.button :href="route('login')">Sign in</x-ui.button>
        </div>
    </div>
</x-marketing.layout>
