<div>
    <div class="flow-canvas-view p-4">
        <h2 class="text-xl font-bold text-ink">Every automation you have set up</h2>
        @if($flows->isEmpty())
            <x-ui.empty-state icon="○" heading="No automations yet">
                When you set up an automation, it appears here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($flows as $f)
                    <li class="text-ink">{{ $f->name }} · {{ ucfirst(str_replace('_', ' ', $f->status)) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
