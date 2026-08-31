<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Contracts\Agent\AgentThreads;
use App\Models\Conversation;
use App\Services\Knowledge\KnowledgeRetriever;
use App\Services\Knowledge\KnowledgeSnippet;

/**
 * What the assistant is allowed to know before it answers on a thread — T176
 * §2.3 rail 2, patch P3.
 *
 * ⚠️ **THIS IS `KnowledgeRetriever`'s FIRST CONSUMER IN THE APPLICATION.** That
 * class shipped with none, which is CLAUDE.md's 272 shape: a retrieval engine
 * whose isolation tests all pass against a query nothing runs. It runs now.
 *
 * ## Rail 2, and why grounding is a refusal rather than a lookup
 *
 * *"Knowledge-bounded: prices from the list, facts from the store, links from
 * the registry. Anything else is 'I'll get {Owner} to confirm.'"* An empty
 * result is therefore the **correct and common** answer, not a failure — a
 * business that has uploaded nothing has nothing to be grounded on, and the
 * skill that would have used it is absent rather than defaulted (R13, and P6's
 * 4006 for the same argument about a booking URL).
 *
 * ⛔ **IT IS NOT THE ANSWER GATE, AND `KnowledgeRetriever`'s OWN DOCBLOCK SAYS
 * SO IN AS MANY WORDS**: *nearest* is not *relevant*, and with an unrelated
 * corpus retrieval returns the least-unrelated thing in it, confidently.
 * Deciding whether a snippet is good enough to answer from is P4's, with the
 * skills.
 *
 * ## Two things this deliberately does not do
 *
 * ⛔ **IT NEVER RUNS ON THE SYNCHRONOUS PATH.** Retrieval embeds the question
 * before it can compare it, so this is an LLM call wearing a search box's
 * clothes and `29` §2's ≤200ms p95 context packet cannot contain one.
 * `ArchitectureTest` enforces it structurally — `AiRouter` may not be named
 * under `Http/Controllers`, `Http/Middleware`, `Livewire` or `View/Components` —
 * and `GroundAgentTurnJob` is how a screen reaches this.
 *
 * ⛔ **IT NEVER GROUNDS A THREAD THE ASSISTANT MAY NOT SPEAK ON.** Rails 3 and 4
 * are and-ed into one question, {@see ThreadState::mayTakeTurn()}, and asking it
 * here rather than only in the caller is deliberate: 398's shape is an outer
 * guard refusing first and an inner one that is therefore never exercised, and
 * grounding a latched thread is what puts a customer's question and a model's
 * context into the same job while a person is answering.
 *
 * ⚠️ **THE TENANT PREDICATE IS NOT RE-IMPLEMENTED HERE, AND THAT IS THE POINT.**
 * The one similarity query in this application is
 * `KnowledgeChunk::nearestTo()`, whose SQL carries `business_id = ?` and is
 * watched by `TenancyTest`'s *no vector similarity query runs without an
 * explicit tenant predicate*. Reaching past `KnowledgeRetriever` to write a
 * second `ORDER BY embedding <=>` here is exactly what both docblocks refuse.
 */
final class AgentGrounding
{
    public function __construct(
        private readonly AgentThreads $threads,
        private readonly KnowledgeRetriever $retriever,
    ) {}

    /**
     * The tenant's own material for the next turn on this thread.
     *
     * Returns `[]` when the assistant may not speak on the thread at all, which
     * is indistinguishable from "nothing was found" **to the caller on
     * purpose** — the caller's job is to write a reply or not, and both answers
     * mean the same thing to it. `GroundAgentTurnJob` is where
     * the two are told apart for the run row, by asking the state itself.
     *
     * @return list<KnowledgeSnippet>
     */
    public function forNextTurn(Conversation $conversation, string $question): array
    {
        if (! $this->threads->stateFor($conversation)->mayTakeTurn()) {
            return [];
        }

        return $this->retriever->retrieve($question);
    }
}
