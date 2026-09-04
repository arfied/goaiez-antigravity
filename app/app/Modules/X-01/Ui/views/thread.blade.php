<x-surface.sample-state module="**one Conversation per Person, every channel** — SMS, email, voice transcripts, web chat, WhatsApp in ONE timeline; contact de-duplication; record and screen pop; CRM logging and injection; field-level history; tagging and auto-categorisation; custom fields; bulk actions and exports *(moved here from X-121)*; conversational global search; lead transparency and hidden scoring; lead caps; ghost-risk; the preference centre. ⛔ **P18: `app/Livewire/Account/Inbox.php` exists but its scope was never verified — if it is a mail reader wearing the name, this is a build, not a wiring job, and it is replaced.**" screen="thread" />
<div>
    <div class="inbox-thread p-4">
        <h3 class="text-lg font-bold">Omnichannel Conversation Thread</h3>
        @if($conversations->isEmpty())
            <p class="text-gray-500">No conversations recorded.</p>
        @else
            <ul>
                @foreach($conversations as $c)
                    <li>Conversation #{{ $c->id }} [{{ $c->channel }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
