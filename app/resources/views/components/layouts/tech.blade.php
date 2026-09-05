<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <title>{{ $title ?? 'Tech Mobile' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    @livewireStyles
</head>
<body class="min-h-full flex flex-col bg-slate-950 text-slate-200 pb-16">
    <header class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950 px-4 py-3 text-center font-bold">
        {{ $title ?? 'Tech Console' }}
    </header>

    <main class="flex-1 w-full px-4 py-4">
        {{ $slot }}
    </main>

    <nav class="fixed bottom-0 left-0 right-0 border-t border-slate-800 bg-slate-950 flex justify-around p-2 z-50 overflow-x-auto">
        @foreach (config('surfaces.generated.tech', []) as $group => $items)
            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}" class="flex flex-col items-center p-2 text-[10px] {{ request()->routeIs($item['route']) ? 'text-indigo-400' : 'text-slate-400' }}">
                    <span class="truncate max-w-[60px]">{{ $item['label'] }}</span>
                </a>
            @endforeach
        @endforeach
    </nav>
    @livewireScripts
</body>
</html>