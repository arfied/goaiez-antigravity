<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Enums\OutreachChannel;
use App\Events\Voice\VoicemailTranscribed;
use App\Models\AssistantBrief;
use App\Models\UrgentTerm;
use App\Services\Agent\AgentTurns;
use App\Services\Config\DefaultsRegistry;
use App\Support\Identifier;
use App\Support\Tenancy;
use InvalidArgumentException;

/**
 * The words that mean drop everything, and the number to give out — T176 P5.
 *
 * §2.2 skill 9: *"tenant-defined urgent terms (lockout, leak, flood, no-heat…)
 * → immediate owner SMS+email ping; give the tenant's emergency line if set;
 * life-safety → advise emergency services, no advice beyond that"*, grounded by
 * R13 on *"urgent-terms list (P5)"*.
 *
 * ⛔ **THE ONLY READER AND WRITER OF `urgent_terms`, AND OF `emergency_line` ON
 * `assistant_briefs`, HELD THERE BY A LINT**
 * (`tests/Feature/Architecture/PricesTest.php`). The reason is {@see matches()}:
 * the matching rule *is* the behaviour of this table, and a second reader is a
 * second matching rule. Two answers to "is this urgent?" is how a term shows as
 * live on the owner's screen while the escalation never fires on it.
 *
 * ## ⛔ AND IT IS WHERE `29` §19.6's ESCALATION CLAUSE LIVES (6880–6890)
 *
 * §19.6 — the telephony and voice gate list — requires that *"emergency keywords
 * escalate immediately, including overnight"*. That clause used to be claimed by
 * `support_settings.emergency_keywords`: a jsonb column seeding six words on
 * every row, citing §19.6 by section number, **with no reader and no writer**.
 * It is dropped, and the rule is this table's.
 *
 * ⚠️ **THE ARGUMENT IS THAT A BUSINESS HAS ONE VOCABULARY OF URGENCY, NOT ONE
 * PER CHANNEL.** The owner answers one question — *which words mean drop
 * everything?* — on one screen, and the answer cannot depend on whether the
 * customer typed it or said it. A second list is a second matching rule wearing
 * a channel as a disguise, and {@see matches()}'s own docblock already refuses
 * second matching rules.
 *
 * ⛔ **AND THE §19.6 CLAUSE IS ONLY HALF SERVED TODAY, WHICH IS THE HONEST
 * READING AND NOT A REASON TO KEEP A SECOND TABLE** (6891). This path covers
 * **inbound text**: {@see AgentTurns::escalateAsUrgent()}
 * is the caller. §19.6's own subject is **voice**, and its clause sits beside
 * *"low-confidence transcripts flagged"* — a voicemail transcript containing an
 * urgent term escalates nowhere, because
 * {@see VoicemailTranscribed} reaches **zero listeners**.
 * ⚠️ **THE LISTENER IS DELIBERATELY NOT BUILT**: the transcription path is dark
 * end to end (`NullTranscriber` returns null, `VOICE_DRIVER` selects the null
 * driver, `voice.enabled` seeds `false`), so a listener written today could
 * never fire and would be `sending_health_windows`' shape — a containment that
 * cannot run, green under tests that seed their own inputs. **When it is built
 * it reads this table**, through this class, like every other reader.
 *
 * ## ⛔ THE LIST BEING EMPTY IS NOT PERMISSION FOR SILENCE
 *
 * R13 makes an empty list mean *this skill's trigger is absent* — the assistant
 * does not decide by itself that a message is urgent. It does **not** mean the
 * assistant stops behaving safely: skill 9's *"life-safety → advise emergency
 * services"* is unconditional and is P4's, not this class's. Recorded here
 * because an empty table is exactly the thing somebody later reads as "we were
 * told to say nothing".
 */
final class UrgentTerms
{
    /**
     * The most terms one business may hold.
     *
     * ⚠️ **A CEILING RATHER THAN A PAGE, AND IT PROTECTS THE FEATURE RATHER THAN
     * THE DATABASE.** Every term widens what pages an owner at 3am; a list of two
     * hundred words matches every message, and an escalation that fires on
     * everything is one nobody reads — which disarms it for the words that
     * mattered.
     */
    public const int MAX_TERMS = 40;

    /**
     * The longest term the store will take — the column's own width.
     */
    public const int MAX_LENGTH = 60;

    private function maxTerms(): int
    {
        return app(DefaultsRegistry::class)->int('assistant.urgent.max_terms');
    }

    private function maxLength(): int
    {
        return app(DefaultsRegistry::class)->int('assistant.urgent.max_length');
    }

    /**
     * @return list<string>
     */
    public function terms(): array
    {
        Tenancy::idOrFail();

        $terms = [];

        foreach (UrgentTerm::query()->orderBy('term')->get() as $row) {
            $terms[] = $row->term;
        }

        return $terms;
    }

    /**
     * The number skill 9 gives out, or null.
     *
     * ⛔ **NULL IS AN ANSWER AND NOT A FAILURE** (R13). The assistant escalates to
     * the owner either way; what it does not do is invent a number, and there is
     * no number that is right for a business which has not given us one.
     */
    public function emergencyLine(): ?string
    {
        Tenancy::idOrFail();

        return $this->brief()?->emergency_line;
    }

