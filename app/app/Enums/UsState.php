<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The USPS state and territory codes `customers.region_code` may hold.
 *
 * ⚠️ **THE SHAPE CHECK WAS THE PERMISSIVE BRANCH, AND IT WAS SITTING INSIDE THE
 * WRITER** (1596). `customers.region_code` shipped with a CHECK of
 * `^[A-Z]{2}$`, which admits `ZZ` — and a bogus code finds no
 * `state_messaging_rules` row, which `ConsentService::stateRefusal()` correctly
 * reads as *"no state rule stricter than federal here"* and **permits**. So a
 * typo in a spreadsheet column, or a Canadian province code in a US contact
 * list, would have relaxed a mini-TCPA window rather than refusing — decision
 * 1568's shape one layer in, arriving with the very writer that closes it.
 *
 * A list, therefore, and not a pattern. This enum is that list, and it is the
 * only place a two-letter code is decided:
 *
 *   `CustomerEditor::setRegion()`  the owner's typed or picked value
 *   `CustomerImports`             a `state` column in an uploaded file
 *   the CHECK on `customers`      rebuilt from these cases, with a test that
 *                                 parses the migration and compares the two so
 *                                 they cannot drift
 *
 * ⚠️ **THE COLUMN IS DELIBERATELY NOT CAST TO THIS ENUM.** A cast turns a row
 * that somehow holds an unknown code into a `ValueError` on *read* — so a single
 * bad row would 500 every screen that touched that contact, including the send
 * path, rather than refusing one message. The refusal belongs at the write, and
 * the read stays a plain string that `StateMessagingRules::for()` looks up.
 *
 * ⚠️ **MILITARY CODES (`AA`, `AE`, `AP`) ARE NOT HERE, AND THAT IS A CHOICE.**
 * They are USPS routing designations for overseas military mail, not
 * jurisdictions — no legislature writes a mini-TCPA statute for `AE`, so a
 * contact carrying one could never match a rule and would be permitted by the
 * absence of one. Leaving them out makes that contact `StateUnknown` instead,
 * which is the fail-closed direction and the same reasoning the whole state gate
 * rests on.
 */
enum UsState: string
{
    case Alabama = 'AL';
    case Alaska = 'AK';
    case Arizona = 'AZ';
    case Arkansas = 'AR';
    case California = 'CA';
    case Colorado = 'CO';
    case Connecticut = 'CT';
    case Delaware = 'DE';
    case DistrictOfColumbia = 'DC';
    case Florida = 'FL';
    case Georgia = 'GA';
    case Hawaii = 'HI';
    case Idaho = 'ID';
    case Illinois = 'IL';
    case Indiana = 'IN';
    case Iowa = 'IA';
    case Kansas = 'KS';
    case Kentucky = 'KY';
    case Louisiana = 'LA';
    case Maine = 'ME';
    case Maryland = 'MD';
    case Massachusetts = 'MA';
    case Michigan = 'MI';
    case Minnesota = 'MN';
    case Mississippi = 'MS';
    case Missouri = 'MO';
    case Montana = 'MT';
    case Nebraska = 'NE';
    case Nevada = 'NV';
    case NewHampshire = 'NH';
    case NewJersey = 'NJ';
    case NewMexico = 'NM';
    case NewYork = 'NY';
    case NorthCarolina = 'NC';
    case NorthDakota = 'ND';
    case Ohio = 'OH';
    case Oklahoma = 'OK';
    case Oregon = 'OR';
    case Pennsylvania = 'PA';
    case RhodeIsland = 'RI';
    case SouthCarolina = 'SC';
    case SouthDakota = 'SD';
    case Tennessee = 'TN';
    case Texas = 'TX';
    case Utah = 'UT';
    case Vermont = 'VT';
    case Virginia = 'VA';
    case Washington = 'WA';
    case WestVirginia = 'WV';
    case Wisconsin = 'WI';
    case Wyoming = 'WY';

    // The five inhabited territories. Each has its own legislature, so each can
    // carry a `state_messaging_rules` row exactly as a state can.
    case AmericanSamoa = 'AS';
    case Guam = 'GU';
    case NorthernMarianaIslands = 'MP';
    case PuertoRico = 'PR';
    case VirginIslands = 'VI';

