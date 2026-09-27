@props([
    'kind' => 'error',   // 'error' | 'success' | 'info'
    'message' => null,
    'timeout' => 8000,
])

{{--
    A message that arrived with the last action, shown as a toast: fixed to the
    bottom-right, dismissed by the reader or after {{ $timeout }} ms. The text is
    in the DOM whether or not it is visible, so a screen reader and a test both
    see it (wave 824). Colour is never the only signal: the kind is also in the
    role and the heading word.
--}}
@if(filled($message))
    <div
        x-data="{ open: true }"
        x-init="setTimeout(() => open = false, {{ (int) $timeout }})"
        x-show="open"
        x-transition
        wire:key="toast-{{ $kind }}-{{ md5((string) $message) }}"
        role="{{ $kind === 'error' ? 'alert' : 'status' }}"
        aria-live="polite"
        {{ $attributes->merge(['class' => 'fixed bottom-4 right-4 z-50 max-w-md rounded-[--radius-panel] border bg-card px-4 py-3 shadow-lg text-ink '.($kind === 'error' ? 'border-red-400' : ($kind === 'success' ? 'border-accent' : 'border-rule-strong'))]) }}
    >
        <div class="flex items-start gap-3">
            <p class="text-sm flex-1"><span class="font-semibold">{{ $kind === 'error' ? 'Could not do that.' : ($kind === 'success' ? 'Done.' : 'Note.') }}</span> {{ $message }}</p>
            <button type="button" class="text-xs underline text-ink-2" x-on:click="open = false">Dismiss</button>
        </div>
    </div>
@endif