    /**
     * Whether skill 9's trigger is grounded (R13).
     */
    public function groundsEscalation(): bool
    {
        return $this->terms() !== [];
    }

    /**
     * Which of this business's terms a message contains.
     *
     * ⚠️ **THE MATCHING RULE LIVES HERE BECAUSE IT IS THE POINT OF THE TABLE.**
     * Case-insensitive, and on **word boundaries** — which is the whole of the
     * care this needs. A naive `str_contains()` fires "leak" on "leaked",
     * which is fine, and on **"Bleak Street"**, which pages an owner at 3am about
     * an address; and it fires "flood" on "floodlight". Anchoring to boundaries
     * costs one regex and removes the class of false positive that trains people
     * to ignore the alert.
     *
     * ⚠️ **THE MESSAGE IS UNTRUSTED AND IS NEVER USED AS A PATTERN.** The terms
     * are the patterns and they are quoted; the member of the public's words are
     * only ever the subject. Reversing those two would let an inbound text carry
     * a regex.
     *
     * @return list<string> the terms that matched, in the order the business
     *                      stores them — empty is the ordinary answer
     */
    public function matches(string $message): array
    {
        $matched = [];

        foreach ($this->terms() as $term) {
            $needle = trim($term);

            if ($needle === '') {
                continue;
            }

            // `\b` is wrong at either end when the term itself begins or ends
            // with a non-word character ("no-heat", "24/7"), where it would
            // never match. `(?<!\w)` / `(?!\w)` say the same thing about the
            // text around the term without asserting anything about the term.
            $pattern = '/(?<!\w)'.preg_quote($needle, '/').'(?!\w)/iu';

            if (preg_match($pattern, $message) === 1) {
                $matched[] = $term;
            }
        }

        return $matched;
    }

    /**
     * Add a word that means drop everything.
     */
    public function add(string $term): string
    {
        Tenancy::idOrFail();

        $normalised = $this->normalise($term);

        // ⚠️ CHECKED AGAINST THE STORE RATHER THAN AGAINST THE UNIQUE INDEX'S
        // ERROR, so that adding a term twice is a no-op an owner reads as "it is
        // already on the list" rather than a 500. The index is still there, and
        // it is what holds when a second writer appears (216's second layer).
        $existing = $this->terms();

        foreach ($existing as $held) {
            if (mb_strtolower($held) === mb_strtolower($normalised)) {
                return $held;
            }
        }

        if (count($existing) >= $this->maxTerms()) {
            throw new InvalidArgumentException(
                'That is as many urgent words as one business can have. Remove one you no longer '
                .'need first — a list that matches everything wakes you for everything.'
            );
        }

        $row = new UrgentTerm;

        $row->forceFill(['term' => $normalised])->save();

        return $normalised;
    }

    public function remove(string $term): void
    {
        Tenancy::idOrFail();

        $normalised = trim($term);

        if ($normalised === '') {
            throw new InvalidArgumentException('Removing an urgent word needs the word.');
        }

        UrgentTerm::query()->whereRaw('lower(btrim(term)) = ?', [mb_strtolower($normalised)])->delete();
    }

    /**
     * Set the number skill 9 gives out, or clear it.
     *
     * ⚠️ **NORMALISED THROUGH `Identifier`, WHICH IS THE SAME NORMAL FORM EVERY
     * OTHER NUMBER IN THIS APPLICATION IS STORED IN.** A number typed as
     * `(555) 123-4567` and given out as `(555) 123-4567` would work; storing one
     * normal form everywhere is what stops the day somebody compares this
     * against a `phone_numbers.e164` and finds no match.
     */
    public function setEmergencyLine(?string $number): ?string
    {
        Tenancy::idOrFail();

        $typed = $number === null ? '' : trim($number);

        if ($typed === '') {
            $this->briefForWriting()->forceFill(['emergency_line' => null])->save();

            return null;
        }

        $normalised = Identifier::normalise($typed, OutreachChannel::Sms);

        if ($normalised === null) {
            // ⛔ REFUSED RATHER THAN STORED AS TYPED. A number this application
            // cannot parse is one it would read out to a member of the public in
            // an emergency, and the failure would surface as somebody dialling
            // nothing at the worst possible moment.
            throw new InvalidArgumentException(
                'That does not look like a phone number we can read out. Write it with the area '
                .'code, like 555 123 4567.'
            );
        }

        $this->briefForWriting()->forceFill(['emergency_line' => $normalised])->save();

        return $normalised;
    }

    private function normalise(string $term): string
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $term) ?? '');

        if ($trimmed === '') {
            throw new InvalidArgumentException('An urgent word needs to be a word.');
        }

        if (mb_strlen($trimmed) > $this->maxLength()) {
            throw new InvalidArgumentException(
                'That is a sentence rather than a word. Use the word a customer would actually type, '
                .'like "lockout" or "no heat".'
            );
        }

        if (preg_match('/\p{L}|\p{N}/u', $trimmed) !== 1) {
            throw new InvalidArgumentException(
                'That has no letters or numbers in it, so nothing a customer writes could ever match it.'
            );
        }

        return $trimmed;
    }

    private function brief(): ?AssistantBrief
    {
        return AssistantBrief::query()->first();
    }

    private function briefForWriting(): AssistantBrief
    {
        return $this->brief() ?? new AssistantBrief;
    }
}