    /**
     * The code, or null when nothing recognisable was supplied.
     *
     * ⚠️ **NORMALISES BEFORE IT MATCHES, AND RETURNS NULL RATHER THAN
     * THROWING.** `' fl '` and `'FL'` are the same jurisdiction and a stored
     * lowercase `fl` matches no `state_messaging_rules` row while looking
     * perfectly populated — the silent shape the whole scrubbing slice is built
     * against. Null is the honest answer for a cell that says `Ontario`, and the
     * import path depends on it: an unrecognisable cell leaves the contact with
     * no jurisdiction rather than failing the row or guessing one.
     */
    public static function normalise(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(mb_strtoupper(trim($value)));
    }

    /**
     * Every code, in the order the cases are declared.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_map(static fn (self $state): string => $state->value, self::cases());
    }

    /**
     * Every clock this jurisdiction keeps — one IANA zone per distinct offset it
     * spans, not one per identifier inside it (1609).
     *
     * ⚠️ **THIS IS NOT THE DERIVATION 1598 FORBIDS, AND A READER WILL ASSUME IT
     * IS.** 1598 refuses deriving a **stored fact** — `locations.timezone`, the
     * zone a named business keeps its hours in — from a state, because six
     * states straddle two zones and the guess is wrong in the direction that
     * texts people earlier. Nothing here asserts which zone anybody is in. This
     * returns the **set** of clocks a state contains, and its only caller
     * refuses when the quiet-hours window is closed in **any** of them. A
     * straddling state therefore makes the refusal *wider*, never a claim: the
     * Panhandle case that killed the derivation is the case this answers
     * correctly, because Florida contributes both Eastern and Central and 20:30
     * Eastern refuses at 19:30 Central.
     *
     * ⚠️ **REPRESENTATIVES, NOT AN EXHAUSTIVE LIST OF IDENTIFIERS.** Indiana
     * alone has seven IANA zones and every one of them keeps either New York's
     * clock or Chicago's; North Dakota has three that keep either Chicago's or
     * Denver's. Naming the representative of each distinct offset answers the
     * only question asked of this list — *what time is it there* — and a list of
     * every identifier would be a maintenance surface with no extra answer in
     * it. `America/Phoenix` and `America/Denver` are separately named because
     * they are genuinely different clocks for half the year, which is why
     * Arizona carries both: the Navajo Nation observes DST and the rest of the
     * state does not.
     *
     * ⚠️ **A CONSTANT, NOT A REGISTRY KEY** — unlike the platform quiet-hours
     * window it is compared against. Where a state's borders fall is a fact
     * about US geography that no operator may move in Ops; the *hours* are
     * policy and live in `DefaultsManifest`. 1420's rule cuts exactly there.
     *
     * @return list<string>
     */
    public function timezones(): array
    {
        return match ($this) {
            // Eastern only.
            self::Connecticut, self::Delaware, self::DistrictOfColumbia,
            self::Georgia, self::Maine, self::Maryland, self::Massachusetts,
            self::NewHampshire, self::NewJersey, self::NewYork,
            self::NorthCarolina, self::Ohio, self::Pennsylvania,
            self::RhodeIsland, self::SouthCarolina, self::Vermont,
            self::Virginia, self::WestVirginia => ['America/New_York'],

            // Central only. ⚠️ Oklahoma is legally Central end to end — the
            // village of Kenton keeps Mountain time by local habit, which is not
            // a jurisdiction and not an IANA zone, so it is not named here.
            self::Alabama, self::Arkansas, self::Illinois, self::Iowa,
            self::Louisiana, self::Minnesota, self::Mississippi, self::Missouri,
            self::Oklahoma, self::Wisconsin => ['America/Chicago'],

            // Mountain only.
            self::Colorado, self::Montana, self::NewMexico, self::Utah,
            self::Wyoming => ['America/Denver'],

            // Pacific only.
            self::California, self::Washington => ['America/Los_Angeles'],

            // Eastern and Central. Florida's panhandle west of the Apalachicola,
            // Indiana's north-west and south-west corners, Kentucky's and
            // Tennessee's western halves, and Michigan's four Wisconsin-border
            // counties (`America/Menominee`).
            self::Florida, self::Indiana, self::Kentucky, self::Michigan,
            self::Tennessee => ['America/New_York', 'America/Chicago'],

            // Central and Mountain. Kansas's four western counties, Nebraska's
            // panhandle, both Dakotas' western counties, and Texas's El Paso and
            // Hudspeth.
            self::Kansas, self::Nebraska, self::NorthDakota, self::SouthDakota,
            self::Texas => ['America/Chicago', 'America/Denver'],

            // Pacific and Mountain. Northern Idaho keeps Pacific time; most of
            // Oregon's Malheur County and Nevada's West Wendover keep Mountain.
            self::Idaho, self::Oregon,
            self::Nevada => ['America/Los_Angeles', 'America/Denver'],

            // Mountain, split by whether DST is observed. The Navajo Nation
            // follows Denver; the rest of Arizona does not move.
            self::Arizona => ['America/Phoenix', 'America/Denver'],

            // Alaska and the Aleutians west of 169°30′W, which keep Hawaii's
            // clock with DST.
            self::Alaska => ['America/Anchorage', 'America/Adak'],

            self::Hawaii => ['Pacific/Honolulu'],

            // The five inhabited territories, each with one zone and none of
            // them observing DST.
            self::AmericanSamoa => ['Pacific/Pago_Pago'],
            self::Guam => ['Pacific/Guam'],
            self::NorthernMarianaIslands => ['Pacific/Saipan'],
            self::PuertoRico => ['America/Puerto_Rico'],
            self::VirginIslands => ['America/St_Thomas'],
        };
    }

