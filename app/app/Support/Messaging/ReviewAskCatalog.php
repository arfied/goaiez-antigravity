<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Enums\SmsEncoding;
use App\Services\Messaging\Composer\SmsBudget;
use App\Services\Messaging\ReviewInviteSender;
use App\Support\LegalCanon;
use RuntimeException;

/**
 * THE REVIEW-ASK TEMPLATES — LP-0's `LP-es` S6 SMS set, CC-5 §3.
 *
 * ## ⚠️ THE ENGLISH SET IS NOT HERE, BECAUSE IT ALREADY EXISTS AS CODE
 *
 * CC-5 §3 asks for *"the EN set + the ES twins"*, and CC-5's bridge says a
 * finding of *"already exists as X"* is a full pass. The English ask this
 * application sends is {@see ReviewInviteSender::compose()} — a fixed sentence
 * pair whose own docblock refuses at length to become a template engine
 * (*"THIS IS NOT THE COMPOSER AND MUST NOT GROW INTO ONE"*, decision 1570). It
 * is live, it is the wording every invite has ever carried, and re-authoring it
 * here as a template would mean two English asks in the codebase with nothing
 * saying which one sends. **Mapped, not rebuilt.**
 *
 * ⚠️ **AND THE EN S6 SET IS NOT IN THIS REPOSITORY EITHER.** LP-0 authors
 * `LP-es` and says *"en/es already covered by the roster"* — the English S6
 * strings live in the GOAIEZ package the owner holds. So there was nothing to
 * seed even if there had been somewhere to put it.
 *
 * ## ⛔ THE ES SET IS SEEDED, VALIDATED, AND NOT SENDABLE — AND THE REASON IS A
 * RULING THIS REPOSITORY ALREADY MADE
 *
 * `lang/en/feedback.php`'s own docblock, about the Spanish half of FPR-01:
 * *"the disclosure below is TCPA language, and `29` 12.1's prelaunch gate puts
 * counsel review before legal text ships. An unreviewed Spanish disclosure is a
 * liability, not a feature."*
 *
 * LP-0's ES lines each end in a Spanish opt-out sentence — *"Responde STOP para
 * salir"* — which is exactly that kind of text, drafted by a language pack
 * rather than reviewed by counsel. CC-5 §3 meanwhile requires the footer to be
 * **composed from `legal.sms_ask_footer` at send time**, and that key is one key
 * with no per-locale twin. Both roads end in the same place:
 *
 *  - seeding the Spanish suffix as the sent footer would ship unreviewed legal
 *    text and would bake a footer §3 requires binding;
 *  - appending the English canon to a Spanish message would put a compliance
 *    sentence in front of somebody in a language they did not choose.
 *
 * So the bodies are seeded and length-validated, {@see self::AUTHORED_FOOTER}
 * records what LP-0 wrote, and `ReviewAskComposer` **refuses** any locale the
 * canon has no twin for. What is owed is one Spanish footer from counsel through
 * {@see LegalCanon}, and nothing else. Decision 5257.
 *
 * ## ⚠️ WHAT WAS RESTORED FROM THE SOURCE, AND ON THE SOURCE'S OWN INSTRUCTION
 *
 * LP-0 writes its lines with the accents stripped and then corrects itself:
 * *"CANONICAL RULE the ledgered pack applies: ñ é ü ¿ ¡ used freely; á í ó ú
 * avoided by word choice — the strings above are shipped with ñ/é restored:
 * `reseña` · `Déjanos`"*. Exactly those two restorations are applied and no
 * others. `Que tal`, `Cuanto`, `encantaria` and `Como` stay as written, because
 * their accents are `á í ó ú` — **not in GSM-7**, which is the whole reason LP-0
 * wrote around them, and "correcting" one would halve the segment budget for the
 * message it appears in. {@see SmsBudget} is what proves that rather than this
 * paragraph.
 *
 * ## ⚠️ THE THREE MMS CAPTIONS ARE AUTHORED AND DELIBERATELY NOT SEEDED
 *
 * LP-0's `M-A`/`M-B`/`M-C` are picture captions, and this application has no MMS
 * review-ask path at all — `CampaignMedia` attaches an image to a *campaign*,
 * never to an invite. Seeding three captions nothing can render would be a row
 * with no reader, which is `CLAUDE.md`'s first recurring failure. They arrive
 * with the path.
 */
