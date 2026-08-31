<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Enums\LegalDocumentType;
use App\Enums\TermsAcceptanceMethod;
use App\Models\LegalDocument;

/**
 * The documents a business accepts to open an account, and the versions of them
 * that are live right now (T176 P22).
 *
 * ⛔ **TENANT SIGNUP ONLY — R24.** The owner ruled that terms are for tenants and
 * never for end customers: a reactivation recipient's sending basis is the
 * tenant's import attestation, a missed caller's is their own inbound contact,
 * and no consent screen, checkbox or terms link is ever injected into a
 * conversation thread. Nothing in this class or in `TermsAcceptances` takes a
 * customer, and the table behind them has no column that could name one.
 *
 * ## Why three documents and not one
 *
 * Counsel versions each of them separately, so one row naming "the terms" could
 * only be honest about one of them. A carrier reviewer asks specifically whether
 * the business agreed to the **SMS programme terms**; a data-protection question
 * asks about the **Privacy Policy**; a billing dispute asks about the **Terms of
 * Service**. Recording all three, each with its own version, is what makes those
 * three different questions answerable from one act.
 *
 * ## It fails closed, and that is a gate rather than a bug
 *
 * `ImportStatement` settled the shape one document over: this resolves the
 * wording from the one store that versions text immutably, and refuses when that
 * store is empty. **A platform with no published Terms cannot lawfully bind
 * anybody to them**, so a signup that proceeded would produce an account with no
 * agreement behind it and no record to show a reviewer — decision 272's shape
 * with a contract attached.
 *
 * ⚠️ **THE CONSEQUENCE IS REAL AND IS THE POINT: NOBODY CAN SIGN UP UNTIL
 * COUNSEL'S TEXT IS PUBLISHED** at `/admin/legal/{doc}`. Nothing seeds a
 * published version — `legal:seed` writes unreviewed v0.9 drafts on purpose —
 * so this is an act somebody has to perform before the first customer arrives.
 * Both signup doors say so on their face rather than failing obscurely.
 *
 * ⚠️ **A PUBLISHED PLACEHOLDER COUNTS, UNLIKE `ImportStatement`'S**, and the
 * difference is deliberate. `is_placeholder` marks text counsel has not
 * finished; publishing it freezes it anyway, so it is already what `/legal/terms`
 * serves to every visitor and what a 10DLC submission points a carrier at. An
 * acceptance naming that version resolves to those exact words forever, which is
 * the property this record needs. `39`'s own checklist keeps *wide launch*
 * locked while a served document is a placeholder — that is the gate for
 * unfinished text, and duplicating it here would instead take signup down.
 */
final class SignupTerms
{
    /**
     * What a business accepts, in the order the notice names them.
     *
     * ⚠️ **A FOURTH ENTRY IS A THREE-PLACE CHANGE**: here, the wording on
     * {@see TermsAcceptanceMethod::notice()}, and both signup
     * screens. A lint pairs the first two, because a document accepted without
     * being named is an acceptance of something nobody was told about.
     *
     * @var list<LegalDocumentType>
     */
    public const array DOCUMENTS = [
        LegalDocumentType::Terms,
        LegalDocumentType::SmsTerms,
        LegalDocumentType::Privacy,
    ];

    public function __construct(private readonly LegalDocuments $documents) {}

    /**
     * The live version of every signup document, or null when any is missing.
     *
     * All or nothing on purpose: two of three is not a set of terms, and a
     * partial acceptance would be the harder thing to explain later — the
     * business would have agreed to the Terms while never being shown the SMS
     * programme terms the carrier asks about.
     *
     * @return ?array<value-of<LegalDocumentType>, LegalDocument>
     */
    public function current(): ?array
    {
        $resolved = [];

        foreach (self::DOCUMENTS as $type) {
            $document = $this->documents->current($type);

            if (! $document instanceof LegalDocument) {
                return null;
            }

            $resolved[$type->value] = $document;
        }

        return $resolved;
    }

    /**
     * Whether an account can be opened at all.
     *
     * The screens' question. `current()` answers it too, but a caller that only
     * wants the yes/no reads better for it and cannot accidentally render a form
     * it forgot to guard.
     */
    public function isAvailable(): bool
    {
        return $this->current() !== null;
    }

    /**
     * The versions a person must be shown, or refuse.
     *
     * @return array<value-of<LegalDocumentType>, LegalDocument>
     *
     * @throws SignupTermsUnavailable
     */
    public function requireCurrent(): array
    {
        $resolved = $this->current();

        if ($resolved === null) {
            throw new SignupTermsUnavailable(
                'No account can be opened until the Terms of Service, the SMS & Communications '
                .'Terms and the Privacy Policy are all published. The wording is counsel\'s and '
                .'an admin publishes each version at /admin/legal/{doc} — a published version '
                .'with a recorded reviewer, not a working draft. Nothing seeds one, deliberately.'
            );
        }

        return $resolved;
    }

    /**
     * What the signup screens link to, if anything.
     *
     * ⚠️ **THE LINKS ARE THE HALF THAT MAKES THE NOTICE MEAN ANYTHING.** A
     * sentence naming three documents with no way to read them is the shape `24`
     * §3.2 already refuses inside a TCPA disclosure — and both signup surfaces
     * carry the same three, so the list is built here rather than typed twice.
     *
     * @return list<array{title: string, url: string, version: string}>
     */
    public function links(): array
    {
        $resolved = $this->current();

        if ($resolved === null) {
            return [];
        }

        return array_map(
            fn (LegalDocumentType $type): array => [
                'title' => $type->title(),
                'url' => route('legal.document', ['doc' => $type->value]),
                'version' => (string) $resolved[$type->value]->version,
            ],
            self::DOCUMENTS,
        );
    }
}
