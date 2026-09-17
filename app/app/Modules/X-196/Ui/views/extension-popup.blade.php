<div>
    <div class="extension-popup-view p-4">
        <h2 class="text-lg font-bold mb-4 text-ink">Browser extension</h2>
        @if($sessions->isEmpty())
            <x-ui.empty-state heading="No extension sessions yet." icon="🧩">Connect the browser extension and every page it scans and every prospect it injects is listed here.</x-ui.empty-state>
        @else
            <x-ui.row-list>
                @foreach($sessions as $session)
                    <x-ui.row>
                        <div class="flex flex-col gap-1 w-full">
                            <div class="flex justify-between w-full">
                                <span class="font-bold">Session #{{ $session->id }}</span>
                                <x-ui.status-pill :state="$session->is_active ? 'ok' : 'unknown'" :label="$session->is_active ? 'Active' : 'Inactive'" />
                            </div>
                            <div class="text-sm text-ink-2">
                                @if($session->injections->isEmpty())
                                    No injections yet.
                                @else
                                    <ul class="list-disc pl-4">
                                        @foreach($session->injections as $injection)
                                            <li>{{ $injection->source_url }} ({{ $injection->attestation_id }})</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </x-ui.row>
                @endforeach
            </x-ui.row-list>
        @endif
    </div>
</div>