final class ReviewAskCatalog
{
    /**
     * The single-segment ceiling, **by measured encoding rather than by locale**.
     *
     * ⛔ **CC-5 §3 SAYS "159 EN / 134 ES" AND THAT IS WRONG FOR SPANISH — READ
     * LP-0 ITSELF, NOT A SUMMARY OF IT** (decision 5259). LP-0's encoding law
     * assigns the two figures to *alphabets*, not to countries:
     *
     *   *"Write-around GSM locales … stay ≤159 … GSM-locales: the standing
     *   ≤159-incl-link law … UCS-2 locales: target ≤134 chars incl. link
     *   (= 2 segments)"*
     *
     * and it names Spanish in the first group, with the trap spelled out —
     * *"GSM-7's Spanish trap: `é ñ ü à è ì ò ù ¿ ¡` ARE in-set; **`á í ó ú` are
     * NOT** — packs write around them"*. 134 is the figure for Cyrillic, Greek,
     * Arabic, CJK and the GSM-hostile Latin set. **Applying it to Spanish
     * refuses `es-t1`, a line LP-0 authored, at 145 units — which is how this
     * was found: the seed threw.**
     *
     * ⚠️ **SO IT IS KEYED ON THE ENCODING THIS APPLICATION ACTUALLY MEASURES**,
     * which is stronger than either fixed number: a template that quietly
     * acquires an `á` stops being a 159-unit message the instant it does, and
     * the ceiling follows it down without anybody re-reading a locale table.
     * `CLAUDE.md`'s rule — verify a parameter against the raw artefact, never
     * against a summary of it — is what turned this up.
     *
     * @var array<string, int>
     */
    public const array CEILING = [SmsEncoding::Gsm7->value => 159, SmsEncoding::Ucs2->value => 134];

    /**
     * What LP-0 authored as the tail of every ES line.
     *
     * ⛔ **A RECORD, NEVER A SEND.** It is here so that the canon LP-0 shipped is
     * not lost, and so that whoever supplies the counsel-reviewed Spanish footer
     * can see what the language pack proposed. `ReviewAskComposer` never reads
     * it — see the class docblock.
     */
    public const string AUTHORED_FOOTER = 'Responde STOP para salir';

    /**
     * How long a slot may be when a line is measured — LP-0's own budgets.
     *
     * *"slots at Name≤12 · Business≤18 · Link≤24"*. Measuring the raw template
     * would call every line short by the length of its own placeholders, which
     * is the measurement that lets a real send overrun.
     *
     * @var array<string, int>
     */
    public const array SLOT_BUDGET = ['{name}' => 12, '{business}' => 18, '{link}' => 24];

    /**
     * Every seeded ask template, validated on the way out.
     *
     * ⚠️ **VALIDATED HERE RATHER THAN IN A TEST ALONE — CC-5 §3's "fail loud".**
     * A test proves the catalogue as it stands today; this refuses to hand back
     * a line that would not fit, so an edit made after the test was written
     * cannot reach a send either. The test drives it red by mutation so the
     * check itself is not decoration.
     *
     * @return list<array{key: string, locale: string, body: string}>
     *
     * @throws RuntimeException when an authored line cannot fit its ceiling
     */
    public static function templates(): array
    {
        $templates = self::declared();

        foreach ($templates as $template) {
            self::assertFits($template);

            MessageSlots::assertOnly(
                $template['body'],
                ['{name}', '{business}', '{link}'],
                "The ask template {$template['key']}",
            );
        }

        return $templates;
    }

