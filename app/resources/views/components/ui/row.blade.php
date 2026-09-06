@props(['action' => null, 'href' => null])
<li class="group">
    @if($href)
        <a href="{{ $href }}" {{ $attributes->merge(['class' => 'w-full text-left px-4 py-3 sm:py-4 flex items-center justify-between hover:bg-paper transition min-h-[44px]']) }}>
            {{ $slot }}
        </a>
    @elseif($action)
        <button type="button" wire:click="{{ $action }}" {{ $attributes->merge(['class' => 'w-full text-left px-4 py-3 sm:py-4 flex items-center justify-between hover:bg-paper transition min-h-[44px]']) }}>
            {{ $slot }}
        </button>
    @else
        <div {{ $attributes->merge(['class' => 'w-full text-left px-4 py-3 sm:py-4 flex items-center justify-between min-h-[44px]']) }}>
            {{ $slot }}
        </div>
    @endif
</li>