    /**
     * The name a person picks from a list.
     *
     * Outcome language does not apply to a proper noun (`22`), but the *order*
     * does: the select is alphabetical by this label, which is how somebody
     * looks for their own state.
     */
    public function label(): string
    {
        return match ($this) {
            self::Alabama => 'Alabama',
            self::Alaska => 'Alaska',
            self::Arizona => 'Arizona',
            self::Arkansas => 'Arkansas',
            self::California => 'California',
            self::Colorado => 'Colorado',
            self::Connecticut => 'Connecticut',
            self::Delaware => 'Delaware',
            self::DistrictOfColumbia => 'District of Columbia',
            self::Florida => 'Florida',
            self::Georgia => 'Georgia',
            self::Hawaii => 'Hawaii',
            self::Idaho => 'Idaho',
            self::Illinois => 'Illinois',
            self::Indiana => 'Indiana',
            self::Iowa => 'Iowa',
            self::Kansas => 'Kansas',
            self::Kentucky => 'Kentucky',
            self::Louisiana => 'Louisiana',
            self::Maine => 'Maine',
            self::Maryland => 'Maryland',
            self::Massachusetts => 'Massachusetts',
            self::Michigan => 'Michigan',
            self::Minnesota => 'Minnesota',
            self::Mississippi => 'Mississippi',
            self::Missouri => 'Missouri',
            self::Montana => 'Montana',
            self::Nebraska => 'Nebraska',
            self::Nevada => 'Nevada',
            self::NewHampshire => 'New Hampshire',
            self::NewJersey => 'New Jersey',
            self::NewMexico => 'New Mexico',
            self::NewYork => 'New York',
            self::NorthCarolina => 'North Carolina',
            self::NorthDakota => 'North Dakota',
            self::Ohio => 'Ohio',
            self::Oklahoma => 'Oklahoma',
            self::Oregon => 'Oregon',
            self::Pennsylvania => 'Pennsylvania',
            self::RhodeIsland => 'Rhode Island',
            self::SouthCarolina => 'South Carolina',
            self::SouthDakota => 'South Dakota',
            self::Tennessee => 'Tennessee',
            self::Texas => 'Texas',
            self::Utah => 'Utah',
            self::Vermont => 'Vermont',
            self::Virginia => 'Virginia',
            self::Washington => 'Washington',
            self::WestVirginia => 'West Virginia',
            self::Wisconsin => 'Wisconsin',
            self::Wyoming => 'Wyoming',
            self::AmericanSamoa => 'American Samoa',
            self::Guam => 'Guam',
            self::NorthernMarianaIslands => 'Northern Mariana Islands',
            self::PuertoRico => 'Puerto Rico',
            self::VirginIslands => 'U.S. Virgin Islands',
        };
    }
}
