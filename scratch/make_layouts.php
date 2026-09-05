<?php
$appLayoutPath = __DIR__.'/../app/resources/views/components/layouts/app.blade.php';
$agencyLayoutPath = __DIR__.'/../app/resources/views/components/layouts/agency.blade.php';
$techLayoutPath = __DIR__.'/../app/resources/views/components/layouts/tech.blade.php';

// App layout (tenant)
$appLayout = file_get_contents($appLayoutPath);
$navReplacement = <<<'BLADE'
                <nav class="hidden md:flex items-center gap-1 text-xs font-medium">
                    @foreach (config('surfaces.generated.tenant', []) as $group => $items)
                        <div class="relative group/dropdown">
                            <button class="px-3 py-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-900 transition flex items-center gap-1">
                                {{ $group }}
                                <svg class="w-3 h-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            <div class="absolute left-0 top-full mt-1 hidden group-hover/dropdown:block w-48 bg-slate-900 border border-slate-800 rounded-lg shadow-xl overflow-hidden py-1 z-50">
                                @foreach ($items as $item)
                                    <a href="{{ route($item['route']) }}" class="block px-4 py-2 text-sm text-slate-400 hover:text-white hover:bg-slate-800 {{ request()->routeIs($item['route']) ? 'bg-slate-800 text-white font-medium' : '' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>
BLADE;
$appLayout = preg_replace('/<nav class="hidden md:flex items-center gap-1 text-xs font-medium">.*?<\/nav>/s', $navReplacement, $appLayout);
file_put_contents($appLayoutPath, $appLayout);

// Agency layout
$agencyLayout = str_replace("config('surfaces.generated.tenant', [])", "config('surfaces.generated.agency', [])", $appLayout);
file_put_contents($agencyLayoutPath, $agencyLayout);

// Tech layout
$techLayout = <<<'BLADE'
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
BLADE;
file_put_contents($techLayoutPath, $techLayout);

// Admin layout
$adminNavPath = __DIR__.'/../app/resources/views/components/admin/nav.blade.php';
$adminNav = file_get_contents($adminNavPath);
$generatedBlock = <<<'BLADE'
    <!-- Generated Operator Routes -->
    <div class="pt-4 border-t border-rule mt-4">
        <div class="px-3 text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-2">Modules (Generated)</div>
        @foreach (config('surfaces.generated.operator', []) as $group => $items)
            <div class="mt-4">
                <h2 class="px-3 text-xs font-bold uppercase tracking-wider text-ink-3">{{ $group }}</h2>
                <ul class="mt-2 space-y-1">
                    @foreach ($items as $item)
                        @php $current = request()->routeIs($item['route']); @endphp
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                @if ($current) aria-current="page" @endif
                                class="flex min-h-10 items-center justify-between rounded-[--radius-control] px-3 text-sm transition {{ $current ? 'bg-card font-semibold text-ink border-l-2 border-indigo-600 shadow-sm' : 'text-ink-2 hover:text-ink hover:bg-card/50 font-medium' }}"
                            >
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <!-- Current Operator Card & Sign Out -->
BLADE;
$adminNav = str_replace('<!-- Current Operator Card & Sign Out -->', $generatedBlock, $adminNav);
file_put_contents($adminNavPath, $adminNav);

echo "Layouts updated.\n";