    /**
     * LP-0 `LP-es`, T1 through T7, with `{Name}`/`{Business}`/`{Link}`
     * translated to the house spelling and the authored footer lifted out.
     *
     * @return list<array{key: string, locale: string, body: string}>
     */
    private static function declared(): array
    {
        return [
            ['key' => 'es-t1', 'locale' => 'es',
                'body' => 'Hola {name}! Somos {business}. Gracias por elegirnos. Nos dejas una breve reseña? {link}'],

            ['key' => 'es-t2', 'locale' => 'es',
                'body' => 'Hola {name}! {business} te saluda. Que tal todo? Tu reseña nos ayuda mucho: {link}'],

            ['key' => 'es-t3', 'locale' => 'es',
                'body' => '{business}: Hola {name}, esperamos que todo siga muy bien. Nos compartes una reseña? {link}'],

            ['key' => 'es-t4', 'locale' => 'es',
                'body' => 'Hola {name}, somos {business}. Cuanto tiempo! Nos encantaria saber de ti: {link}'],

            ['key' => 'es-t5', 'locale' => 'es',
                'body' => 'Hola {name}, mil gracias de parte de {business}! Tu reseña vale mucho: {link}'],

            ['key' => 'es-t6', 'locale' => 'es',
                'body' => 'Hola {name}! Somos {business}. Como fue tu experiencia? Deja tu reseña: {link}'],

            // LP-0's "sin nombre" variant — the one for a contact whose name we
            // do not have. It is not the same message with the greeting dropped:
            // `NameNormaliser` answers null for a contact it cannot greet, and
            // "Hola there!" is not Spanish.
            ['key' => 'es-t7', 'locale' => 'es',
                'body' => 'Hola! Somos {business}. Gracias por elegirnos. Nos dejas una breve reseña? {link}'],
        ];
    }

    /**
     * Measure one line the way LP-0 measured it, and refuse it if it will not
     * fit.
     *
     * @param  array{key: string, locale: string, body: string}  $template
     *
     * @throws RuntimeException
     */
    private static function assertFits(array $template): void
    {
        $budget = new SmsBudget;

        // ⚠️ **MEASURED WITH THE AUTHORED FOOTER ATTACHED, WHICH IS HOW LP-0
        // MEASURED IT** — *"≤159 chars incl. link"*, and its own lines carry
        // their opt-out sentence. The footer that actually sends comes from the
        // registry and its length is not knowable here; `ReviewAskComposer`
        // measures the real composition at send time, which is the check this
        // one cannot make and vice versa.
        $measured = self::atFullSlotBudget($template['body']).' '.self::AUTHORED_FOOTER;

        $encoding = $budget->encodingOf($measured);
        $length = $budget->lengthOf($measured);
        $ceiling = self::CEILING[$encoding->value];

        if ($length <= $ceiling) {
            return;
        }

        throw new RuntimeException(sprintf(
            'The ask template %s measures %d %s units with every slot at its budget and its footer '
            .'attached, and the ceiling for that alphabet is %d. %sNothing here truncates: the '
            .'first thing a length limit cuts is the tail, and the tail is the opt-out sentence. '
            .'Shorten the line.',
            $template['key'],
            $length,
            $encoding->value,
            $ceiling,
            $encoding === SmsEncoding::Ucs2
                ? 'It contains at least one character outside GSM-7 — for a Spanish line that is '
                    .'almost always an `á`, `í`, `ó` or `ú`, which LP-0 writes around by word choice '
                    .'rather than by dropping the accent. '
                : '',
        ));
    }

    /**
     * The line with every slot expanded to the longest value it may hold.
     */
    private static function atFullSlotBudget(string $body): string
    {
        foreach (self::SLOT_BUDGET as $slot => $budget) {
            $body = str_replace($slot, str_repeat('x', $budget), $body);
        }

        return $body;
    }
}
