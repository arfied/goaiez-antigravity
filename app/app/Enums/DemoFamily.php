<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\DefaultsManifest;

/**
 * The six family doors of `/demo/{family}` — CC-2 §2.7.
 *
 * A person on a marketing page picks the door that looks like their business,
 * texts the keyword on it, and watches the front desk answer. The six are the
 * owner's grouping and not a taxonomy of anything: they exist so that a hundred
 * industry landers (CC-3) each have somewhere to send a visitor, and so that one
 * demo number can tell a plumber's text from a dentist's.
 *
 * ⚠️ **THE KEYWORD IS A REGISTRY ROW AND THE NUMBER IS AN UNSET ONE.** Neither is
 * written here. {@see self::keywordKey()} names the row; `DefaultsManifest` holds
 * the seed. The number the keyword is texted **to** has never been stated by the
 * owner, so `demo.number` carries no seed at all and every block that needs it
 * renders **not at all** rather than with a placeholder — the same rule the
 * Limited tier price follows on the marketing home (262, 502).
 *
 * ⛔ **A KEYWORD IS A PROMISE THAT SOMETHING ANSWERS.** Nothing in `app/` routes
 * these words to a demo tenant yet: `InboundKeyword` parses STOP/HELP/START and
 * everything else is `None`. So the moment `demo.number` is set, the six doors
 * start telling members of the public to text a number, and whatever answers them
 * has to exist by then. That responder is owed and it is not this slice's — the
 * note on `demo.number` in {@see DefaultsManifest} says so where an
 * operator setting the row will read it.
 */
enum DemoFamily: string
{
    case Trades = 'trades';
    case Care = 'care';
    case Auto = 'auto';
    case Food = 'food';
    case Medspa = 'medspa';
    case Office = 'office';

    /**
     * The word a visitor reads, lower case, because it sits mid-sentence.
     */
    public function label(): string
    {
        return match ($this) {
            self::Trades => 'trades',
            self::Care => 'care',
            self::Auto => 'auto',
            self::Food => 'food',
            self::Medspa => 'medspa',
            self::Office => 'office',
        };
    }

    /**
     * The indefinite article the label takes.
     *
     * Written out rather than derived from the first letter, because "an" before
     * a vowel is a rule about sound and not about spelling — the derivation is
     * right for these six by luck and wrong for the first family added whose name
     * begins with a consonant sound spelled with a vowel.
     */
    public function article(): string
    {
        return match ($this) {
            self::Auto, self::Office => 'an',
            self::Trades, self::Care, self::Food, self::Medspa => 'a',
        };
    }

    /**
     * The registry row holding this door's keyword.
     *
     * One key per family rather than one key holding six, so an operator can
     * change the word a single door listens for without rewriting a blob — and so
     * that a missing one is missing for one door instead of for all of them.
     */
    public function keywordKey(): string
    {
        return 'demo.keyword.'.$this->value;
    }
}
