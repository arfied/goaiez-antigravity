<div>
    <x-surface.sample-state module="⭐ **The platform's one OWNED channel — the only one with no carrier between us and the device.** Delivers to **Web Push** *(every desktop and mobile browser)*, **iOS via APNs** and **Android via FCM**, from one internal contract: a module emits an event, X-207 decides nothing about whether it may send and everything about how it arrives. ⛔⛔ **It is a CHANNEL under `X-204 ConsentService` — same permit, same three lanes, same STOP, same twenty refusal codes as SMS. Free does not mean ungated.** ⛔ **Payloads carry NO PII: a notification is a title, a deep link and a secure id; the client fetches the sensitive content after waking, authenticated.** ⛔ **It never polls — it subscribes to the bus.** Owns the **device register** *(tokens per user per device, rotated, invalidated on logout at the next request, not at expiry)*. **Class comes from `X-193`; the quiet-hours window applies to MARKETING class only, exactly as it does on every other channel.**" screen="promptcopy_editor" />
    <div class="prompt-editor-view p-4">
        <h3 class="text-lg font-bold">Push Prompt Copy Editor</h3>
        @if($prompts->isEmpty())
            <p class="text-gray-500">No prompt templates configured.</p>
        @else
            <ul>
                @foreach($prompts as $p)
                    <li>#{{ $p->id }}: {{ $p->prompt_title }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
