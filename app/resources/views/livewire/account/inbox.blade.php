{{--
    The Inbox (R21) — text messages with the people who contacted the business.

    ⛔ THE ASSISTANT'S STATE IS NEVER INFERRED HERE. Every label is
    `AgentThreadStatus::ownerLabel()`, written on the column by `AgentThreadStates`
    and by nothing else. A thread this application opens starts `Unhandled` —
    "Waiting for a first reply" — so nothing on this screen can say "your assistant
    is answering" unless the assistant took a turn. That was P3's stated reason for
    deleting its own stub rather than shipping it.

    ⚠️ COLOUR IS NOT THE SIGNAL (`22`). The state is carried by its words alone.

    ⚠️ MESSAGE BODIES ARE CUSTOMER CONTENT. They render on the tenant's own screen
    and nowhere else — never in a toast, never in an audit row, never in a log line.
--}}

@use('App\Enums\AgentThreadStatus')
@use('App\Services\Conversations\InboxReplies')

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Your inbox</h1>
        <p class="mt-1 text-base text-ink-2">
            Text messages from people who contacted you. Reply here and your assistant
            stays quiet on that conversation until you hand it back.
        </p>

        {{--
            T176 P17 ASKS FOR "LINKS FROM PLAN + INBOX" AND THIS IS A SIGNPOST,
            NOT A LINK — THE SAME REFUSAL 4301 RECORDED FOR THE PLAN SCREEN, ON
            THE SAME GROUNDS (4382–4384).

            `Architecture/OwnerNavTest`'s "an owner screen does not hand-write
            its own link to another owner screen" fails the build on a `route()`
            call naming the credit screen anywhere in this file, and both of its
            narrow allowlist entries say in as many words that "a GET route name
            appearing here would be the cross-link this lint exists to refuse".
            Widening it a third time, to keep one sentence, would be overturning
            a written argument rather than answering it.

            ⚠️ AND THE ROUTE NAME IS NOT WRITTEN OUT EVEN IN THIS COMMENT (4384).
            The lint is a regex over the file's raw contents, so it cannot tell a
            link from a mention of one — the first draft of this comment reddened
            the build by explaining why the link is absent. That is the lint
            being blunt rather than wrong: a rule that parsed Blade to work out
            which `route()` calls were "real" would be one somebody could get
            past by commenting a line out. `plan.blade.php`'s 4301 comment makes
            the same accommodation.

            NOTHING IS UNREACHABLE. Your credit is already an `OwnerNav` item
            under More, so what is lost is a hint and never a route. The reason
            the hint belongs on THIS screen is that this is the one place an
            owner spends a text by hand — R21 calls the Inbox the daily surface,
            and a reply that will not send for want of balance is a thing to find
            out here rather than afterwards.
        --}}
        <p class="mt-2 text-base text-ink-2">
            Every reply you send here uses one of your texts. Your credit — under More —
            shows how many you have left.
        </p>
    </div>

    @if ($thread === null)
        <section aria-label="Your conversations">
            @if ($threads->isEmpty())
                {{--
                    "Nothing yet" is a different statement from "no results", and
                    every account is in this state on day one.

                    ⚠️ THE COPY NAMES THE ONE THING THAT HAS TO BE TRUE, because it
                    is the thing most tenants do not have yet: a conversation starts
                    when somebody texts the business's own number. On the shared
                    sending number an inbound text cannot be attributed to a business
                    at all — `InboundThreading` refuses rather than guessing — so this
                    screen would otherwise stay empty with no explanation anywhere.
                --}}
                <x-ui.empty-state icon="◇">
                    No conversations yet. One starts when somebody texts your business number.
                </x-ui.empty-state>
            @else
                <ul class="space-y-3">
                    @foreach ($threads as $item)
                        <li class="rounded-[--radius-panel] border border-rule bg-card p-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <button
                                    type="button"
                                    wire:click="open({{ $item->id }})"
                                    class="min-h-11 text-left text-base font-medium text-ink underline decoration-rule underline-offset-4 hover:decoration-ink"
                                >
                                    {{-- A contact the owner deleted leaves the thread standing
                                         with nobody on it. Saying so beats a blank line. --}}
                                    {{ $contacts[$item->id]['name'] ?? 'This contact was removed' }}
                                    @if ($item->channel === 'whatsapp') <span class="ml-2 text-xs text-ink-2">WhatsApp</span> @endif
                                </button>
                                <p class="text-sm text-ink-2">{{ $item->updated_at?->diffForHumans() }}</p>
                            </div>
                            <p class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-paper text-ink-2 border border-rule" data-thread-state="{{ $item->id }}">
                                {{ $item->agent_status->ownerLabel() }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @else
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5" aria-label="One conversation">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-display text-lg font-semibold text-ink">
                    {{ $contact['name'] ?? 'This contact was removed' }}
                    @if ($thread->channel === 'whatsapp') <span class="ml-2 text-xs text-ink-2">WhatsApp</span> @endif
                </h2>
                <p class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-paper text-ink-2 border border-rule" data-thread-state>{{ $state->status->ownerLabel() }}</p>
            </div>

            {{--
                R20's whole point, and the reason lane L12 exists: a reply to a
                campaign should read in context rather than as an orphan message.
                `campaign_replies` was written on every inbound reply and read
                back by nothing, so this said nothing on any screen while the
                table filled up (4236).

                ⛔ THERE IS NO `@else` HERE AND THERE MAY NEVER BE ONE. A thread
                with no linkage recorded is not the same as a thread that is not
                a campaign reply — the row is also absent when a reply threaded
                after the linkage was written without one, or when attribution
                was ambiguous and the resolver correctly refused to guess. The
                screen therefore makes NO claim rather than a false negative one:
                "Not a reply to a campaign" would be a sentence we cannot
                support.

                ⛔ AND NOTHING HERE READS `$state->campaign->campaignId`. It is
                null for every review invite — the volume product at soft launch
                — so a block gated on the id would be blank in the commonest
                case and populated in the rare one. The origin is what says a
                context exists; the date is what makes it useful.

                ⚠️ NO MESSAGE TEXT AND NO CONTACT DETAIL, which is the same rule
                the DTO itself is built on: it carries ids and an occasion, and
                the occasion is an internal handle that is deliberately not
                rendered.

                ⚠️ THE SECOND CLAUSE IS NARROWING, NOT A SECOND CONDITION.
                `isCampaignReply()` is exactly `campaign !== null`, and it is the
                gate on purpose — it is the method that returned `false` in
                production always, so this is the call site that makes it mean
                something. The explicit null check beside it is what lets a
                reader see that the dereference below is safe.
            --}}
            @if ($state->isCampaignReply() && $state->campaign !== null)
                <p class="mt-1 text-base text-ink-2" data-campaign-context>
                    They replied to {{ $state->campaign->origin->ownerLabel() }}, sent
                    {{ $state->campaign->sentAt->diffForHumans() }}.
                </p>
            @endif

            @if ($state->status === AgentThreadStatus::HumanTakeover && $state->latchedAt !== null)
                <p class="mt-1 text-base text-ink-2" data-silenced-since>
                    Your assistant has been quiet here since {{ $state->latchedAt->diffForHumans() }}.
                </p>
            @endif

            @if ($state->status === AgentThreadStatus::TurnCapped)
                {{--
                    Rail 3. The turn cap is the assistant working correctly rather
                    than failing, and the sentence says so — an owner told only
                    "handed to you" would read it as a fault.
                --}}
                <p class="mt-1 text-base text-ink-2" data-turn-cap>
                    Your assistant answered {{ $state->turnsUsed }} times here and handed the rest to you.
                </p>
            @endif

            <ol class="mt-4 space-y-4">
                @forelse ($messages as $entry)
                    <li class="border-l-2 border-rule pl-4">
                        <p class="text-sm font-medium text-ink-2">
                            {{ $entry->sender_type->label() }}
                            <span class="font-normal">· {{ $entry->created_at?->diffForHumans() }}</span>
                        </p>
                        <p class="mt-1 whitespace-pre-line break-words text-base text-ink">{{ $entry->body }}</p>
                        @foreach (($entry->attachments ?? []) as $i => $att)
                            @if (($att['status'] ?? '') === 'stored')
                                <x-ui.button variant="secondary" size="default" wire:click="downloadAttachment({{ $entry->id }}, {{ $i }})">Download the {{ $att['type'] }}</x-ui.button>
                            @elseif (($att['status'] ?? '') === 'pending')
                                Saving the {{ $att['type'] }}…
                            @elseif (($att['status'] ?? '') === 'expired')
                                WhatsApp deleted this {{ $att['type'] }} before it could be saved.
                            @elseif (($att['status'] ?? '') === 'too_large')
                                This {{ $att['type'] }} is too large to save here.
                            @elseif (($att['status'] ?? '') === 'refused_health_tenant')
                                This {{ $att['type'] }} was not saved, because this business handles health information.
                            @elseif (($att['status'] ?? '') === 'pruned')
                                This {{ $att['type'] }} was deleted after your media retention period.
                            @else
                                This {{ $att['type'] }} could not be saved.
                            @endif
                        @endforeach
                    </li>
                @empty
                    {{--
                        A thread always opens with the message that created it, so
                        this is the shape of a conversation whose content was
                        erased rather than one that never had any. Saying so beats
                        an empty list — `ScreenStatesTest` is what found it.
                    --}}
                    <li>
                        <x-ui.empty-state icon="◇">
                            There are no messages left on this conversation.
                        </x-ui.empty-state>
                    </li>
                @endforelse
            </ol>

            @if ($thread->channel === 'whatsapp' ? (($contact['phone'] ?? null) === null) : $thread->customer === null)
                <p class="mt-6 text-base text-ink-2">
                    You can read this conversation, but there is nobody left to reply to —
                    this contact was removed.
                </p>
            @elseif ($state->status === AgentThreadStatus::Closed)
                {{--
                    The enum's own contract: replying on a closed thread is a new
                    thread, so that the close summary already sent stays true.
                    ⚠️ Nothing in `app/` closes a thread yet, so this branch is the
                    enum being honoured rather than a state a tenant can reach.
                --}}
                <p class="mt-6 text-base text-ink-2">This conversation is finished.</p>
            @else
                <label class="mt-6 flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Your reply</span>
                    <textarea
                        wire:model="reply"
                        rows="3"
                        maxlength="{{ $this->bodyLimit() }}"
                        class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    ></textarea>
                </label>
                @error('reply')
                    <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <div class="mt-4">
                    <x-ui.button type="button" size="default" wire:click="send">Send</x-ui.button>
                </div>

                @if ($thread->channel === 'whatsapp' && $templates->isNotEmpty())
                    <div class="mt-6 border-t border-rule pt-4">
                        <label class="flex flex-col gap-1">
                            <span class="text-sm font-medium text-ink-2">More than 24 hours since they last wrote? WhatsApp only allows an approved template.</span>
                            <select wire:model="templateId" class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink">
                                <option value="">Choose a template</option>
                                @foreach ($templates as $tpl)
                                    <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        @error('templateId')
                            <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
                        @enderror

                        <div class="mt-4">
                            <x-ui.button type="button" size="default" wire:click="sendTemplate">Send template</x-ui.button>
                        </div>
                    </div>
                @endif
            @endif

            <div class="mt-6 flex flex-wrap gap-3 border-t border-rule pt-4">
                <x-ui.button type="button" size="default" variant="secondary" wire:click="back">
                    Back to your inbox
                </x-ui.button>

                @if ($state->status->agentMaySpeak())
                    {{-- Rail 4 as a control rather than only a consequence: silence
                         the assistant before answering by phone. --}}
                    <x-ui.button type="button" size="default" variant="secondary" wire:click="handOver" data-hand-over>
                        I’ll answer this one
                    </x-ui.button>
                @elseif ($state->status->isReArmable())
                    {{-- Hidden for `Closed`, which `isReArmable()` answers false for
                         — the service no-ops rather than throwing, and a control that
                         does nothing is worse than no control. --}}
                    <x-ui.button type="button" size="default" variant="secondary" wire:click="handBack" data-hand-back>
                        Let your assistant answer again
                    </x-ui.button>
                @endif

                @if ($state->status !== \App\Enums\AgentThreadStatus::Closed)
                    {{-- Rail 9's *resolved*, and the control `close()` shipped
                         without (4543). Hidden once the thread is closed, on
                         `handBack`'s own rule one state over: closing is one-way
                         at the service, so a control that does nothing is worse
                         than no control.

                         ⚠️ THE COPY NAMES THE ACT RATHER THAN ASKING ABOUT IT.
                         A confirmation dialog would be a support surface for a
                         state the customer's next text recreates anyway, and the
                         service is idempotent, so a double click is not an
                         error and sends no second owner summary. --}}
                    <x-ui.button type="button" size="default" variant="secondary" wire:click="finish" data-finish>
                        Mark as finished
                    </x-ui.button>
                @endif
            </div>
        </section>
    @endif
</div>
