<div>
    <x-surface.sample-state module="**one Conversation per Person, every channel** — SMS, email, voice transcripts, web chat, WhatsApp in ONE timeline; contact de-duplication; record and screen pop; CRM logging and injection; field-level history; tagging and auto-categorisation; custom fields; bulk actions and exports *(moved here from X-121)*; conversational global search; lead transparency and hidden scoring; lead caps; ghost-risk; the preference centre. ⛔ **P18: `app/Livewire/Account/Inbox.php` exists but its scope was never verified — if it is a mail reader wearing the name, this is a build, not a wiring job, and it is replaced.**" screen="customers_list" />
    <div class="customers-list p-4">
        <h3 class="text-lg font-bold">Customers Directory</h3>
        @if($persons->isEmpty())
            <p class="text-gray-500">No customers found.</p>
        @else
            <ul>
                @foreach($persons as $p)
                    <li>{{ $p->first_name }} {{ $p->last_name }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
