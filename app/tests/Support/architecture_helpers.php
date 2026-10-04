<?php

declare(strict_types=1);

use App\Enums\LifecycleRung;
use App\Enums\Plan;
use App\Enums\PlatformHealthSignal;
use App\Livewire\Admin\PlatformSettings;
use App\Models\SiteChange;
use App\Models\User;
use App\Services\Actuation\SiteChanges;
use App\Services\Config\DefaultsRegistry;
use App\Services\Content\Publishing;
use App\Services\Places\GoogleLinkHosts;
use App\Support\Admin\OperatedElsewhere;
use App\Support\Campaigns\CampaignPackCatalog;
use App\Support\DefaultsManifest;
use App\Support\Messaging\LifecycleLadderCatalog;
use App\Support\Messaging\ReviewAskCatalog;
use App\Support\Support\SupportMacroCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_ as ArrayNode;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp as BinaryOpNode;
use PhpParser\Node\Expr\BinaryOp\Coalesce as CoalesceNode;
use PhpParser\Node\Expr\BinaryOp\Identical as IdenticalNode;
use PhpParser\Node\Expr\BinaryOp\NotIdentical as NotIdenticalNode;
use PhpParser\Node\Expr\Cast as CastNode;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_ as NewNode;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary as TernaryNode;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Float_ as FloatNode;
use PhpParser\Node\Scalar\Int_ as IntNode;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property as PropertyNode;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\SplFileInfo;

/*
|--------------------------------------------------------------------------
| Shared helpers for the architecture lints
|--------------------------------------------------------------------------
|
| Moved verbatim out of tests/Feature/ArchitectureTest.php when it was split
| by domain. They live here rather than in any one domain file because more
| than one domain reads them, and duplicating them per domain is how two
| copies of one rule drift apart. Required once from tests/Pest.php --
| tests/ is PSR-4 for classes, so plain functions need an explicit require.
|
*/

/**
 * Every string literal in a PHP file, keyed by line number.
 *
 * Includes single- and double-quoted strings and heredoc/nowdoc bodies, which is
 * where multi-line SQL actually lives. Interpolated strings arrive as fragments
 * around their expressions; that is fine here, because the operator and the
 * predicate that must accompany it both sit in the static text.
 *
 * @return array<int, string>
 */
function sqlStringLiterals(SplFileInfo $file): array
{
    $literals = [];

    foreach (token_get_all($file->getContents()) as $token) {
        if (is_string($token)) {
            continue;
        }

        if (! in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            continue;
        }

        $literals[$token[2]] = ($literals[$token[2]] ?? '').$token[1];
    }

    return $literals;
}
/**
 * CLAUDE.md's commercial-model table, in integer cents.
 *
 * Parses the live document rather than restating it, which is the whole point:
 * a copy here would drift from the copy in the manifest and the test would go on
 * passing. Decisions 428–433 set this pattern against SUBPROCESSOR-INVENTORY.md.
 *
 * The table has four rows and this reads three of them — Limited is skipped
 * because both of its cells read "not set", which is the state the neighbouring
 * assertion pins rather than a number to compare.
 *
 * ⚠️ A ROW IT CANNOT FIND FAILS RATHER THAN RETURNING NOTHING. A parser that
 * silently returns an empty array turns the assertion using it into a
 * comparison of nothing against nothing, which passes — decision 256's failure
 * mode, rebuilt inside the helper meant to prevent it.
 *
 * @return array<string, array{monthly: int, annual: int}>
 */
function commercialModelTable(): array
{
    $markdown = File::get(base_path('CLAUDE.md'));

    $rows = [
        // `| **Free** — the sensor without the actuator (154–156) | $0 | $0 |`
        'free' => '/^\|\s*\*\*Free\*\*[^|]*\|([^|]*)\|([^|]*)\|/m',
        // `| Base plan (1 location included) | $179.99 | $997 |`
        'base' => '/^\|\s*Base plan[^|]*\|([^|]*)\|([^|]*)\|/m',
        // `| Each additional location | $99.99 | **$499** |`
        'additional' => '/^\|\s*Each additional location[^|]*\|([^|]*)\|([^|]*)\|/m',
    ];

    $table = [];

    foreach ($rows as $name => $pattern) {
        if (preg_match($pattern, $markdown, $matches) !== 1) {
            throw new RuntimeException(
                "CLAUDE.md's commercial-model table has no `{$name}` row this test can read. "
                .'Either the table moved and this parser needs updating, or a price row was '
                .'deleted — and the second is the one worth stopping the build for.'
            );
        }

        $table[$name] = [
            'monthly' => dollarsToCents($matches[1], "{$name} monthly"),
            'annual' => dollarsToCents($matches[2], "{$name} annual"),
        ];
    }

    return $table;
}
/**
 * CLAUDE.md's founder-offer table, in integer cents (T176 R10, P1).
 *
 * {@see commercialModelTable()}'s pattern applied to the second price table,
 * and it is a **second table rather than two more rows in the first** for the
 * reason decision 2090 gives: an offer is not a plan, and a founder row inside
 * the tier table would be compared against `DefaultsManifest` by the assertion
 * that already reads it — which is exactly the "seed the founder rate as
 * `Plan::Base`'s price" mistake, arriving through the test instead of the code.
 *
 * ⚠️ THE ROW LABELS ARE CHOSEN SO THE OTHER PARSER CANNOT SEE THEM. `Base plan`
 * and `Each additional location` are anchored to the start of their cells over
 * there; `Founder base plan` and `Founder, each additional location` do not
 * match either, and would have silently supplied a second set of matches for the
 * retail comparison if they did — `preg_match` takes the first.
 *
 * Fails on a row it cannot find, for {@see commercialModelTable()}'s reason.
 *
 * @return array<string, array{monthly: int, annual: int}>
 */
function founderOfferTable(): array
{
    $markdown = File::get(base_path('CLAUDE.md'));

    $rows = [
        // `| Founder base plan (1 location included) | $99.99 | $499.99 |`
        'base' => '/^\|\s*Founder base plan[^|]*\|([^|]*)\|([^|]*)\|/m',
        // `| Founder, each additional location | $99.99 | $499.00 |`
        'additional' => '/^\|\s*Founder, each additional location[^|]*\|([^|]*)\|([^|]*)\|/m',
    ];

    $table = [];

    foreach ($rows as $name => $pattern) {
        if (preg_match($pattern, $markdown, $matches) !== 1) {
            throw new RuntimeException(
                "CLAUDE.md's founder-offer table has no `{$name}` row this test can read. "
                .'Either the table moved and this parser needs updating, or a founder price '
                .'row was deleted — and the second is the one worth stopping the build for, '
                .'because the offer would go on being charged with nothing stating it.'
            );
        }

        $table[$name] = [
            'monthly' => dollarsToCents($matches[1], "founder {$name} monthly"),
            'annual' => dollarsToCents($matches[2], "founder {$name} annual"),
        ];
    }

    return $table;
}
/**
 * A dollar figure from a markdown cell, as integer cents.
 *
 * Tolerates the bold markers and footnote text the table carries around its
 * numbers (`**$499**`), and refuses anything it cannot read rather than
 * returning zero — a price silently parsing to `0` would make the manifest
 * comparison pass against a free plan.
 */
function dollarsToCents(string $cell, string $label): int
{
    if (preg_match('/\$\s*([0-9]+(?:\.[0-9]{1,2})?)/', $cell, $matches) !== 1) {
        throw new RuntimeException(
            "No dollar figure in CLAUDE.md's `{$label}` cell: ".trim($cell)
        );
    }

    return (int) round(((float) $matches[1]) * 100);
}
/**
 * A whole-number quantity from a markdown cell — `1,000 messages` → 1000.
 *
 * Separate from {@see dollarsToCents()} because the credits table's third column
 * holds two different kinds of thing: a count of messages or emails, and a dollar
 * amount of AI credit. Reading a count with the dollar parser would silently
 * multiply it by a hundred, and reading a dollar figure with this one would
 * silently divide it — either way the comparison still passes against *something*,
 * which is the failure mode worth spending a second function on.
 *
 * Refuses a cell it cannot read, for {@see commercialModelTable()}'s reason.
 */
function markdownQuantity(string $cell, string $label): int
{
    if (preg_match('/\b([0-9][0-9,]*)\b/', $cell, $matches) !== 1) {
        throw new RuntimeException(
            "No quantity in CLAUDE.md's `{$label}` cell: ".trim($cell)
        );
    }

    return (int) str_replace(',', '', $matches[1]);
}
/**
 * CLAUDE.md's credits-and-allotment table, in integer cents and whole units.
 *
 * The second document-as-fixture parser in this file, added 2026-08-13 with the
 * credits model (3327), and it exists for a sharper reason than the first: **three
 * of the figures it reads contradicted themselves inside the owner's own reply**
 * (3308), and one of them — email metering — is a tenfold correction of what this
 * repository believed for months (3299). A figure with that history is not one to
 * re-read by eye once and trust.
 *
 * ⛔ **"THE AI MONTHLY GRANT IS DELIBERATELY NOT RETURNED" WAS TRUE FOR ONE DAY
 * AND WAS FALSE FOR TEN — CORRECTED 2026-08-24 (9220).** This paragraph read
 * *"its cell says 'not set', the manifest withholds it, and the assertion that
 * pins that pair is the withheld test rather than a comparison"*, and every
 * clause of it stopped being true on 2026-08-14: 3412 answered 3304, the cell
 * carried a figure, the manifest seeded one, and `RegistryTest` asserted the key
 * was **out** of `withheld()`. **The paragraph went on arguing from a state that
 * had ended, in the file a reader opens to find out whether the pair is
 * guarded.**
 *
 * ⛔ **AND THE GAP IT LEFT WAS MEASURED RATHER THAN INFERRED.** What stood in for
 * the comparison was a pin on the row's exact text. With the manifest seeding
 * `5_000` and this row saying **$40 of retail credit**, `RegistryTest` was green
 * at an unchanged assertion count — because a pin fixes the row's text *to
 * itself*, which is a different claim from the row agreeing with the manifest.
 * **The AI grant is parsed here now**, and what survives at the pin is the part a
 * number cannot carry: the words *of retail credit*.
 *
 * ⚠️ **IT NEEDS {@see dollarsToCents()} AND NOT {@see markdownQuantity()}**, for
 * this function's own reason two paragraphs down — the cell is a dollar figure
 * with prose after it, and reading it with the count parser would give `50` where
 * the seed is `5_000` and reading a count with the dollar parser would give
 * `50000` where the seed is `500`. Both comparisons still pass against
 * *something*.
 *
 * ⚠️ A row it cannot find throws rather than returning nothing — the same reason
 * {@see commercialModelTable()} does. A parser that silently drops a row turns
 * every assertion over that row into nothing compared with nothing, which passes.
 *
 * @return array{
 *     monthly_grant: array{sms: int, emails: int, ai_cents: int},
 *     topup: array<string, array{price_cents: int, grants: int}>,
 *     auto_topup_ceiling_cents: int,
 *     email_rate_cents_per_thousand: int,
 *     additional_phone_number_monthly_cents: int,
 * }
 */
function creditsModelTable(): array
{
    $cells = static function (string $label): array {
        $markdown = File::get(base_path('CLAUDE.md'));

        $pattern = '/^\|\s*'.preg_quote($label, '/').'\s*\|([^|]*)\|([^|]*)\|/m';

        if (preg_match($pattern, $markdown, $matches) !== 1) {
            throw new RuntimeException(
                "CLAUDE.md's credits-and-allotment table has no `{$label}` row this test can "
                .'read. Either the table moved and this parser needs updating, or a credit '
                .'line was deleted — and the second is the one worth stopping the build for.'
            );
        }

        return [$matches[1], $matches[2]];
    };

    $topUp = static function (string $label, bool $grantsDollars) use ($cells): array {
        [$price, $grants] = $cells($label);

        return [
            'price_cents' => dollarsToCents($price, "{$label} price"),
            'grants' => $grantsDollars
                ? dollarsToCents($grants, "{$label} grant")
                : markdownQuantity($grants, "{$label} grant"),
        ];
    };

    return [
        'monthly_grant' => [
            'sms' => markdownQuantity($cells('Monthly grant, SMS')[1], 'monthly SMS grant'),
            'emails' => markdownQuantity($cells('Monthly grant, email')[1], 'monthly email grant'),
            // ⛔ DOLLARS, NOT A COUNT — and the citation in the cell is part of
            // what the row has to carry, so the parser reads past it rather than
            // demanding a bare figure. 9180 moved this number ten days after 3412
            // settled what a dollar of it means; the row states both.
            'ai_cents' => dollarsToCents($cells('Monthly grant, AI credit')[1], 'monthly AI credit grant'),
        ],
        'topup' => [
            'sms.automatic' => $topUp('SMS top-up, automatic', false),
            'sms.manual' => $topUp('SMS top-up, manual', false),
            'email.automatic' => $topUp('Email top-up, automatic', false),
            'email.manual' => $topUp('Email top-up, manual', false),
            'ai.automatic' => $topUp('AI top-up, automatic', true),
            'ai.manual' => $topUp('AI top-up, manual', true),
        ],
        'auto_topup_ceiling_cents' => dollarsToCents(
            $cells('Auto-top-up ceiling, platform default')[0],
            'auto-top-up ceiling',
        ),
        'email_rate_cents_per_thousand' => dollarsToCents(
            $cells('Email metered rate')[0],
            'email metered rate',
        ),
        'additional_phone_number_monthly_cents' => dollarsToCents(
            $cells('Additional phone number')[0],
            'additional phone number',
        ),
    ];
}
/**
 * Every file the price-literal lint reads, keyed by repo-relative path.
 *
 * Views are in scope because the only violation that existed when the lint was
 * written was in one (decision 512), and `config/` is in scope because a price
 * in a config default is the same mistake wearing a different hat — that is
 * where the AI monthly cap lived until decision 506 moved it.
 *
 * `docs/` is deliberately out of scope. Those documents are the rationale, and
 * several of them are *required* to quote the superseded figures in order to
 * record that they are superseded.
 *
 * ⚠️ COMMENTS ARE STRIPPED FROM THE PHP, for the reason codeWithoutComments()
 * exists at all: the first run of this lint failed `Plan` and `PlanPricing` for
 * *documenting the prices they were built to stop hardcoding*. The tempting fix
 * is to delete the explanation, which is the wrong trade every time.
 *
 * Views are scanned raw. `token_get_all()` sees a Blade file as one lump of
 * inline HTML and strips nothing, so pretending otherwise would be a false
 * claim; and `{{-- --}}` comments carrying a price are not a shape this codebase
 * has, whereas `{{ $199.99 }}` in a template is precisely the one it had.
 *
 * @return array<string, string>
 */
function registryScannedFiles(): array
{
    $files = [];

    foreach (['app', 'config', 'database', 'resources/views', 'routes', 'tests'] as $directory) {
        $isViews = $directory === 'resources/views';

        foreach (File::allFiles(base_path($directory)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', $directory.'/'.$file->getRelativePathname());

            $files[$relative] = $isViews
                ? $file->getContents()
                : codeWithoutComments($file);
        }
    }

    return $files;
}
/**
 * A PHP file's source with comments removed.
 *
 * The text-matching checks in this file would otherwise match their own
 * documentation: a migration explaining *why* it avoids a database enum has to
 * name the thing it is avoiding, and gets failed for saying so. That is not
 * hypothetical — it is how the businesses migration first failed, and the
 * tempting fix is to stop writing the explanation, which is the wrong trade.
 *
 * It happens a second time in the NULLS-FIRST lint, and worse: ConsentService
 * documents the hazard three times, in the three places it was hit, so a lint
 * reading raw text would fail the one file that already gets this right — and
 * permitting that file would blind the lint exactly where the bug lives.
 *
 * Tokenizing rather than regex-stripping comments: a naive `//.*` also eats the
 * contents of strings, and these migrations contain SQL with `--` in it.
 */
function codeWithoutComments(SplFileInfo $file): string
{
    return phpWithoutComments($file->getContents());
}

/**
 * The same strip, over a fragment rather than a whole file.
 *
 * ⚠️ **A LINT THAT SLICES A METHOD BODY OUT WITH `ReflectionMethod` GETS RAW
 * SOURCE, COMMENTS AND ALL, AND THAT IS HOW ONE OF THEM WENT SOFT** (2015).
 * `OutboundTest`'s `canDeliver()` lint asserts `deliverNow()` still calls
 * `assertDeliverable()`; read raw, a comment inside that method *mentioning*
 * `assertDeliverable` satisfies it after the call itself is deleted — and the
 * one thing this codebase does with a load-bearing line is explain it in a
 * comment.
 *
 * ⛔ **THIS DOCBLOCK ENDED "EVERY ARM OF EVERY LINT READS STRIPPED SOURCE,
 * INCLUDING THE ONES THAT CANNOT GO THROUGH `codeWithoutComments()`", AND
 * THAT WAS A RULE NOTHING ENFORCED — CORRECTED 2026-08-23 (8695).** It is
 * 314–316's shape in the helper the rule is about: the paragraph asserting
 * the discipline is what stopped anybody checking whether it held. A census
 * on 2026-08-23 found **sixteen** raw text reads of `app/` source across ten
 * files in `tests/Feature/Architecture/`, twenty-two distinct subject/needle
 * pairs between them, and **one genuinely satisfied by a docblock**:
 * `MessagingTest`'s *"every key must actually appear in the one scorer"*
 * against `numbers.degraded_below_score`, which the scorer names three times
 * and reads never (8690). The other twenty-one hold today — so the rule is
 * right and the claim that it is universally applied was not.
 *
 * ⚠️ **A LINT FORBIDDING RAW `app_path()` READS IN ARCHITECTURE TESTS WAS
 * CONSIDERED AND REFUSED THIS WAVE** (8696): it would redden fifteen sound
 * sites across nine files at once, which is a wave rather than a lane, and
 * three of them (`ReviewsTest`'s three `$parser->parse()` calls) are correct
 * as they are because an AST discards comments anyway. **The census is in
 * that decision block**; whoever lands the general rule should start there
 * rather than re-deriving it.
 *
 * ⚠️ **AND THE INVERSE IS A REAL HAZARD, NOT A HYPOTHETICAL.** Two of the
 * sixteen are NEGATIVE arms — `CrmTest:680`'s
 * `not->toMatch('/Storage::disk\(\s*[\'"](?!s3)/')` and `ConsentTest:296`'s
 * `not->toContain('ConsentType::ExpressWritten')` — where reading raw is
 * *stricter*, and stripping would loosen them. They redden on a sentence
 * explaining why the forbidden thing is forbidden, which is a loud false red
 * rather than a silent green, so they are deliberately left alone (511's
 * error mirrored: a lint made strict enough to redden on prose).
 *
 * ⛔ **"ONE GENUINELY SATISFIED BY A DOCBLOCK" WAS THE 2026-08-23 READING AND
 * THERE ARE TWO — CORRECTED 2026-08-25 (9343).** `OneSourceTest`'s *every
 * render point binds the guarantee* asserts four `app/` files through
 * `assertStringContainsString` over `File::get()`, and **measured** rather than
 * read: `SupportMacros`' slot was moved off `guaranteeSentence()` onto a direct
 * registry read — a second source path, in the file whose subject is that the
 * guarantee has one home — with a two-line comment above it mentioning the old
 * call in passing, and the whole file stayed green at an unchanged 159
 * assertions. **No sibling caught it**: the *written in exactly one place* arm
 * looks for the sentence, and a wrong binding is not a pasted sentence.
 * ⚠️ **The census's conclusion is unchanged and its figure is not**, which is
 * why the figure is corrected here rather than deleted: the rule was right and
 * the count of instances was a floor, exactly as every other count in this
 * repository turns out to be.
 *
 * `token_get_all()` is a lexer, not a parser, so an unbalanced fragment
 * tokenizes fine — but it needs an open tag to treat the input as PHP at all,
 * which callers slicing a body must prepend.
 */
function phpWithoutComments(string $php): string
{
    $tokens = token_get_all($php);

    return implode('', array_map(
        fn (array|string $token): string => match (true) {
            is_string($token) => $token,
            in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) => '',
            default => $token[1],
        },
        $tokens,
    ));
}

/**
 * Whether $code contains a raw `DB::statement()`/`DB::unprepared()` call whose
 * SQL declares an enum — either inline (`ENUM(...)`) or as a named Postgres
 * type (`CREATE TYPE ... AS ENUM (...)`).
 *
 * Matches through `DB::connection(...)->statement(...)` as well as the
 * facade's own two static methods. That form is not hypothetical here: this
 * schema's migrations run as a separate owner role
 * (`DB::connection('pgsql_migrate')->statement(...)`, see
 * `tests/Concerns/RefreshesTenantDatabase.php`), and the literal substring
 * `DB::statement` never appears in it — a plain `(DB::statement|DB::unprepared)`
 * alternation misses it while catching every other raw spelling.
 *
 * ⚠️ THE CONNECTION ARGUMENT MAY ITSELF CONTAIN PARENS, so it is matched one
 * level deep rather than with `[^)]*`. `DB::connection(config('…'))->statement()`
 * and `DB::connection($this->connection())->unprepared()` both evaded the first
 * version of this helper — a hole in the exact case it was added to close, since
 * a computed connection name is the likeliest way to reach the owner role. The
 * quantifiers are possessive so that a file of unbalanced parens cannot make
 * this backtrack catastrophically.
 *
 * ⚠️ THE GAP IS BOUNDED, and that is not cosmetic. `.*?` under `/s` runs to the
 * end of the file, so a benign `DB::statement('… integer')` stitched to an
 * unrelated `ENUM(` forty lines later reported an offender that was not one.
 * 600 characters comfortably spans any single DDL string in this schema while
 * being far shorter than the distance between two unrelated statements — a lint
 * that cries wolf is one that gets tuned until it catches nothing (511).
 */
function rawStatementIntroducesEnumColumn(string $code): bool
{
    return preg_match(
        '/DB::(?:connection\s*\((?:[^()]++|\([^()]*+\))*+\)\s*->\s*)?(?:statement|unprepared)\s*\(.{0,600}?(CREATE\s+TYPE|ENUM\s*\()/is',
        $code,
    ) === 1;
}

/**
 * Which money-column rules a raw `DB::statement()`/`DB::unprepared()` breaks.
 *
 * ⛔ THE MONEY LINT SCANNED `Blueprint` CALLS ONLY, AND THE SCHEMA'S FIRST
 * `*_millicents` COLUMN IS DECLARED IN RAW SQL (3889). `2026_08_14_113356_…`
 * adds `cost_millicents` with `DB::statement('… ADD COLUMN …')`, because the
 * conversion has to be DDL rather than an `UPDATE` (3430) and Blueprint cannot
 * spell a generated column — so the one column the lint was extended for was the
 * one column it could not see, and the extension was proven only against a
 * hypothetical planted Blueprint migration. That is 256's shape, and the enum
 * lint had already been through it: `rawStatementIntroducesEnumColumn()` above
 * exists for the same reason and this mirrors its regex deliberately, including
 * the connection arm and the bounded gap.
 *
 * ⚠️ THE FRACTIONAL TYPE LIST IS POSTGRES' OWN SPELLINGS, NOT LARAVEL'S. The
 * Blueprint half refuses `decimal`/`float`/`double`; raw DDL says `numeric`,
 * `real`, `double precision`, `float8` and `money`, none of which that half has
 * any reason to know about. `money` is included because Postgres' own type of
 * that name is locale-dependent and rounds — the very thing `18` §Money handling
 * refuses.
 *
 * ⚠️ THE NAMING HALF IS ANCHORED TO A COLUMN POSITION rather than to a bare
 * word, so `ORDER BY cost DESC` and `WHERE amount > 0` are not offenders. What
 * it matches is a money-shaped name where a column is being *declared*: after
 * `ADD COLUMN`, or after the `(` or `,` of a `CREATE TABLE` list, and always
 * followed by a type. `cost_cents` and `price_cents` pass, because the word is
 * required to end there.
 *
 * @return list<string> One line per rule broken, empty when the code is clean.
 */
function rawStatementMoneyColumnOffences(string $code): array
{
    $call = 'DB::(?:connection\s*\((?:[^()]++|\([^()]*+\))*+\)\s*->\s*)?(?:statement|unprepared)\s*\(';
    $fractional = 'numeric|decimal|real|double\s+precision|float\d?|money';
    $moneyName = 'price|amount|cost|balance|subtotal|total_charged';

    $offences = [];

    if (preg_match('/'.$call.'.{0,600}?\b[a-z0-9_]*_(?:milli)?cents\s+(?:'.$fractional.')\b/is', $code) === 1) {
        $offences[] = 'raw SQL declares a *_cents or *_millicents column as a fractional type; minor units are integers';
    }

    if (preg_match(
        '/'.$call.'.{0,600}?(?:ADD\s+COLUMN\s+(?:IF\s+NOT\s+EXISTS\s+)?|[(,]\s*)(?:'.$moneyName.')\s+[a-z]/is',
        $code,
    ) === 1) {
        $offences[] = 'raw SQL declares a money column that is not named *_cents, so its unit is ambiguous';
    }

    return $offences;
}

/**
 * Whether this source opens an outbound socket through Laravel's HTTP client.
 *
 * ⛔ **EXTRACTED FROM THE LINT SO THE LINT CAN BE TESTED** (5550, 3895's move).
 * It lived inline in `OutboundTest`'s *"no outbound HTTP happens outside the
 * gateway"*, where it matched **nothing in `app/`** — the allowlist is complete,
 * so the lint proved the tree and proved nothing about itself. A companion test
 * drives every arm here, which is what stops the regex being narrowed until it
 * catches nothing (511).
 *
 * ⚠️ **THE THREE EXEMPTIONS ARE TEST-HARNESS STATICS**: they assert *about*
 * requests, they do not make them. Everything else on the client is treated as
 * an opened socket on purpose — an allowlist of verbs is a denylist wearing a
 * hat, and `asForm` and `timeout` were both missing from one until 413's
 * reasoning was applied to the lint itself.
 */
function opensOutboundSocket(string $source): bool
{
    if (preg_match_all('/\b(?i:Http::)([a-zA-Z]+)/', $source, $matches) === 0) {
        return false;
    }

    // ⚠️ CASE-INSENSITIVE, DELIBERATELY (10250), AND IN THE OPPOSITE DIRECTION
    // FROM THIS FILE'S OTHER FIXES: the capture above already matches any
    // case, so a real outbound verb is never missed regardless of spelling —
    // what a bare-case exemption list misses is the OTHER way, flagging a
    // differently-cased `Http::Fake()`/`Http::PreventStrayRequests()` test
    // double as a socket it never opens. Lower-casing before the diff is what
    // keeps the exemption meaning what it says.
    $verbs = array_map(strtolower(...), $matches[1]);

    return array_diff($verbs, ['fake', 'preventstrayrequests', 'assertnothingsent']) !== [];
}

/**
 * The files permitted to open an outbound connection.
 *
 * Shared by two lints, deliberately. The first decides *who* may open a socket;
 * the outbound-host lint decides *where* those files may connect. Two copies of
 * this list is how the second one quietly stops matching the first.
 */
function outboundHttpPermittedFiles(): array
{
    return [
        // The gateway itself, and the robots.txt lookup that gates it.
        'Services/Fetch/DirectFetchGateway.php',
        'Services/Fetch/RobotsPolicy.php',

        // Vendor JSON APIs. Each has a credential, a documented contract, and a
        // rate/cost model of its own; none of them fetch arbitrary pages.
        'Services/Providers/ProviderClient.php',
        'Services/Places/GooglePlacesClient.php',
        'Services/Oauth/GoogleTokenRefresher.php',
        'Services/Oauth/MetaTokenRefresher.php',
        'Services/Oauth/MicrosoftTokenRefresher.php',
        'Services/Oauth/MicrosoftSocialiteProvider.php',

        // The three AI providers (row 3 slice A0). On Laravel's HTTP client rather
        // than the official SDKs, and this list is one of the reasons why:
        // AppServiceProvider::forbidLiveVendorCallsInTests() notes that
        // Http::preventStrayRequests() "covers every call in app/Services because
        // they all go through Laravel's HTTP client; a client that built its own
        // Guzzle instance would slip past this". An SDK would be invisible to
        // both that guard and this lint — on the one vendor billing per token.
        'Services/Ai/AnthropicClient.php',
        'Services/Ai/OpenAiClient.php',
        'Services/Ai/XaiClient.php',
        // Google Gemini (2026-10-02, the boss: compare ChatGPT, Gemini, Claude and Grok on site design) — a fourth
        // documented vendor JSON API with its own credential, on the same footing as the three above.
        'Services/Ai/GeminiClient.php',
        // fal.ai (2026-10-02, the boss: low-cost FLUX pictures) — pictures only, returned inline (sync_mode), so the one
        // host is fal.run and no picture is fetched from anywhere else.
        'Services/Ai/FalImageClient.php',
        // Pixabay (2026-10-04, the boss's template brief: a stock-photo fallback when a business has too few photos of its own) —
        // a documented vendor JSON API with its own credential; searches pixabay.com/api and downloads only from pixabay.com or
        // cdn.pixabay.com, never any other host.
        'Services/Images/PixabayClient.php',

        // The embeddings client (lane L5 phase 1). A third file rather than a
        // widened second one, because Anthropic publishes no embeddings API and
        // there is nothing to pair it with.
        //
        // ⚠️ IT ADDS NO NEW HOST, WHICH IS WHY IT ADDS NO INVENTORY ROW. It
        // reaches `api.openai.com`, already scanned through the entry above and
        // already listed in `SUBPROCESSOR-INVENTORY.md`. Had it needed a fourth
        // vendor — Voyage AI is the one Anthropic's own documentation points at
        // for embeddings — this entry and an inventory row would have had to be
        // one change, and the slice would have stopped for the DPA.
        'Services/Ai/OpenAiEmbeddingClient.php',

        // owner ruling 2026-09-28 "yes, generate images with openAI"; image generation only; same host api.openai.com, so no inventory row.
        'Services/Ai/OpenAiImageClient.php',

        // Zernio — Google Business Profile, read through an intermediary while
        // our own GBP API application clears. A documented vendor JSON API with
        // a credential, like the entries above.
        //
        // ⚠️ Adding a file here also adds its hosts to the scanned set that both
        // subprocessor-inventory assertions compare against, which is why this
        // entry and the inventory row are one change. That coupling is the point:
        // the only way to reach a new vendor is to name it.
        'Services/Gbp/ZernioGbpClient.php',
        // Zernio (same host) — WhatsApp, H2 wave 839
        'Services/Zernio/ZernioWhatsappClient.php',
        'Services/Zernio/ZernioHttp.php', // Zernio (same host) — shared transport for social posting, H3

        // Stripe — payments (X-198). A documented vendor JSON API with a
        // credential and an idempotency key, like the entries above; it never
        // fetches a page. Listed because the outbound-socket lint names who may
        // open a socket, and a payment client that is not named is a payment
        // client the lint would have to be weakened for.
        'Modules/X-198/Domain/StripeGatewayClient.php',

        // Google Places key validation (X-206, SIXTY-40). One autocomplete POST
        // to places.googleapis.com with the CANDIDATE key — the same host
        // GooglePlacesClient reaches, sent outside that client on purpose: the
        // client resolves the stored key, and this call must not. A vendor JSON
        // API with a credential, never a page fetch.
        'Modules/X-206/Actions/PlacesKeyValidateAction.php',
        // IndexNow (row 9 slice E). One POST to the protocol's shared
        // endpoint, which the protocol itself requires: "You may submit your
        // request to only one of the following participating endpoints … your
        // submission will be shared across all IndexNow-enabled search engines"
        // (indexnow.org/faq, fetched 2026-08-20).
        //
        // ⚠️ SO THIS ONE ENTRY ADDS ONE HOST AND SIX COMPANIES, WHICH IS WHY THE
        // INVENTORY ROW BESIDE IT NAMES ALL OF THEM. Every other entry on this
        // list is one vendor; this is a consortium with a sharing rule, and a
        // row naming Bing alone would understate who receives the data.
        //
        // ⛔ NOTHING IN THIS BUILD REACHES IT. `IndexNowKeys` resolves to
        // `UnhostedIndexNowKeys`, which never returns a key, so
        // `Indexing::announce()` refuses before the client is called — decision
        // 5581's key-file constraint. The entry is here because the code exists
        // and could, which is the only question this list asks.
        'Services/Indexing/IndexNowSubmitter.php',

        // Google Search Console (row 15 slice 1). A documented vendor JSON API
        // read with the *tenant's* own OAuth grant out of the vault, like
        // ProviderClient's subclasses — it does not extend that base class only
        // because its failure classification is four-way and
        // ProviderRequestFailed reports two flags rather than four causes (see
        // SearchConsoleRequestFailed, whose own docblock records that the other
        // half of that reason — a reason lookup that could not read this API's
        // envelope at all — was fixed in the shared class on 2026-08-06).
        //
        // ⚠️ Adding a file here also adds its hosts to the scanned set that both
        // subprocessor-inventory assertions compare against, which is why this
        // entry and the inventory row are one change — the coupling is the point.
        // The host is `searchconsole.googleapis.com`, NOT `www.googleapis.com`:
        // the client's docblock records that every HTML reference page still
        // prints the legacy alias and the live discovery document does not.
        'Services/Gsc/GoogleSearchConsoleClient.php',

        // Cloudflare Turnstile (row 2 slice F). A documented vendor API with a
        // credential and a contract, like the entries above — it verifies a
        // challenge token and fetches no pages.
        //
        // ⚠️ It was absent from this list until 2026-08-03 and the lint did not
        // notice, because the list was of *verbs* and this file uses
        // `Http::asForm()`. It is the reason the check above matches `Http::`
        // and subtracts, rather than enumerating what is forbidden.
        'Services/TurnstileVerifier.php',

        // WordPress core's REST API on a *tenant's own website* (row 9 slice
        // F1). A documented JSON API with a credential and a contract, like the
        // entries above — and unlike every one of them in the way that matters.
        //
        // ⛔ **THE DESTINATION IS NOT A VENDOR AND IS NOT IN THIS FILE.** Every
        // other entry reaches a host from configuration or a literal; this one
        // reaches an address a *business owner typed into a form*, stored on
        // `locations.website_url` and `wordpress_credentials.rest_root`. So it
        // adds no host to the scanned set and needs no subprocessor row —
        // `SnsMessageVerifier`'s case, arrived at from the other direction: the
        // site being edited is the customer's own property, not a third party we
        // send their data to.
        //
        // ⚠️ **AND IT IS THE ONE ENTRY ON THIS LIST WITH AN SSRF SURFACE**,
        // for exactly that reason. `App\Support\PublicAddress` is checked
        // before a socket opens, https is required by the store's own CHECK
        // constraint, and redirects are not followed on any authenticated call —
        // a `301` off a tenant's site is a Basic Auth header handed to whatever
        // the redirect names.
        //
        // ⚠️ **IT IS DELIBERATELY NOT INSIDE `FetchGateway`.** `40` Part 8 puts
        // every *HTML* fetch there for robots, the method ceiling and the
        // attempt ledger. This is a JSON API a site owner authorised us to write
        // to; robots.txt has nothing to say about an authenticated PUT to your
        // own CMS, and inheriting the ladder would let a `Disallow: /` stop a
        // customer's own edits. The one probe that *is* an HTML fetch — reading
        // the `Link` header off the front page, which is WordPress's preferred
        // discovery method — is slice B's and lives in the gateway.
        'Services/Actuation/WordPress/WordPressRestClient.php',

        // Stripe (row 22 slice B). A documented vendor JSON API with a
        // credential, like the entries above — and on Laravel's HTTP client
        // rather than `stripe/stripe-php`, which is installed and available,
        // for the reason the AI-provider entry above states (decision 277,
        // applied at 680).
        //
        // ⚠️ THAT CHOICE IS WHAT KEEPS THIS LINT HONEST ABOUT STRIPE, AND
        // DECISION 438 PREDICTED THE OPPOSITE. Its note reads: "The day
        // Cashier's `Billable` trait lands, the right change to this document is
        // moving Stripe from §3 to §1. Making it turns the build red, because
        // `api.stripe.com` is then *named* and still not in the scanned set."
        // The prediction assumed the SDK, whose host literal lives in `vendor/`
        // where nothing here can read it. Building on `Http::` puts
        // `https://api.stripe.com/v1` in a file this scan opens, so moving the
        // inventory row is an ordinary edit and the build stays green. **The
        // trap was real and the remedy was choosing the client, not fixing the
        // scan.**
        'Services/Billing/StripeApi.php',

        // Authorize.Net — the second card gateway (2056, T137 R2/SL-11). Same
        // reasoning as the Stripe entry above and the same client, deliberately:
        // `authorizenet/authorizenet` exists and would have been the obvious
        // choice, and it bundles its own HTTP stack, which both
        // `Http::preventStrayRequests()` and this lint would be blind to. On the
        // gateway where a stray call in a test is a charge against a **live**
        // merchant account (2108 records the account as already live), that is
        // not a trade worth making twice.
        //
        // ⚠️ TWO HOSTS, NOT ONE, AND BOTH ARE NAMED IN THE INVENTORY. Stripe
        // distinguishes test from live by the key; Authorize.Net distinguishes
        // them by the **host** — `apitest.authorize.net` against
        // `api.authorize.net`. Listing only production would leave the sandbox
        // host reachable and unnamed, which is precisely the gap this list
        // exists to close.
        'Services/Billing/AuthorizeNetApi.php',

        // Infobip — SMS (row 4 slice 1). A documented vendor JSON API with a
        // credential, like the entries above, and on Laravel's HTTP client
        // rather than `infobip/infobip-api-php-client` for the reason the
        // AI-provider entry states — which `config/credentials.php` had already
        // written down before there was a client to instruct.
        //
        // ⚠️ THIS ENTRY ADDS NO HOST TO THE SCANNED SET, WHICH MAKES IT THE ONE
        // CASE THE COUPLING ABOVE DOES NOT COVER. Infobip issues a per-account
        // base URL, so there is no literal to read and none can be written —
        // `INFOBIP_BASE_URL` is exempt by name in the env-var assertion for
        // exactly that reason. The inventory row therefore has to move by hand,
        // and it did: Infobip left §3 for §2 in the same change that added this
        // line (1576). Nothing here can enforce that pairing, which is why it is
        // stated rather than assumed.
        'Services/Sms/InfobipClient.php',

        // Infobip Calls — the voice half of R7 (T176 P2). Same vendor, same
        // credential, same per-account base URL, and on Laravel's HTTP client
        // rather than `infobip/infobip-api-php-client` for the reason the
        // AI-provider entry states.
        //
        // ⛔ **ALL THREE OF ITS ENDPOINTS ARE A `GET`, AND THAT IS A RULE RATHER
        // THAN A COINCIDENCE.**
        //
        // ⛔ **THIS CITED `CLAUDE.md` FOR *"voice is web-only, no outbound
        // calling, ever"* UNTIL 2026-08-28 (11595), AND THAT AUTHORITY IS
        // RETIRED** — the lowercase, unquoted spelling an exact-phrase grep
        // cannot see, which is why a wave-43 census of fourteen such sites
        // reported twelve. `CLAUDE.md` now holds those words only inside the
        // correction that retires them: *"\"Voice is web-only\" is decision 28
        // and is NOT overridden"* — that is the support-bot widget, a different
        // subject. **The rule that governed outbound calling is `29` §2.3 rule
        // 13, *"No outbound AI calling, ever. TCPA governs calls you place"*,
        // and the owner OVERRODE it at 9363**: both kinds are to be built, with
        // AI disclosure and every number scrubbed. ⛔ **A ruling is not a build
        // (9366) and nothing has been built, so what refuses a call today is a
        // lint and not a sentence.** The refusal here was right throughout and
        // its stated reason was wrong — in the file a reader opens to find out
        // why this client only reads.
        //
        // Infobip's `POST /calls/1/calls` places a call and is one
        // line away in the same client; `tests/Feature/Architecture/VoiceTest.php`
        // fails the build on a mutating HTTP verb in any voice file —
        // `Services/Voice`, `Jobs/Voice`, `Listeners/Voice`,
        // `Http/Controllers/Voice` and `Contracts/VoiceProvider.php`, which is
        // `voiceFilesUnderTest()` below.
        //
        // ⚠️ **THIS LINE READ "ANY FILE THAT NAMES IT" WHILE THAT SCAN WAS ONE
        // DIRECTORY** (4513). The rule did hold — through this very permit list,
        // which is app-wide — and the sentence still overclaimed, which is what
        // stops the next reviewer looking.
        //
        // ⚠️ **AND THE TWO LINTS COMPOSE RATHER THAN OVERLAP** (4512). This list
        // is what forces a voice file that reaches Infobip to be named here and
        // to be written on `Http::`; `VoiceTest` then refuses the mutating verb
        // in exactly those files. **Neither is sufficient alone**: a file absent
        // from this list cannot reach a vendor, and a file on it cannot write to
        // one.
        //
        // ⚠️ **IT ADDS NO HOST TO THE SCANNED SET, WHICH IS THE ENTRY ABOVE'S
        // CASE EXACTLY** (1576) — the account's base URL is per-account, so
        // there is no literal to read. The inventory row for Infobip already
        // exists in §2 and this reaches no vendor that is not already named.
        'Services/Voice/InfobipVoiceProvider.php',

        // Fetching one inbound MMS picture (T176 P10). ⛔ **THE ONLY ENTRY ON
        // THIS LIST WHOSE URL IS CHOSEN BY SOMEBODY OUTSIDE THIS COMPANY** —
        // `SnsMessageVerifier`'s shape reached from the other direction. Its
        // `SigningCertURL` comes from a vendor's own message; this comes out of
        // a webhook body whose *content* a customer's carrier composed. So the
        // guards are stricter than anything else here: an `https`-only hostname
        // allowlist checked before a socket opens, a private-address rejection
        // on top of it, redirects not followed **at all**, a byte ceiling
        // enforced on the stream rather than on a `Content-Length` we were
        // handed, and a content type checked against the file's own magic bytes.
        //
        // ⚠️ **IT ADDS NO HOST TO THE SCANNED SET, WHICH IS INFOBIP'S CASE
        // EXACTLY** (1576). The account's base URL is per-account, so there is
        // no literal to read and none can be written; the media allowlist is
        // env-driven with an **empty default**, so `config/services.php` gains
        // no host literal either. Infobip's inventory row already exists in §2
        // and nothing here reaches a vendor that is not already named — so the
        // coupling this list normally provides does not apply, and that is
        // stated rather than assumed.
        'Services/Sms/InboundMediaFetcher.php',

        // The platform's own Google Workspace mailbox (SL-4, decision 2093). A
        // documented vendor JSON API with a credential, like the entries above,
        // and on Laravel's HTTP client rather than `google/apiclient` for the
        // AI-provider entry's reason — an SDK opens its own sockets and is
        // invisible to both this lint and `Http::preventStrayRequests()`, on the
        // path that sends mail to members of the public.
        //
        // ⚠️ **THIS ENTRY ADDS `gmail.googleapis.com` TO THE SCANNED SET AND
        // THAT IS THE COUPLING WORKING** — `config/platform_mail.php` holds the
        // endpoint literal, so the inventory row and this line are one change.
        // The token endpoint it also reaches, `oauth2.googleapis.com`, was
        // already named for the tenant-side Google refresh; it is the *platform's
        // own* grant here and a tenant's there, which 2072 is explicit must not
        // be conflated.
        'Services/Mail/GmailApiClient.php',

        // Reading that same mailbox for replies (T137 §3 rail 3). The inbound
        // half of the entry above, on the same grant and the same host, and a
        // separate file because it is a separate scope: `gmail.send` authorises
        // none of `users.watch`, `users.history.list` or `users.messages.get`,
        // and the narrowest scope that does is `gmail.metadata` — headers, never
        // bodies (verified against Google's reference, 2026-08-12).
        //
        // ⚠️ **IT ADDS NO NEW HOST**, which is what makes it a line here rather
        // than an inventory row of its own: `gmail.googleapis.com` is already
        // scanned through `GmailApiClient` and already named. What it *does*
        // change is the direction of the data, so the existing §2 row was
        // rewritten rather than left describing outbound mail alone.
        'Services/Mail/GmailInbox.php',

        // The goaiez **support** mailbox (T176 P24). Third file on the same
        // grant shape and the same host, and a separate file for a reason the
        // two above do not share: it is a **different Google account**.
        //
        // ⛔ **THE SCOPE IS WIDER AND THAT IS THE WHOLE POINT.** The relay holds
        // `gmail.metadata`, under which Google refuses `format=full` outright —
        // which is what makes `GmailInbox`'s claim that reply *bodies* are
        // unreadable a fact about the credential rather than a promise about
        // our code. A support ticket with no body is not a support ticket, so
        // this reads `gmail.readonly` — and it must therefore be a **second
        // account**, because adding the scope to the existing grant would
        // silently falsify that claim for the relay too.
        //
        // ⚠️ **IT ADDS NO NEW HOST**, on `GmailInbox`'s own reasoning:
        // `gmail.googleapis.com` is already scanned and already named, so this
        // is a line here rather than an inventory row of its own.
        //
        // ⚠️ **NOTHING IN CODE CAN ENFORCE THE TWO-ACCOUNT SEPARATION** — one
        // refresh token pasted into both config fields would defeat it and look
        // identical from here. What is enforced is narrower and worth having:
        // `SupportMailbox::isEnabled()` refuses to run when the two mailbox
        // names match, because two consumers sharing one cursor row lose mail
        // silently.
        'Services/Support/SupportMailbox.php',

        // Google's published signing keys for a Cloud Pub/Sub push token
        // (T137 §3 rail 3). ⚠️ **THE THIRD WEBHOOK VERIFIER AND THE THIRD
        // AUTHENTICATION SHAPE**: Infobip HMACs the body, SNS signs a field list
        // and fetches a certificate the message names, and Pub/Sub signs nothing
        // in the request at all — it attaches an OIDC JWT, and verifying it
        // needs Google's JWKS.
        //
        // ⚠️ **UNLIKE `SnsMessageVerifier`, THE URL IS CONFIGURATION AND NOT
        // FROM THE MESSAGE**, so there is nothing to constrain and a literal to
        // scan: `www.googleapis.com` from the discovery document's `jwks_uri`.
        // That host is already in §1 for sign-in, which names `/oauth2/v3/certs`
        // on it by that exact path — the same keys, reached for the same reason.
        'Services/Mail/GooglePushTokenVerifier.php',

        // Amazon SNS's signing certificate (SL-4, open question H). ⚠️ **THE ONE
        // ENTRY ON THIS LIST WHOSE DESTINATION ARRIVES IN A REQUEST BODY RATHER
        // THAN FROM CONFIGURATION**, which is why it adds no host to the scanned
        // set and why the inventory row for it has to move by hand — Infobip's
        // case, arrived at from the other direction. `SnsMessageVerifier`
        // constrains the URL with `parse_url()` against a host prefix and suffix
        // before fetching anything, because an unconstrained `SigningCertURL` is
        // the whole attack on that endpoint.
        'Services/Mail/SnsMessageVerifier.php',

        // The other half of the SNS webhook: completing a subscription that
        // `SesFeedbackController` deliberately refuses to complete for itself
        // (10220-10229). ⚠️ **A SECOND FILE ON THIS LIST REACHING ONE HOST THAT
        // IS ALREADY EXEMPTED IN `OutboundTest`, AND IT ADDS NO HOST LITERAL AND
        // NO INVENTORY ROW.** The URL is AWS's own, signed inside the message
        // being verified and held in a store until an operator acts, so there is
        // nothing to write down here either -- which is the same reason
        // `sns.<region>.amazonaws.com` is unscannable. What bounds it is the
        // host pattern `SnsMessageVerifier` already constrains `SigningCertURL`
        // with, read from config rather than re-typed, plus a refusal to follow
        // redirects.
        'Services/Mail/SnsSubscriptions.php',

        // The Google short-link resolver (row 2 slice C). Exempt deliberately,
        // and `BUILD-PLAN` §2.5.4 says why: "The resolver deliberately sits
        // outside the gateway: `24` §1.2.2 fetches a `Location` header and never
        // a page body, so it is not an HTML fetch and must not inherit robots or
        // ladder semantics."
        //
        // Inheriting them would be actively wrong. `google` is seeded
        // guided_only precisely so we never fetch Google *pages* — but a short
        // link is a redirect, which is the mechanism the link exists to provide,
        // and a robots disallow on goo.gl would stop an owner telling us which
        // business is theirs. The constraints it does carry are stricter than
        // the gateway's: an allowlist checked before any socket, three hops, and
        // a private-address rejection on every one.
        'Services/Places/ShortLinkResolver.php',

        // Owner ruling 2026-09-28 (main REVIEWS): outbound webhooks to a tenant's own
        // https URL, guarded by PublicAddressGuard, pinned, no redirects.
        'Services/Webhooks/TenantWebhookClient.php',
    ];
}
/**
 * The config basenames {@see outboundScannedConfigFiles()} does not scan, and why.
 *
 * ⚠️ **EXTRACTED SO IT CAN BE ASKED A QUESTION** (8258). It was a local variable
 * inside the scan, which meant the only assertion any test could make about it
 * was the one it already makes — that `database.php` is not scanned. Nothing
 * could ask whether an excluded name still refers to anything.
 *
 * @return list<string>
 */
function outboundExcludedConfigFiles(): array
{
    return [
        // Transport, set per deployment rather than in code: DB, Redis, SMTP,
        // the object store, the log sinks. The inventory's Infrastructure table
        // is where a per-deployment relationship belongs.
        //
        // ⚠️ AND THIS EXCLUSION IS A REAL SILENCE FOR R2 AND ACS. An earlier
        // version of this comment said §3 forbade their hosts "so their absence
        // here is not a silence", which is untrue in the only way that matters:
        // assertion 3 compares §3 against the *scanned* set, and R2 arrives
        // through AWS_ENDPOINT in filesystems.php and ACS through mail.php —
        // both excluded right here. A literal `*.r2.cloudflarestorage.com` or
        // `*.communication.azure.com` written into either file evades it
        // entirely. Decision 434 says so; the comment contradicted the decision,
        // which is 314-316's pattern — a protection layer asserted before it is
        // true — inside the comment written to prevent it. **For these two
        // vendors §3 is documentation, not enforcement**, and closing it needs
        // the vendor-package scan of decision 438 rather than un-excluding a
        // file whose every other line names a host that is not a subprocessor.
        'cache',
        'database',
        'filesystems',
        'logging',
        'mail',
        'queue',
        'session',

        // Our own address and our own guards. APP_URL is not a destination —
        // we are not our own subprocessor — and auth.php names no host at all.
        'app',
        'auth',

        // First-party package configuration: authentication behaviour, queue
        // dashboard, API-token guards. None of the three reaches a vendor.
        'fortify',
        'horizon',
        'sanctum',
    ];
}

/**
 * The config files scanned for outbound destinations.
 *
 * INVERTED ON PURPOSE: every file in `config/`, minus a named exclusion list.
 * It was a hard-coded list of six — oauth, services, ai, places, credentials,
 * fetch — written in two places, and decision 429's lesson is that an allowlist
 * of verbs is a denylist wearing a hat. A file list is one too. This repository
 * adds one config file per subsystem: `ai.php`, `places.php`, `fetch.php` and
 * `credentials.php` all arrived that way in the last three slices, and row 4's
 * `config/messaging.php` would have carried an Infobip endpoint past all four
 * inventory assertions without anybody editing this file. Adding a config file
 * is now scanned by default; excluding one is a visible, reviewable act.
 *
 * Shared by two assertions for the same reason `outboundHttpPermittedFiles()`
 * is — two copies of a list is how the second one quietly stops matching the
 * first, which is what this list was before today.
 *
 * ⚠️ THE EXCLUSIONS ARE NOT "FILES WITHOUT VENDORS". Three of them contain host
 * literals right now, and each would be a false positive of a different kind:
 * `app.php` and `filesystems.php` carry our own APP_URL, `mail.php` derives the
 * EHLO domain from it, and `queue.php` ships Laravel's stock SQS placeholder
 * `https://sqs.us-east-1.amazonaws.com/your-account-id` for a driver we do not
 * use. Naming AWS SQS in §1 would be §0's over-claiming produced by the lint
 * built to prevent it, and naming ourselves would be the same mistake
 * `outboundScannedHosts()` already corrects by subtracting goaiez.com.
 *
 * The list names only files that exist today. A stock file that arrives later
 * — `broadcasting.php`, `cors.php`, `view.php` — is scanned until somebody
 * excludes it with a reason, which is the failure direction we want.
 *
 * ⚠️ **AND "NAMES ONLY FILES THAT EXIST TODAY" IS NOW ASSERTED RATHER THAN
 * STATED** (8258) — `OutboundTest`'s *every excluded config file is a config
 * file that still exists*. It was a claim in a comment about a list the reader
 * had no way to check, which is the sentence this codebase keeps finding stale.
 *
 * @return list<string>
 */
function outboundScannedConfigFiles(): array
{
    $excluded = outboundExcludedConfigFiles();

    $files = [];

    foreach (File::files(config_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        if (in_array($file->getFilenameWithoutExtension(), $excluded, true)) {
            continue;
        }

        $files[] = $file->getPathname();
    }

    sort($files);

    return $files;
}
/**
 * The traits that mark a model as tenant-owned.
 *
 * Two, not one: ordinary tenant-owned models carry a `business_id` foreign key,
 * while the tenant root is scoped on its own primary key and cannot fill that key
 * on create. Both are equally subject to row-level security, so both must be
 * discoverable here — a model marked with either and missing its RLS policy is a
 * build failure.
 *
 * @return list<string>
 */
function tenancyTraits(): array
{
    return [
        'App\\Concerns\\BelongsToTenant',
        'App\\Concerns\\IsTenantRoot',
    ];
}
/**
 * Every host a piece of PHP source could open a connection to.
 *
 * THREE RULES, EACH ONE EARNED.
 *
 * Comments are stripped first. These files are dense with vendor documentation
 * links — developers.google.com, learn.microsoft.com, support.google.com — and
 * not one of them is a destination.
 *
 * The match is anchored on a scheme. Tested against the permitted files, a bare
 * dotted-word pattern returns `places.timeout`, `usage.input`,
 * `choices.0.message.refusal` and `robots.txt`.
 *
 * parse_url() finishes the job rather than a regex capture, which is decision
 * 223's rule, and it runs on the URL exactly as matched — no pre-truncation.
 * parse_url() already stops the host at the first `/`, so config/oauth.php's
 * `login.microsoftonline.com/{tenant}/…` parses correctly with no help: a
 * placeholder in the *path* was never a problem. A placeholder in the *host*
 * — a future `https://{$region}.example.com/v1` — is: parse_url() then
 * returns a garbage-but-present host rather than nothing. That is deliberate.
 *
 * ⚠️ WHICH ASSERTION CATCHES IT, STATED EXACTLY, BECAUSE THIS SENTENCE WAS
 * WRITTEN BEFORE ANY OF THEM EXISTED. Not "the inventory assertions" — one of
 * them. A garbage host is a host the document does not name, so 'every host the
 * code can reach is named in the subprocessor inventory' fails and prints it;
 * the other three are unmoved. Verified 2026-08-03 by interpolating a host into
 * a scanned file: exactly that one assertion went red, with the fragment
 * `{$request-` in the diff. Loud, and obviously not a hostname, which is the
 * whole point — a destination that fails the build is one somebody
 * investigates, while silently dropping it (which truncating on `{` first would
 * do, because the truncated string has no host left to parse) is one nobody
 * knows about.
 *
 * @return list<string>
 */
function outboundHostsInCode(string $php): array
{
    $source = '';

    foreach (token_get_all($php) as $token) {
        if (is_string($token)) {
            $source .= $token;

            continue;
        }

        if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $source .= $token[1];
    }

    preg_match_all('~https?://[^\s\'"`<>\\\\]+~i', $source, $matches);

    $hosts = [];

    foreach ($matches[0] as $url) {
        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            $hosts[strtolower($host)] = true;
        }
    }

    $hosts = array_keys($hosts);
    sort($hosts);

    return $hosts;
}
/**
 * The scanned set: every host reachable from code.
 *
 * THREE GROUPS, EACH ARGUED. The file lint decides who may open a socket; this
 * decides where they may connect, so the first group is that lint's own list —
 * a file that cannot connect cannot send, and its host strings are not this
 * lint's business. That is why GoogleReviewLink's `google.com` and
 * DestinationSettings' `trustpilot.com` are absent: those are URLs handed to the
 * customer's *browser*, and they are exactly the strings that look like a
 * subprocessor and are not.
 *
 * The second group is one constant. ShortLinkResolver is permitted to connect
 * and holds zero host literals — its destinations live in GoogleLinkHosts::
 * SHORT_LINK, one file away (decision 430). Scanning its values rather than
 * hoping the file's text is spelled with a scheme is the more honest form:
 * GoogleLinkHosts.php's host strings are bare, carry no `https://`, and the
 * scheme anchor above would exclude them either way.
 *
 * The third is our own config, where the real endpoints are — every file in
 * `config/` except a named exclusion list, for the reasons
 * outboundScannedConfigFiles() gives.
 *
 * ⚠️ THREE SILENCES, STATED SO THEY ARE NOT MISTAKEN FOR THE LINT WORKING.
 *
 * DirectFetchGateway contributes zero hosts and always will — it fetches
 * arbitrary tenant websites, so there is nothing to enumerate, and §5 of the
 * inventory argues it is outbound-only and not a subprocessor.
 *
 * ⚠️ OAuth *scope* URIs are opaque identifiers, not destinations, and decision
 * 432 said their hosts "coincide with real endpoints today so no carve-out is
 * needed". That was already false when written. `www.googleapis.com` appears in
 * config/oauth.php ONLY as the `business.manage` scope string: Google's token
 * and revocation endpoints are oauth2.googleapis.com and GBP is
 * mybusinessaccountmanagement.googleapis.com. So the scanned set carried a host
 * no first-party client of ours contacts, and assertion 1 forced a §2 row
 * describing a flow that does not exist. `graph.microsoft.com` genuinely does
 * coincide — it is `graph_endpoint` as well as a scope prefix — which is what
 * made the claim read as true. The row survives for a reason it never gave:
 * Socialite's own GoogleProvider contacts www.googleapis.com on every sign-in.
 * Decision 435 records it; **a claim accidentally true by a mechanism other
 * than the one stated is decision 384's shape**, and it is why the next scope
 * naming a host we never call would still be a false positive.
 *
 * ⚠️ A vendor package's own sockets are invisible here, and that is the largest
 * gap. This scans our files; Socialite, Cashier, the S3 driver and the mail
 * transports all open connections from inside `vendor/`. Decision 434 and the
 * inventory's §6 both say so.
 *
 * @return list<string>
 */
function outboundScannedHosts(): array
{
    $sources = [];

    foreach (outboundHttpPermittedFiles() as $relative) {
        $sources[] = app_path($relative);
    }

    foreach (outboundScannedConfigFiles() as $path) {
        $sources[] = $path;
    }

    $hosts = [];

    foreach ($sources as $path) {
        foreach (outboundHostsInCode(File::get($path)) as $host) {
            $hosts[$host] = true;
        }
    }

    foreach (GoogleLinkHosts::SHORT_LINK as $host) {
        $hosts[$host] = true;
    }

    // We are not our own subprocessor. RobotsPolicy carries goaiez.com inside
    // the User-Agent string — a promise to webmasters that we identify
    // ourselves, not a destination.
    //
    // ⚠️ `t.goaiez.com` IS THE SAME SHAPE, ON A SUBDOMAIN RATHER THAN THE BARE
    // NAME, SO THE EXACT MATCH ABOVE DOES NOT ALREADY COVER IT. It comes from
    // `config('pixel.install_script_url')` — GOAIEZ_PIXEL_MASTER_BUILD.md §10's
    // own Install block — and it is never fetched by our backend: it is
    // rendered into a tenant's install snippet for a stranger's *browser* to
    // load, `outboundScannedHosts()`'s own group-1 argument above this
    // function ("URLs handed to the customer's browser… look like a
    // subprocessor and are not") applied to a config value rather than a code
    // literal.
    unset($hosts['goaiez.com'], $hosts['goaiez.ai'], $hosts['t.goaiez.com']);

    $hosts = array_keys($hosts);
    sort($hosts);

    return $hosts;
}
/**
 * The hosts named in one numbered section of the subprocessor inventory.
 *
 * Reads the live document and delegates to hostsInMarkdownSection(), which
 * takes the markdown as a string so it can be exercised against a synthetic
 * fixture in a test without touching the filesystem.
 *
 * @return list<string>
 */
function inventoryHostsInSection(int $section): array
{
    return hostsInMarkdownSection(File::get(base_path('docs/SUBPROCESSOR-INVENTORY.md')), $section);
}
/**
 * The hosts named in one numbered section of a subprocessor-inventory-shaped
 * markdown document.
 *
 * Backticked, dotted, and not a filename. The tables also backtick class names
 * (`AiRouter`), column names (`platform_settings`) and method calls
 * (`Storage::disk`) — none of which have the shape of a host — and file paths,
 * which do, so extensions are excluded explicitly.
 *
 * A leading `*.` is preserved and means a suffix match. §3 needs it: R2 and
 * Azure Communication Services both address a per-account subdomain, so no
 * exact host can be named.
 *
 * THE SECTION BOUNDARY. `^## N\..*?$(.*?)(?=^## |\z)` correctly treats a `###`
 * subheading as still inside the section — §1's own `### Infrastructure` is
 * where `care.gomdusa.net` lives — because the lookahead only stops at a line
 * starting `## ` (two hashes, one space), never three. It has a known,
 * un-fixed limit pinned by 'a fenced code block cannot smuggle a `## ` line
 * past the section boundary' below: a fenced code block containing a line
 * starting `## ` truncates the section early, because the regex has no
 * concept of a code fence. Not fixed in this pass — pinned honestly instead of
 * left as a silent gap for Tasks 6 and 7 to inherit.
 *
 * @return list<string>
 */
function hostsInMarkdownSection(string $markdown, int $section): array
{
    if (! preg_match('/^## '.$section.'\..*?$(.*?)(?=^## |\z)/ms', $markdown, $match)) {
        throw new RuntimeException("Section {$section} is missing from the document.");
    }

    preg_match_all('/`(\*\.)?((?:[a-z0-9-]+\.)+[a-z]{2,})`/i', $match[1], $found, PREG_SET_ORDER);

    $extensions = ['md', 'php', 'txt', 'json', 'yml', 'yaml', 'js', 'css', 'blade'];

    $hosts = [];

    foreach ($found as $one) {
        $host = strtolower($one[2]);

        if (in_array(pathinfo($host, PATHINFO_EXTENSION), $extensions, true)) {
            continue;
        }

        $hosts[($one[1] === '' ? '' : '*.').$host] = true;
    }

    $hosts = array_keys($hosts);
    sort($hosts);

    return $hosts;
}
/**
 * Tables backing models that are tenant-owned.
 *
 * Returns an empty array until the traits and models exist, so this file was
 * committable at Stage 0 before any model was written.
 *
 * @return list<string>
 */
function tenantOwnedTables(): array
{
    $modelPath = app_path('Models');

    if (! File::isDirectory($modelPath)) {
        return [];
    }

    $tables = collect(File::allFiles($modelPath))
        ->map(function (SplFileInfo $file): ?string {
            $class = 'App\\Models\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            );

            // is_a() with $allow_string narrows the type to class-string<Model>,
            // so getTable() below resolves against Model rather than object.
            return is_a($class, Model::class, true) ? $class : null;
        })
        ->filter()
        ->filter(fn (string $class): bool => array_intersect(
            tenancyTraits(),
            class_uses_recursive($class),
        ) !== [])
        ->map(fn (string $class): string => (new $class)->getTable())
        ->unique()
        ->sort()
        ->all();

    return array_values($tables);
}
/**
 * `39`'s thirteen draft sections, keyed by the `doc_type` in each heading.
 *
 * Each is `## DOC n — TITLE (doc_type: `slug`)` followed by its body and
 * terminated by the horizontal rule separating it from the next. The `doc_type`
 * is read out of the heading rather than inferred from the order, so a
 * reordered document still compares correctly and a renamed slug fails loudly.
 *
 * @return array<string, string>
 */
function legalDraftSectionsIn(string $markdown): array
{
    preg_match_all(
        '/^## DOC \d+ — .*?\(doc_type: `([a-z-]+)`\)\n(.*?)(?=\n---\n)/ms',
        str_replace("\r\n", "\n", $markdown),
        $matches,
        PREG_SET_ORDER,
    );

    $sections = [];

    foreach ($matches as $match) {
        $sections[$match[1]] = trim($match[2]);
    }

    return $sections;
}
/**
 * R53's draft sections, keyed by the `L-n` label in each heading.
 *
 * ⚠️ **A SECOND PARSER RATHER THAN A WIDER REGEX, BECAUSE THE TWO PACKS INDEX
 * DIFFERENTLY.** `39` writes the `doc_type` into every heading and terminates
 * each section with a horizontal rule; R53 heads its sections `## L-1 · TITLE`,
 * never writes a `doc_type` at all, and separates them with nothing but the next
 * heading. One regex covering both would have to make the `doc_type` group
 * optional, and an optional capture that silently misses is how a lint starts
 * comparing an empty string against a real document and passing (256).
 *
 * The lookahead stops at the next `## ` heading or at the end of the string, so
 * the last section before R53's fix log is bounded correctly and the final one
 * in a file with no trailing heading is not truncated.
 *
 * @return array<string, string>
 */
function r53LegalDraftSectionsIn(string $markdown): array
{
    preg_match_all(
        '/^## (L-\d+) · .*?\n(.*?)(?=\n## |\z)/ms',
        str_replace("\r\n", "\n", $markdown),
        $matches,
        PREG_SET_ORDER,
    );

    $sections = [];

    foreach ($matches as $match) {
        $sections[$match[1]] = trim($match[2]);
    }

    return $sections;
}
/**
 * Every textual shape a write to one column takes, as regexes.
 *
 * ⚠️ **ONE DEFINITION, BECAUSE THE LINT AND ITS FALSIFICATION TEST BOTH NEED
 * IT** (1610). The `region_code` chokepoint shipped with the lint holding its
 * pattern as a literal in `tests/Feature/Architecture/CrmTest.php` and the test
 * that proves the pattern catches a planted violation holding a *second copy* of
 * the same literal in `tests/Feature/CustomerRegionTest.php`. Neutering the real
 * lint therefore left both green — the falsification test was falsifying a
 * regex nobody ran. Both call this now, so the proof is about the shipped rule.
 *
 * ⚠️ **THE ASSIGNMENT ARM ALONE WAS EVADED BY SIX OF SEVEN REALISTIC SHAPES.**
 * The lint as shipped matched `->region_code =` and nothing else, so
 * `Customer::create([…])`, `->update([…])`, `fill()`, `forceFill()`,
 * `setAttribute()`, a raw `DB::table()->update([…])` and `->{'region_code'} =`
 * all passed it. The four arms below are the answer, and the mass-assignment one
 * is what catches the raw query builder, which no `$guarded` list can.
 *
 * ⚠️ **THREE MORE ARMS, AND TWO OF THEM CLOSE HOLES THE FOUR LEFT OPEN** (1621).
 * `$customer['region_code'] = 'FL'` evaded every one of the four **and**
 * `$guarded`, because `Model::offsetSet()` calls `setAttribute()` directly and
 * never asks `isFillable()` — so the one shape both layers missed was the same
 * shape. Raw SQL evaded both layers too, and `database/` was added to the scan
 * (1610) *specifically* for the backfill case that `DB::statement("UPDATE
 * customers SET region_code = …")` is the natural spelling of. The array-setter
 * arm exists because arm 3 is dropped per file for `timezone`, and where it is
 * dropped `->fill(['timezone' => …])` had nothing left to catch it.
 *
 * ⚠️ **`setRawAttributes` WAS IN THE SCALAR ARM AND COULD NEVER FIRE** (1621).
 * That method takes an array, so the character after `(` is always `[` and the
 * `['"]` the arm requires never matched. It was rescued by arm 3 everywhere
 * except the files arm 3 is dropped in — which is precisely where an arm is
 * worth having. Scalar setters and array setters are two arms now.
 *
 * ⚠️ **IT IS STILL A TEXTUAL LINT AND STILL HAS THE SIBLINGS' BLIND SPOTS.** A
 * variable holding the column name, reflection, a column list built by
 * concatenation, `Model::unguarded()`, and any SQL that names the column
 * somewhere other than immediately after `SET` (a parameter binding, a
 * multi-column `SET a = ?, region_code = ?` where the arm does match, but an
 * `UPDATE … SET (a, region_code) = …` where it does not) are all out of reach,
 * and `CLAUDE.md` already records raw `DB::statement` as the escape hatch from
 * the sibling database-enum lint for the same reason.
 *
 * ⛔ **AND THE SECOND LAYER IS NOT WHAT THIS DOCBLOCK SAID IT WAS — CORRECTED
 * 2026-08-29 (11830).** The sentence above ended *"— which is exactly why the
 * columns these guard are *also* in `$guarded`. Two layers for two different
 * evasions; neither is claimed to be complete on its own"*, and that is the
 * over-claim `CLAUDE.md` §Critical rules →Engineering measured out at 9140-9143
 * and `ConsentTest` already carries: **`$guarded` refuses `fill()` and nothing
 * else.** Six of these eight arms evade it outright and two evade it in part, so
 * for most of this list the arms are not a belt beside a brace, they are the
 * whole of it. **The superseded sentence is kept and dated** on 4368's rule -
 * reassurance in the file a reader opens to check is what stops the check.
 *
 * ## ⛔ THE ARMS ARE POSITIONS, AND EVERY ARM OWES AN ISOLATION CASE (11830)
 *
 * {@see writesColumn()} selects arms by integer index and six chokepoints pass
 * the literal `[0, 1, 2, 4, 5, 6, 7]` meaning *every arm but the bare payload
 * key*, so inserting or deleting an arm re-points all six in silence. Until
 * 2026-08-29 **arm 0 could be neutered with every one of the nine consuming
 * test files green**, and arms 5 and 7 were held only by two indexed assertions
 * somebody had added by hand. `columnWriteArmIsolations()`, in
 * `tests/Feature/CustomerRegionTest.php`, now binds every index to a shape and
 * asks of each independent arm whether the set **without** it still matches -
 * so adding, removing or narrowing an arm here means declaring it there, and a
 * deleted arm reddens as a missing arm rather than as four innocent files
 * accused of writing `customers.tags`.
 *
 * @return list<string>
 */
function columnWriteShapes(string $column): array
{
    $name = preg_quote($column, '/');

    return [
        // 0. $model->region_code = … and the ??= Pint is free to rewrite it into.
        '/->\s*'.$name.'\s*(?:\?\?)?=(?!=)/',
        // 1. $model->{'region_code'} = … — the same write spelled to dodge arm 0.
        '/->\s*\{\s*[\'"]'.$name.'[\'"]\s*\}\s*(?:\?\?)?=(?!=)/',
        // 2. The scalar attribute setters, which reach the bag without passing
        //    $guarded. `offsetSet` is spelled out here as well as in arm 4
        //    because somebody may call it by name.
        '/->\s*(?:setAttribute|offsetSet)\s*\(\s*[\'"]'.$name.'[\'"]/',
        // 3. 'region_code' => … as a create/update/fill key or a DB payload.
        '/[\'"]'.$name.'[\'"]\s*=>/',
        // 4. $customer['region_code'] = … — ArrayAccess, which lands in
        //    setAttribute() and is therefore invisible to $guarded too.
        '/\[\s*[\'"]'.$name.'[\'"]\s*\]\s*(?:\?\?)?=(?!=)/',
        // 5. The array setters. Narrower than arm 3 on purpose: it exists for
        //    the files arm 3 is dropped in, where the column name collides with
        //    something that is not this column.
        '/->\s*(?:setRawAttributes|forceFill|fill)\s*\(\s*\[[^\]]*[\'"]'.$name.'[\'"]\s*=>/',
        // 6. Raw SQL. `DB::statement("UPDATE customers SET region_code = …")`
        //    reads as data cleanup and is the likeliest place a jurisdiction is
        //    derived; both migrations that touch this column already use
        //    `DB::statement`, so the escape hatch is not hypothetical.
        '/\bSET\s+'.$name.'\s*=(?!=)/i',
        // 7. Arm 0 narrowed to a receiver that is not `$this` (1621). It exists
        //    for one file: a Livewire component holding a public property of the
        //    same name has to drop arm 0, and dropping it left
        //    `$location->timezone = $this->timezone;` — a second writer sitting
        //    *beside* the service call rather than replacing it — invisible to
        //    the lint and green under the test that was named as covering it.
        '/(?<!\$this)->\s*'.$name.'\s*(?:\?\?)?=(?!=)/',
    ];
}

/**
 * Whether any write shape for $column appears in this source.
 *
 * @param  list<int>|null  $shapes  indexes into columnWriteShapes(), or null for all
 */
function writesColumn(string $source, string $column, ?array $shapes = null): bool
{
    foreach (columnWriteShapes($column) as $index => $pattern) {
        if ($shapes !== null && ! in_array($index, $shapes, true)) {
            continue;
        }

        if (preg_match($pattern, $source) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * Which calls receive a `'$column' => …` key in this source, named.
 *
 * ## ⛔ WHY THIS EXISTS: ARM 3 CANNOT TELL A WRITE FROM THE RECORD OF ONE
 *
 * {@see columnWriteShapes()}'s arm 3 is the bare `'x' =>` key, and that is the
 * shape of an audit payload, a change-set snapshot and a model's `casts()` array
 * as well as of a `create()`/`update()`/`forceFill()` payload. An anti-vacuity
 * floor built on it is therefore satisfied by the *record* of a write and stays
 * green on the day the writer is deleted (9102) — the vacuity it exists to
 * refuse, one level up.
 *
 * ⛔ **AND "DROP ARM 3" IS NOT THE ANSWER, WHICH IS THE FINDING THIS HELPER
 * CARRIES** (9152). Arm 3 is the **only** arm that sees a write spelled
 * `Model::query()->update([...])` or `::create([...])` — arm 5 covers
 * `fill`/`forceFill`/`setRawAttributes` and nothing else, and its `[^\]]*` also
 * loses a `forceFill([...] + [...])` payload the moment the first array closes.
 * Both permitted writers of `locations.website_scanned_at` are in exactly that
 * state: `SiteProbe` writes through `update([...])` and `LocationWebsite`
 * through a `+`-concatenated `forceFill`, so a floor there with arm 3 dropped
 * **can never be satisfied by a correct application.**
 *
 * So the property is not an arm index. It is: **the floor must not be
 * satisfiable by a spelling in this file that is not the write.** Where the
 * writer takes an arm besides 3, dropping arm 3 delivers that and is the
 * cheaper instrument. Where arm 3 is all the writer takes, this names the sink
 * instead — an audit payload's sink is `recordChange`, a `casts()` array's is
 * no call at all, and neither can be mistaken for `update`.
 *
 * ## ⚠️ IT IS NOT A WRITE LINT AND MUST NEVER BE USED AS ONE
 *
 * It answers one question — *what receives this key* — and it is deliberately
 * blind to every non-key write shape, because those are the ones arm 3 was
 * never needed for. The forward chokepoints stay on {@see writesColumn()}, so a
 * ninth arm added there is still asked by every lint. Using this to *find*
 * offenders would blockade `update()` calls in files that spell the column by
 * assignment and miss every file that does not use a payload at all.
 *
 * ## ⚠️ ITS BLIND SPOT RUNS THE SAFE WAY
 *
 * A writer that moves from `->update(['x' => …])` to `$model->x = …` makes this
 * report a different sink, and the floor reading it goes **red** on a change
 * that was legitimate. That is the direction to fail in: the remedy is to state
 * the new sink here, where the next reader can see what the write now is,
 * rather than to discover months later that a floor stopped meaning anything.
 * ⚠️ **A dynamic call name (`$this->{$verb}(...)`) reports `(dynamic call)`**
 * rather than being dropped, so a payload that has become unreadable is visible
 * instead of absent.
 *
 * @param  string  $php  source, comments already stripped by the caller's scanner
 * @return list<string> sorted, unique; `(not a call argument)` for a key in an
 *                      array that is nobody's argument — a `casts()` return, a
 *                      `$before = [...]` snapshot, a property default
 */
function columnPayloadSinks(string $php, string $column): array
{
    $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($php);

    if ($ast === null) {
        return [];
    }

    $traverser = new NodeTraverser;
    $traverser->addVisitor(new ParentConnectingVisitor);
    $ast = $traverser->traverse($ast);

    $sinks = [];

    foreach ((new NodeFinder)->findInstanceOf($ast, ArrayItem::class) as $item) {
        if (! $item->key instanceof String_ || $item->key->value !== $column) {
            continue;
        }

        $sinks[] = payloadSinkOf($item);
    }

    $sinks = array_values(array_unique($sinks));

    sort($sinks);

    return $sinks;
}

/**
 * The name of the call whose argument list this array item ends up inside.
 *
 * ⚠️ **THE WALK STOPS AT THE FIRST STATEMENT**, which is what keeps a
 * `return [...]`, a `$before = [...]` and a property default out of every
 * caller's answer: an array that is nobody's argument is reported as such
 * rather than being attributed to whatever call happens to enclose the
 * statement. ⚠️ **It does NOT stop at an intervening expression**, which is the
 * `forceFill([...] + [...])` case — the item's array is an operand of a `+`
 * before it is an argument, and that payload is a write.
 */
function payloadSinkOf(Node $node): string
{
    $current = $node;

    while (($parent = $current->getAttribute('parent')) instanceof Node) {
        if ($parent instanceof Arg) {
            $call = $parent->getAttribute('parent');

            return match (true) {
                $call instanceof MethodCall,
                $call instanceof NullsafeMethodCall,
                $call instanceof StaticCall => $call->name instanceof Identifier
                    ? $call->name->toString()
                    : '(dynamic call)',
                $call instanceof FuncCall => $call->name instanceof Name
                    ? $call->name->toString()
                    : '(dynamic call)',
                $call instanceof NewNode => $call->class instanceof Name
                    ? 'new '.$call->class->toString()
                    : 'new (dynamic class)',
                default => '(dynamic call)',
            };
        }

        if ($parent instanceof Stmt) {
            return '(not a call argument)';
        }

        $current = $parent;
    }

    return '(not a call argument)';
}

/**
 * Every file that may name the support desk's three tables (T137 `SL-7`).
 *
 * Relative to `base_path()` rather than to `app/`, because the lint walks
 * {@see chokepointScannedFiles()} — 1610's hole — and the creating migration is
 * legitimately in `database/`.
 *
 * @return list<string>
 */
function supportStoreAllowlist(): array
{
    return [
        'app/Services/Support/SupportDesk.php',
        'app/Models/SupportTicket.php',
        'app/Models/SupportMessage.php',
        'app/Models/SupportQueueEntry.php',
        'database/migrations/2026_08_12_065625_create_support_desk_tables.php',
    ];
}

/**
 * What counts as touching the support desk's store.
 *
 * Four shapes, and the third and fourth are the ones a regex over class names
 * alone would miss:
 *
 *   - a static call on `SupportTicket` that is **not** `::class` — a policy
 *     check and a type hint are how the model legitimately appears in a screen
 *     that never queries it (`Business::(?!class\b)`'s rule);
 *   - `SupportMessage` or `SupportQueueEntry` in any spelling, which nothing
 *     outside the desk has a reason to name at all. ⚠️ The word boundary is what
 *     keeps `SupportMessageAuthor`, `SupportThreadMessage` and
 *     `InboundSupportMessage` out of it — three legitimate names that all
 *     contain one of these;
 *   - `new SupportTicket`, because `(new SupportTicket)->save()` is a write that
 *     names no static call;
 *   - an **aliased** import, which is the hole `MailTest`'s chokepoint closes by
 *     watching the import clause: `use … SupportTicket as T;` then `T::query()`;
 *   - the table names themselves, which is what a raw `DB::table()` would use.
 */
function supportStorePattern(): string
{
    // ⚠️ CASE-INSENSITIVE, DELIBERATELY (10250): every clause but the table
    // names is a class name, a static call, a construction or a namespaced
    // import — all PHP-identifier dispatch.
    return '/\bSupportTicket::(?!class\b)'
        .'|\bnew\s+SupportTicket\b'
        .'|\bSupportMessage\b'
        .'|\bSupportQueueEntry\b'
        .'|use\s+App\\\\Models\\\\Support(?:Ticket|Message|QueueEntry)\s+as\s'
        .'|\bsupport_tickets\b'
        .'|\bsupport_messages\b'
        .'|\bsupport_queue_entries\b/i';
}

/**
 * The files a chokepoint lint walks: the application, plus the two places a
 * backfill actually gets written.
 *
 * ⚠️ **`app_path()` ALONE WAS THE HOLE** (1610). Every chokepoint lint in this
 * codebase scanned `app/` only, so a one-off backfill in a migration, a seeder
 * or `routes/console.php` evaded all of them — and a backfill is the single most
 * likely place somebody derives a jurisdiction from an area code, because it is
 * the diff that reads as data cleanup rather than as a compliance change.
 *
 * `tests/` is deliberately not walked: a test that pins the *gate* rather than
 * the writer sets the column directly and should, and factories construct
 * unguarded.
 *
 * ⛔ **THIS CLAUSE READ "WHICH IS WHY THOSE COLUMNS ARE IN `$guarded`" UNTIL
 * 2026-08-24 AND THAT REASON IS FALSE FOR AT LEAST ONE OF THEM (9088-9091).**
 * `Review::$guarded` is `['id', 'business_id']` and names neither `source` nor
 * `google_review_id` nor `display_on_website`. **The conclusion survives and
 * the reason does not**: what makes not walking `tests/` right is that a test
 * pinning a gate must set the column, not a second layer that turns out to be
 * absent. ⚠️ **A reason offered for a correct decision is still load-bearing**,
 * because it is what the next reader checks instead of the decision.
 *
 * @return array<string, string> relative path => source with comments stripped
 */
function chokepointScannedFiles(): array
{
    $files = [];

    foreach (['app', 'database', 'routes'] as $directory) {
        foreach (File::allFiles(base_path($directory)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', $directory.'/'.$file->getRelativePathname());

            $files[$relative] = codeWithoutComments($file);
        }
    }

    return $files;
}

/**
 * Every entry in a column chokepoint's arm-exemption map that has stopped
 * excusing anything, for the map's own subject.
 *
 * ## ⛔ WHY IT IS ONE FUNCTION AND NOT THE SIXTH HAND-COPIED `foreach`
 *
 * 9025 wrote this guard inline in `StaffTest`, over `usersRoleArmExemptions()`
 * alone, and its own docblock said out loud what it could not reach: *"IT IS A
 * MAP RATHER THAN A PREDICATE SO THAT IT CAN BE WALKED … neither sibling
 * `$arms` map has that guard."* **That sentence is what hid two of them.** The
 * exemptions in `ActuationTest` and `LocationDetailsTest` were *predicates* —
 * exactly the shape the sentence rules out — so they contain no `$arms` token
 * and a sweep for one finds neither. A guard written once for every map is the
 * same trade {@see exemptedPathOffences()} already made for the path half of
 * this problem (8253), and it is the reason a sixth mechanism inherits the
 * guard by being passed here rather than by being guarded twice.
 *
 * ## ⛔ THE MAP IS KEYED BY FILE **AND COLUMN**, WHICH IS THE FINDING
 *
 * An arm exemption is a written argument that **one named file may spell one
 * named column one named way**. Keyed by file alone it says *"may spell any of
 * these columns"*, and then a single live spelling keeps every other column's
 * excusal alive for ever. That was not hypothetical: `Location`'s excusal from
 * arm 3 covered ten file/column pairs and **three of them had never been
 * earned** — `website_url`, `about_url` and `primary_phone` are string columns
 * with no `casts()` entry, so nothing in `Location.php` has ever spelled them
 * `'website_url' =>`, and the arm was dropped over a shape the file does not
 * take. A file-keyed guard passes on all ten, because the seven cast columns
 * carry the file. **So the granularity of the map is the granularity of the
 * guard**, and anything coarser is a decoration on the pairs it cannot see.
 *
 * ## ⚠️ WHAT IT ASKS, IN THE ORDER IT ASKS IT
 *
 * The exempted path is still scanned; the entry names at least one column; the
 * complement is not empty (an entry listing every arm is the same as no entry,
 * and it reads on a diff as a considered narrowing); and the file still spells
 * that column one of the excused ways. **Existence first, and the order is the
 * message** (8461) — asking the last question of a file that is gone reports a
 * missing spelling in a file nobody can open.
 *
 * ## ⚠️ WHAT IT DELIBERATELY DOES NOT ASK
 *
 * Whether the file takes an arm that still **applies**. That is the forward
 * lint's to report, and reporting it here in a second vocabulary is how a
 * reader ends up debugging the wrong instrument.
 *
 * ⚠️ **IT REACHES THE PATTERNS THROUGH {@see writesColumn()} AND THE ARM COUNT
 * THROUGH {@see columnWriteShapes()}**, never a re-typed copy of either — 8460's
 * rule, which holds even while both copies are correct. A ninth arm added there
 * is asked by every lint and by this guard, or by neither.
 *
 * @param  array<string, array<string, list<int>>>  $exemptions  path => column => the arms that still apply
 * @param  string  $subject  what the map is called where it is read, for the failure line
 * @return list<string> one line per exemption that protects nothing, empty when every entry is earned
 */
function columnArmExemptionOffences(array $exemptions, string $subject): array
{
    $scanned = chokepointScannedFiles();
    $offences = [];

    foreach ($exemptions as $relative => $columns) {
        if (! array_key_exists($relative, $scanned)) {
            $offences[] = "{$relative} has a {$subject} arm exemption and is not scanned by the lint "
                .'at all - it has been deleted, renamed, or moved outside app/, database/ and routes/.';

            continue;
        }

        if ($columns === []) {
            $offences[] = "{$relative} has a {$subject} arm exemption that names no column, so it "
                .'excuses nothing and reads as a considered narrowing.';

            continue;
        }

        foreach ($columns as $column => $applies) {
            $excused = array_values(array_diff(range(0, count(columnWriteShapes($column)) - 1), $applies));

            if ($excused === []) {
                $offences[] = "{$relative} has a {$subject} arm exemption for {$column} that excuses "
                    .'no arm at all. An entry listing every arm is the same as no entry, and it reads '
                    .'on a diff as a considered narrowing.';

                continue;
            }

            if (! writesColumn($scanned[$relative], $column, $excused)) {
                $offences[] = "{$relative} has a {$subject} arm exemption and no longer spells "
                    ."{$column} any of the excused ways, so the exemption protects nothing. Delete "
                    .'that column from the entry - a dropped arm is a written argument that this file '
                    .'may spell this column that one way, and it should not outlive the spelling.';
            }
        }
    }

    sort($offences);

    return $offences;
}

/**
 * Which routes to `message_cost_entries` a file takes that only
 * `MessageCostLedger` may take.
 *
 * ⛔ **EXTRACTED FROM THE LINT SO THE LINT CAN BE TESTED** (3895). The four arms
 * lived inline in `BillingTest`'s *"only one service touches the internal cost
 * ledger"*, where they matched **nothing in `app/` at all** — the schema is
 * clean, so the lint proved the schema and proved nothing about itself. That is
 * 256's shape and it had already bitten this same slice once, at 3889: an arm
 * added to catch `DB::table(...)` matched only `->table(` and let the facade
 * spelling — the exact route the review named first — walk through, and nothing
 * said a word until a planted line failed to redden it. A companion test drives
 * every arm here.
 *
 * ⚠️ **THE `::class` SPELLING STAYS LEGAL AS A TYPE HINT**, which is what the
 * container and relationship arms distinguish: it is not the constant that is
 * dangerous, it is handing it to something that returns rows.
 *
 * ⚠️ **THE IMPORT ARM IS WHY THE OTHERS CANNOT BE ALIASED AROUND** (3896).
 * Every other arm is anchored to the literal token `MessageCostEntry`, so
 * `use App\Models\MessageCostEntry as CostRow;` followed by
 * `CostRow::query()->sum('cost_millicents')` evaded all of them. Refusing the
 * import outside the allowlist closes that without a new spelling to chase:
 * `MessageCostLedger` is the only file in `app/` that imports the model, so
 * nothing legitimate is caught.
 *
 * ⚠️ **THE TABLE-NAME ARM REQUIRES SQL CONTEXT, NOT JUST THE WORD** (3897).
 * `\b(?:FROM|INTO|JOIN)\s+message_cost_entries\b` on its own matches English
 * prose inside a string literal — `codeWithoutComments()` strips comments and
 * not strings — and `DefaultsManifest` explains this very table to an operator
 * in three descriptions. Failing the build on *naming* the table would make the
 * only available fix "stop explaining it", which is 511's failure inside the arm
 * whose own comment cites 511. So `FROM`/`INTO`/`JOIN` must be preceded by a
 * statement verb within a bounded gap; `UPDATE message_cost_entries` needs no
 * companion because the verb and the keyword are the same word.
 *
 * @return list<string> One line per route taken, empty when the file is clean.
 */
function costBookChokepointOffences(string $code, string $relative = 'a file'): array
{
    return modelChokepointOffences('MessageCostEntry', 'message_cost_entries', $code, $relative);
}

/**
 * Which routes to `voice_usage_events` a file takes that only `VoiceSpend` may
 * take (4686).
 *
 * ⛔ **WRITTEN BECAUSE THE CLAIM WAS WRITTEN** (314–316). `VoiceUsageEvent`'s
 * docblock and the creating migration both say *"only `VoiceSpend` may touch
 * this model"*, and a described chokepoint is not a chokepoint — 4682 records
 * the same sentence about `SendDriver` and found the hole by planting a probe.
 *
 * ⚠️ **THE REACH THIS REFUSES IS NOT A MARGIN LEAK, IT IS A TENANCY ONE**, which
 * is what makes it different from its cost-book sibling. `voice_usage_events`
 * carries no global scope and no row-level security **by design**, because a
 * ceiling that cannot see the rows with no tenant on them is not a ceiling. So
 * every read of it is a cross-tenant read by construction, and the explicit
 * `business_id` predicate inside `VoiceSpend` is the *whole* of the isolation. A
 * `$business->voiceUsage` relationship or one `DB::table('voice_usage_events')`
 * on a screen is one missing `where` away from showing one tenant another's call
 * volume — with nothing beneath to catch it.
 *
 * @return list<string> One line per route taken, empty when the file is clean.
 */
function voiceMeterChokepointOffences(string $code, string $relative = 'a file'): array
{
    return modelChokepointOffences('VoiceUsageEvent', 'voice_usage_events', $code, $relative);
}

/**
 * Which routes to `gbp_grant_revocation_attempts` a file takes that only
 * `GbpConnections` may take (5071).
 *
 * ⛔ **WRITTEN BECAUSE THE CLAIM WAS WRITTEN, FOR THE THIRD TIME IN THIS FILE**
 * (314–316). The model's docblock, the creating migration and — the one that
 * matters — `TenancyTest`'s own allowlist entry all say *"`GbpConnections::
 * attemptRevocation()` as the only writer"*. That sentence is the compensating
 * control a table with **no tenant scope and no row-level security beneath it**
 * rests on, and until this function existed it was prose. A described
 * chokepoint is not a chokepoint.
 *
 * ⚠️ **WHAT A SECOND WRITER WOULD COST.** Every row here is the only surviving
 * evidence that a subprocessor was asked to give up read-and-write access to a
 * deleted customer's Google listing, and who asked. A second writer could
 * record an outcome the vendor never gave — most plausibly a screen "marking it
 * resolved" — and nothing in the schema would disagree, because the log has no
 * foreign key to the binding it describes (5062) and no policy to fail closed
 * on.
 *
 * @return list<string> One line per route taken, empty when the file is clean.
 */
function grantRevocationLogChokepointOffences(string $code, string $relative = 'a file'): array
{
    return modelChokepointOffences(
        'GbpGrantRevocationAttempt',
        'gbp_grant_revocation_attempts',
        $code,
        $relative,
    );
}

/**
 * Which routes to `site_changes` a file takes that only `SiteChanges` may take
 * (5529).
 *
 * ⛔ **WRITTEN BECAUSE THE CLAIM WAS WRITTEN, FOR THE FOURTH TIME IN THIS FILE**
 * (314–316). {@see SiteChange}'s docblock, the creating migration
 * and {@see SiteChanges} itself all say that one
 * service is the only writer. A described chokepoint is not a chokepoint.
 *
 * ⚠️ **WHAT A SECOND WRITER WOULD COST IS LARGER HERE THAN AT ANY OF THE THREE
 * ABOVE.** `29` §2 rule 32 — *every site change snapshots its prior state and is
 * reversible* — is enforced by `SiteChanges::open()` refusing an empty
 * `before_snapshot`, and by nothing else. The column is `NOT NULL`, which `{}`
 * satisfies. So a job, a screen or a second service creating a row directly
 * would produce a change to **somebody else's website** with no prior state
 * recorded, and the first anybody would hear of it is an owner asking for it to
 * be put back.
 *
 * ⚠️ **THE SERVICE'S OWN NAME IS ONE CHARACTER FROM THE MODEL'S AND THE ARMS
 * SURVIVE IT.** Every arm in {@see modelChokepointOffences()} is anchored on
 * `\b`, so `SiteChanges::open()`, `new SiteChanges(` and
 * `use App\Services\Actuation\SiteChanges;` match none of them — which the
 * companion guard test drives explicitly rather than leaving to inspection.
 *
 * @return list<string> One line per route taken, empty when the file is clean.
 */
function siteChangeChokepointOffences(string $code, string $relative = 'a file'): array
{
    return modelChokepointOffences('SiteChange', 'site_changes', $code, $relative);
}

/**
 * Every route to `growth_pages` that is not `Services\Content\GrowthPages`.
 *
 * ⛔ **WHAT THE CHOKEPOINT PROTECTS IS ONE PAIRING**: `status` and `hold_until`
 * together decide whether text this platform wrote appears on somebody else's
 * website. A held page with no release time waits for a person; one with a
 * release time is AUTO-WITH-HOLD and publishes on silence. `GrowthPages::holdUntil()`
 * is the only thing that refuses to put the second on a page the quality gate
 * turned down — and a second writer is a second place that refusal is skipped,
 * with the two rows indistinguishable afterwards.
 *
 * ⚠️ **THE SERVICE'S NAME IS THE MODEL'S PLUS ONE LETTER**, and so are five other
 * symbols in this slice — `GrowthPageStatus`, `GrowthPageType`,
 * `GrowthPageDraft`, `GrowthPageFactory`, `GrowthPageRefused`. Every arm in
 * {@see modelChokepointOffences()} is anchored on `\b`, and the companion guard
 * test drives all six rather than leaving it to inspection: a lint that reddened
 * on its own permitted writer would be widened until it caught nothing (511).
 *
 * @return list<string> One line per route taken, empty when the file is clean.
 */
function growthPageChokepointOffences(string $code, string $relative = 'a file'): array
{
    return modelChokepointOffences('GrowthPage', 'growth_pages', $code, $relative);
}

/**
 * Every route to `content_quality_checks` that is not
 * `Services\Content\ContentQuality`.
 *
 * ⛔ **THE TABLE HAD ZERO WRITERS FROM STAGE 0 UNTIL 2026-08-19** — 272's shape,
 * a green isolation suite over a table nothing filled in — so the chokepoint
 * lands with the *first* writer rather than after the second. What it protects
 * is decision 347's three-state verdict: `passed` true, false and null mean
 * three different things, a CHECK constraint keeps them apart at the database,
 * and a second writer is where a moderation refusal would quietly become a
 * content failure.
 *
 * @return list<string> One line per route taken, empty when the file is clean.
 */
function contentQualityCheckChokepointOffences(string $code, string $relative = 'a file'): array
{
    return modelChokepointOffences('ContentQualityCheck', 'content_quality_checks', $code, $relative);
}

/**
 * Every route to `indexing_submissions` that is not `Services\Indexing\Indexing`.
 *
 * ⛔ **THE TABLE HAD ZERO WRITERS FROM STAGE 0 UNTIL 2026-08-19** — 272's shape,
 * an isolation suite passing perfectly over a table nothing filled in — so the
 * chokepoint lands with the *first* writer rather than after the second, on
 * `contentQualityCheckChokepointOffences()`'s reasoning one slice earlier.
 *
 * ⛔ **WHAT IT PROTECTS IS THE TRIPLE `status`, `reason`, `submitted_at`.** A
 * refusal is *ours* and a rejection is the search engine's; `submitted_at` is
 * non-null only when a request was actually taken. `Indexing::record()` is the
 * one place that pairing is enforced — the database can only hold the half of it
 * that does not require naming status strings in SQL — and a second writer is
 * where a page we declined to announce becomes a page a search engine turned
 * down, indistinguishably, for ever. On this deployment almost every row is the
 * first kind, which is exactly why it matters.
 *
 * ⚠️ **THE READER IS THE SAME FILE AND THAT IS DELIBERATE.** The offence arms
 * catch an import and a relationship, not just a write, so a separate report
 * service would need its own permit entry — and a chokepoint with two entries is
 * an allowlist.
 *
 * @return list<string> One line per route taken, empty when the file is clean.
 */
function indexingSubmissionChokepointOffences(string $code, string $relative = 'a file'): array
{
    return modelChokepointOffences('IndexingSubmission', 'indexing_submissions', $code, $relative);
}

/**
 * Every route to `response_templates` that is not `Services\Reviews\ResponseTemplates`.
 *
 * ⛔ **THIS TABLE WAS READ ON EVERY REPLY DRAFT AND WRITTEN BY NOTHING FROM
 * STAGE 0 UNTIL 2026-08-21** (1732) — 272's shape in its most dangerous form,
 * because the *consumer* was built, tested and green. `ReplyGenerator::draft()`
 * has queried it since GBP-03 shipped and the only writer in the repository was
 * the factory, so every tenant's few-shot block rendered *"(no curated
 * examples)"* and no suite anywhere had a reason to complain. The chokepoint
 * therefore lands with the **first** writer, on
 * {@see contentQualityCheckChokepointOffences()}'s reasoning: there has never
 * been a moment at which a second writer would have looked unusual.
 *
 * ⛔ **WHAT IT PROTECTS IS THAT A BODY IS AN UNTRUSTED PROMPT INPUT.** Three of
 * these are interpolated into the call whose output publishes under the
 * business's name on their public Google listing.
 * `ResponseTemplates::add()` is the one place a body is asked
 * `ReplyGuardrails::allows()` before it is stored, and a second writer is where
 * an import or an admin screen would store one without asking — invisibly, since
 * the consequence is that the drafter falls silently back to the safe template
 * for ever.
 *
 * ⚠️ **THE READER IS THE SAME FILE AND THAT IS DELIBERATE**, exactly as it is
 * for `indexing_submissions`: the arms catch an import and a relationship rather
 * than only a write, so `ReplyGenerator` asks `examples()` and the owner's panel
 * asks `forOwner()`. A permit list of two is an allowlist.
 *
 * ⚠️ **THE MODEL AND ITS SERVICE DIFFER BY ONE TRAILING LETTER**, so every arm
 * of {@see modelChokepointOffences()} is load-bearing here in a way it is not
 * for `GrowthPage`/`GrowthPages`: `ResponseTemplates` contains
 * `ResponseTemplate` as a prefix. The guard test beside this lint drives twelve
 * legal spellings that all start with the model's name.
 *
 * @return list<string> One line per route taken, empty when the file is clean.
 */
function responseTemplateChokepointOffences(string $code, string $relative = 'a file'): array
{
    return modelChokepointOffences('ResponseTemplate', 'response_templates', $code, $relative);
}

/**
 * The arms every model chokepoint in this tree is made of, returned as data.
 *
 * ⛔ **PARAMETERISED RATHER THAN COPIED, AND THE REASON IS 3889's DEFECT.** The
 * cost-book arms took three separate corrections to close — the facade spelling
 * of `DB::table()`, the aliased import, and the SQL-context requirement that
 * stops the table-name arm matching English prose — and every one of them is a
 * fact about *how somebody reaches an Eloquent model*, not about that particular
 * model. A second copy would start out one revision behind and nobody would know
 * which.
 *
 * ⛔ **RETURNED AS DATA RATHER THAN RUN INLINE, BECAUSE THE GUARD OVER THESE
 * ARMS MAY NOT HOLD ITS OWN COPY OF THEM** — `CLAUDE.md` §*What a chokepoint
 * lint owes*, third bullet: *a lint holding its own copy of the pattern its
 * guard reads is 8460's shape even when both copies are correct today*. Twelve
 * consumers each assert their fragment set drives every arm, and every one of
 * them asks **this** function for the arm set on every run rather than carrying
 * a re-typed list or a hand-kept count.
 *
 * ⛔ **THE OFFENCE STRINGS COLLIDE AT GROUP LEVEL AND THAT IS DELIBERATE, AND IT
 * IS WHY THE `id` EXISTS.** `static-access` and `construction` both report the
 * bare relative path; the three table-name arms all report *"queries {table} by
 * table name"*. An offence names the **route a file took**, not the regex that
 * spotted it, and `ReviewsTest`'s one-arm waiver for `ResponseTemplatePolicy`
 * is written against one of those strings by exact value. So an offence list
 * structurally **cannot** say which arm fired — a coverage guard keyed on the
 * output would read five arms where there are eight, and would go on reading
 * five after three of them stopped matching anything at all.
 *
 * ⚠️ **THE ORDER IS THE ORDER THE OFFENCES ARE EMITTED IN**, because
 * {@see modelChokepointOffences()} walks this list and de-duplicates by offence
 * string. Reordering these entries reorders a lint's output, and at least one
 * consumer asserts an offence list by exact value.
 *
 * @return list<array{id: string, pattern: string, offence: string, narrows: string|null}>
 */
function modelChokepointArms(string $model, string $table, string $relative = 'a file'): array
{
    $qualified = '\\\\?(?:[A-Za-z0-9_]+\\\\)*'.$model;

    return [
        // Static access that is not the `::class` constant, plus direct
        // construction, since the model uses `$guarded`.
        //
        // ⚠️ CASE-INSENSITIVE, DELIBERATELY (10250): a class name and a static
        // method call are both PHP-identifier dispatch, so `wordpresscredential::`
        // or `new WORDPRESSCREDENTIAL` reach the same class — and the same `/i`
        // is what makes the `(?!class\b)` exclusion recognise `::CLASS`/`::Class`
        // as the legitimate magic constant it is, rather than flagging it as an
        // offence for not spelling `class` in lower case.
        [
            'id' => 'static-access',
            'pattern' => '/\b'.$model.'::(?!class\b)/i',
            'offence' => $relative,
            'narrows' => null,
        ],
        [
            'id' => 'construction',
            'pattern' => '/\bnew\s+'.$qualified.'\b/i',
            'offence' => $relative,
            'narrows' => null,
        ],

        // The table by name, through the query builder or raw SQL — the route
        // that mentions no class and so evades every check above.
        [
            'id' => 'table-builder',
            'pattern' => '/(?:->|::)\s*(?:table|from)\s*\(\s*[\'"]'.$table.'\b/i',
            'offence' => "{$relative}: queries {$table} by table name",
            'narrows' => null,
        ],
        [
            'id' => 'raw-update',
            'pattern' => '/\bUPDATE\s+'.$table.'\b/i',
            'offence' => "{$relative}: queries {$table} by table name",
            'narrows' => null,
        ],
        [
            'id' => 'raw-statement',
            'pattern' => '/\b(?:SELECT|INSERT|DELETE|WITH)\b.{0,400}?\b(?:FROM|INTO|JOIN)\s+'.$table.'\b/is',
            'offence' => "{$relative}: queries {$table} by table name",
            'narrows' => null,
        ],

        // The model resolved out of the container, which yields a builder from
        // the one spelling that has to stay legal.
        //
        // ⚠️ CASE-INSENSITIVE, DELIBERATELY (10250): `app`/`resolve`/`make` are
        // function calls and `::class` is a class-name reference — both
        // PHP-identifier dispatch.
        [
            'id' => 'container',
            'pattern' => '/\b(?:app|resolve|make)\s*\(\s*'.$qualified.'::class/i',
            'offence' => "{$relative}: resolves {$model} from the container",
            'narrows' => null,
        ],

        // A relationship, which is the same reach wearing an Eloquent accessor —
        // and the one a screen would actually use.
        //
        // ⚠️ CASE-INSENSITIVE, DELIBERATELY (10250): the relationship method and
        // the class name are both PHP-identifier dispatch.
        [
            'id' => 'relationship',
            'pattern' => '/\b(?:hasMany|hasOne|belongsTo|belongsToMany|morphMany|morphOne|hasManyThrough|hasOneThrough)\s*\(\s*(?:'.$qualified.'::class|[\'"]\\\\?App\\\\Models\\\\'.$model.'[\'"])/i',
            'offence' => "{$relative}: declares a relationship to {$model}",
            'narrows' => null,
        ],

        // The import itself, aliased or not — what makes every arm above
        // unavoidable rather than merely inconvenient.
        //
        // ⚠️ CASE-INSENSITIVE, DELIBERATELY (10250): a namespace resolves exactly
        // like a class name — `use app\models\Xxx;` imports the same class.
        [
            'id' => 'import',
            'pattern' => '/^\s*use\s+\\\\?App\\\\Models\\\\'.$model.'\s*(?:as\s+[A-Za-z0-9_]+\s*)?;/mi',
            'offence' => "{$relative}: imports {$model}",
            'narrows' => null,
        ],
    ];
}

/**
 * Which routes to `$table` a file takes that only its one permitted service may
 * take.
 *
 * ⚠️ **THE OUTPUT IS DE-DUPLICATED BY OFFENCE STRING, WHICH IS WHAT MAKES THIS
 * BYTE-IDENTICAL TO THE FIVE SEQUENTIAL `if` BLOCKS IT REPLACED.** Two arms that
 * share an offence string report once between them, exactly as `A || B` inside
 * one `if` did.
 *
 * @return list<string> One line per route taken, empty when the file is clean.
 */
function modelChokepointOffences(string $model, string $table, string $code, string $relative = 'a file'): array
{
    $offences = [];

    foreach (modelChokepointArms($model, $table, $relative) as $arm) {
        if (preg_match($arm['pattern'], $code) === 1 && ! in_array($arm['offence'], $offences, true)) {
            $offences[] = $arm['offence'];
        }
    }

    return $offences;
}

/**
 * What a consumer is told when its own fragment set stops driving every arm.
 *
 * ⛔ **ONE SENTENCE IN ONE PLACE, BECAUSE TWELVE CONSUMERS SAY IT.** Twelve
 * hand-typed copies of a demand is how a demand comes to say twelve slightly
 * different things, and this one has to name the remedy precisely: the arm ids
 * in the failure output are the ids of {@see modelChokepointArms()}, and the
 * fix is a fragment per named arm rather than a shorter arm list.
 */
function modelChokepointCoverageDemand(string $model): string
{
    return "The fragment set in this {$model} control no longer proves the arms named above. "
        .'An arm nothing reaches is an arm this test would stay green without, and it is green '
        .'today only because some other chokepoint in some other file happens to drive it. An '
        .'arm nothing reaches ON ITS OWN is worse: a wider arm added later absorbs every '
        .'fragment that reaches it and the coverage stays perfect. There are exactly three '
        .'endings. Add a fragment reaching the arm, spelled the way somebody would really write '
        .'it. Add one reaching that arm and no other. Or, if the arm is a strict narrowing of '
        .'another and cannot be isolated by construction, declare what it narrows in '
        .'modelChokepointArms() and it will owe coverage only. Do not shorten the arm list to '
        .'match the fragments: the set is asked of modelChokepointArms() on every run, so a '
        .'ninth arm is meant to redden every one of these controls on the commit that adds it '
        .'(12050).';
}

/**
 * Which arms of {@see modelChokepointArms()} a fragment reaches, by id.
 *
 * ⛔ **THIS IS THE ONLY THING THAT CAN TELL TWO ARMS SHARING AN OFFENCE STRING
 * APART**, which is the whole reason it exists: `new X(…)` and `X::query()`
 * produce one identical line of output, and so do all three of the table-name
 * routes.
 *
 * @return list<string>
 */
function modelChokepointArmsReached(string $model, string $table, string $code): array
{
    $reached = [];

    foreach (modelChokepointArms($model, $table) as $arm) {
        if (preg_match($arm['pattern'], $code) === 1) {
            $reached[] = $arm['id'];
        }
    }

    return $reached;
}

/**
 * What a chokepoint's own fragment set fails to prove about the classifier —
 * empty when the set reaches every arm and pins every arm on its own.
 *
 * ⛔ **WRITTEN BECAUSE NOTHING BOUND THE TWELVE CONSUMERS' FRAGMENT SETS TO THE
 * ARM SET** (12050). `CustomerRegionTest`'s `columnWriteArmIsolations()` asserts
 * its keys against `count(columnWriteShapes(…))` — the arm count asked of the
 * classifier on every run — and this classifier had no equivalent, so a ninth
 * arm could be added with no fragment anywhere.
 *
 * ⛔ **IT DEMANDS ISOLATION AND NOT ONLY COVERAGE, AND THE DIFFERENCE IS THE
 * WHOLE POINT.** Coverage alone is satisfied by a **wider** ninth arm, because
 * a wider arm matches fragments that already exist — so the set would stay
 * "covered" while every one of those fragments quietly stopped proving anything
 * about the arm it was written for. Isolation asks the question that cannot be
 * answered by accident: *is there a fragment this arm and only this arm sees?*
 * ⚠️ **Measured before it was demanded, at `a810f145`: all twelve sets already
 * isolate all eight arms**, so this costs no fragment today and refuses the
 * next silent widening.
 *
 * ⛔ **AN ARM THAT IS A STRICT NARROWING OF ANOTHER CANNOT BE ISOLATED AND MUST
 * NOT BE ASKED TO BE** (11830–11839) — a narrowing exists so a consumer can drop
 * its parent, and demanding a shape it alone matches is demanding it stop
 * existing. Such an arm declares `narrows` in {@see modelChokepointArms()},
 * naming the arm it narrows, and owes coverage only. **Declaring it is the
 * argument**; the eight arms in this tree declare `null` because none of them
 * narrows another.
 *
 * ⚠️ **MEASURED BEFORE IT WAS WRITTEN**, by neutering one arm at a time and
 * running all seven consuming files: eight of the twelve controls drove all
 * eight arms and four did not. Three controls drove all eight, which is what
 * kept the other four looking healthy — **a control blind to an arm is
 * invisible while any sibling anywhere in the tree drives it.**
 *
 * @param  array<string, string>  $fragments  The consumer's own positive-control
 *                                            set, label => code fragment.
 * @return list<string> One line per arm the set does not prove, arm id first.
 */
function modelChokepointArmsNotProven(string $model, string $table, array $fragments): array
{
    $reachedBy = [];
    $isolatedBy = [];

    foreach ($fragments as $code) {
        $reached = modelChokepointArmsReached($model, $table, $code);

        foreach ($reached as $id) {
            $reachedBy[$id] = true;
        }

        if (count($reached) === 1) {
            $isolatedBy[$reached[0]] = true;
        }
    }

    $unproven = [];

    foreach (modelChokepointArms($model, $table) as $arm) {
        if (! isset($reachedBy[$arm['id']])) {
            $unproven[] = $arm['id'].': no fragment in this set reaches it at all';

            continue;
        }

        if ($arm['narrows'] === null && ! isset($isolatedBy[$arm['id']])) {
            $unproven[] = $arm['id'].': reached, but never on its own, so a wider arm could '
                .'absorb every fragment that reaches it and nothing would say so';
        }
    }

    return $unproven;
}

/**
 * How many times a chunk of PHP really CALLS a named function, counted with the
 * lexer rather than with a pattern.
 *
 * ⛔ **WRITTEN BECAUSE THE PATTERN VERSION FOUND A CALL THAT WAS A SENTENCE**
 * (12050). `phpWithoutComments()` strips comments and **not strings**, and
 * `RegexCaseSensitivityTest` names this classifier inside a failure message —
 * `'modelChokepointOffences() no longer spells its static-access arm this way'`
 * — which a `/\bname\s*\(/` count reads as two call sites. That is 3897's
 * defect exactly, one layer up: an arm matching English prose inside a string
 * literal, where the only obvious fix is to stop explaining the thing.
 *
 * ⚠️ **A MENTION IS A `T_CONSTANT_ENCAPSED_STRING` AND A CALL IS A `T_STRING`**,
 * so the lexer separates them with no pattern to tune and nothing to widen.
 *
 * ⚠️ **CASE-INSENSITIVE, DELIBERATELY** (10203): PHP dispatches a function name
 * case-insensitively, so `MODELCHOKEPOINTOFFENCES(` is the same call.
 */
function phpCallSiteCount(string $php, string $callee): int
{
    $tokens = token_get_all(str_starts_with(ltrim($php), '<?php') ? $php : '<?php '.$php);

    $count = 0;

    foreach ($tokens as $index => $token) {
        if (! is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        if (strcasecmp($token[1], $callee) !== 0) {
            continue;
        }

        for ($next = $index + 1; $next < count($tokens); $next++) {
            $following = $tokens[$next];

            if (is_array($following) && in_array($following[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($following === '(') {
                $count++;
            }

            break;
        }
    }

    return $count;
}

/**
 * Every `…ChokepointOffences()` wrapper declared in this file, against the model
 * and table it fixes.
 *
 * ⛔ **DERIVED FROM THIS FILE'S OWN SOURCE RATHER THAN LISTED** (12050). A
 * hand-kept map of the wrappers is the artefact `CLAUDE.md` §*What a chokepoint
 * lint owes* is about — it would go stale on the wave that adds the ninth
 * wrapper, silently, in the direction of reporting full coverage.
 *
 * ⚠️ **A WRAPPER THAT STOPS DELEGATING TO {@see modelChokepointOffences()} FALLS
 * OUT OF THIS MAP**, which is why `ModelChokepointTest` asserts the declared
 * wrapper names and the resolved ones are the same set: a wrapper that inlines
 * its own arms is a second copy of the classifier and has to be seen, not
 * quietly dropped from a census.
 *
 * @return array<string, string> Wrapper function name => `"Model/table"`.
 */
function modelChokepointWrappers(): array
{
    preg_match_all(
        '/function\s+([A-Za-z0-9_]+ChokepointOffences)\s*\([^)]*\)\s*:\s*array\s*\{\s*'
        .'return\s+modelChokepointOffences\(\s*\'([A-Za-z0-9_]+)\'\s*,\s*\'([a-z0-9_]+)\'/i',
        (string) file_get_contents(__FILE__),
        $matches,
        PREG_SET_ORDER,
    );

    $wrappers = [];

    foreach ($matches as $match) {
        $wrappers[$match[1]] = $match[2].'/'.$match[3];
    }

    ksort($wrappers);

    return $wrappers;
}

/**
 * Every `"Model/table"` a chunk of test code runs a model chokepoint over,
 * through the shared classifier directly or through one of its wrappers.
 *
 * ⚠️ **CASE-INSENSITIVE, DELIBERATELY** (10203): PHP dispatches a function name
 * case-insensitively and `grep` does not, so `ModelChokepointOffences(` reaches
 * the same function and a case-sensitive census would report the file clean.
 * ⛔ **It would not fail loudly — it would narrow**, which is why
 * `ModelChokepointTest` drives a mis-cased call as a control.
 *
 * ⚠️ **THE FIRST TWO ARGUMENTS MUST BE LITERALS.** A call passing variables is
 * invisible here, and the census asserts there are none rather than assuming it.
 *
 * @return list<string> Sorted, unique.
 */
function modelChokepointSubjectsIn(string $code): array
{
    $subjects = [];

    preg_match_all(
        '/\bmodelChokepointOffences\s*\(\s*\'([A-Za-z0-9_]+)\'\s*,\s*\'([a-z0-9_]+)\'/i',
        $code,
        $matches,
        PREG_SET_ORDER,
    );

    foreach ($matches as $match) {
        $subjects[$match[1].'/'.$match[2]] = true;
    }

    foreach (modelChokepointWrappers() as $wrapper => $subject) {
        if (preg_match('/\b'.preg_quote($wrapper, '/').'\s*\(/i', $code) === 1) {
            $subjects[$subject] = true;
        }
    }

    $subjects = array_keys($subjects);
    sort($subjects);

    return $subjects;
}

/**
 * Every `"Model/table"` a chunk of test code proves the classifier's arms for,
 * through {@see modelChokepointArmsNotProven()}.
 *
 * ⚠️ **CASE-INSENSITIVE FOR THE SAME REASON THE SCANNER ABOVE IS** (10203).
 *
 * @return list<string> Sorted, unique.
 */
function modelChokepointProvenSubjectsIn(string $code): array
{
    preg_match_all(
        '/\bmodelChokepointArmsNotProven\s*\(\s*\'([A-Za-z0-9_]+)\'\s*,\s*\'([a-z0-9_]+)\'/i',
        $code,
        $matches,
        PREG_SET_ORDER,
    );

    $subjects = [];

    foreach ($matches as $match) {
        $subjects[$match[1].'/'.$match[2]] = true;
    }

    $subjects = array_keys($subjects);
    sort($subjects);

    return $subjects;
}

/**
 * One arm's pattern for one subject, resolved from {@see modelChokepointArms()}
 * by id, with the caller's own remedy on the failure.
 *
 * ⛔ **IT THROWS RATHER THAN SKIPPING.** A missing id resolved with `?? null`
 * and skipped is the vacuity 12183–12189 was about: the lint would go on
 * passing while the arm it thought it was running had stopped existing.
 *
 * ⛔ **IT IS HERE BECAUSE IT WAS ABOUT TO BE WRITTEN A THIRD TIME** (12390).
 * `pixelChokepointPattern()` and `planOfferChokepointPattern()` landed the same
 * six-line resolve-or-throw within one commit of each other, and the lint whose
 * whole subject is un-compared copies cannot add a third. Neither of those two
 * re-typed a *pattern*, so this is a smaller shape than 8460's — but the
 * remedy sentence is the part that differs between callers, so it is a
 * parameter rather than a reason to keep three copies.
 */
function modelChokepointArmPattern(string $model, string $table, string $id, string $remedy): string
{
    foreach (modelChokepointArms($model, $table) as $arm) {
        if ($arm['id'] === $id) {
            return $arm['pattern'];
        }
    }

    throw new RuntimeException("modelChokepointArms() no longer declares an arm called '{$id}'. A lint "
        .'that asks for its patterns by id rather than re-typing them has to answer a renamed or '
        ."deleted arm rather than silently dropping it. {$remedy}");
}

/**
 * The value of one PHP string-literal token, decoded the way PHP decodes it.
 *
 * ⛔ **RAW TOKEN TEXT IS NOT A PATTERN, AND `pregMatchCallPatterns()` KEEPS RAW
 * TOKEN TEXT ON PURPOSE** — its consumers ask *"does this pattern carry `/i`"*,
 * which survives the quotes being left on. A consumer asking *"is this pattern
 * character-for-character the one in `modelChokepointArms()`"* cannot: the arm
 * is a value, so the copy has to be a value too, and a double-quoted `"\\b"`
 * and a single-quoted `'\b'` are the same pattern written two ways.
 *
 * ⚠️ **HEREDOCS ARE OUT OF SCOPE AND THAT IS THE LEXER'S BOUNDARY RATHER THAN A
 * CHOICE** — a heredoc body is `T_ENCAPSED_AND_WHITESPACE` under
 * `T_START_HEREDOC`, never `T_CONSTANT_ENCAPSED_STRING`, so it never reaches
 * this function. There is no heredoc regex in `tests/`, and one written
 * tomorrow is invisible to every caller here: a floor, not a ceiling (565).
 */
function phpStringLiteralValue(string $raw): string
{
    $quote = $raw[0];
    $body = substr($raw, 1, -1);
    $length = strlen($body);
    $out = '';

    if ($quote === "'") {
        // Single quotes escape exactly two characters and nothing else, so a
        // sequential str_replace() would decode `\\'` wrongly.
        for ($i = 0; $i < $length; $i++) {
            if ($body[$i] === '\\' && $i + 1 < $length && ($body[$i + 1] === '\\' || $body[$i + 1] === "'")) {
                $out .= $body[$i + 1];
                $i++;

                continue;
            }

            $out .= $body[$i];
        }

        return $out;
    }

    $simple = ['n' => "\n", 't' => "\t", 'r' => "\r", 'v' => "\v", 'e' => "\e", 'f' => "\f",
        '\\' => '\\', '$' => '$', '"' => '"'];

    for ($i = 0; $i < $length; $i++) {
        if ($body[$i] !== '\\' || $i + 1 >= $length) {
            $out .= $body[$i];

            continue;
        }

        $next = $body[$i + 1];

        if (isset($simple[$next])) {
            $out .= $simple[$next];
            $i++;

            continue;
        }

        if ($next === 'x' && preg_match('/^[0-9A-Fa-f]{1,2}/', substr($body, $i + 2, 2), $hex) === 1) {
            $out .= chr((int) hexdec($hex[0]));
            $i += 1 + strlen($hex[0]);

            continue;
        }

        if ($next === 'u' && preg_match('/^\{([0-9A-Fa-f]+)\}/', substr($body, $i + 2), $point) === 1) {
            $out .= mb_chr((int) hexdec($point[1]), 'UTF-8');
            $i += 1 + strlen($point[0]);

            continue;
        }

        if (preg_match('/^[0-7]{1,3}/', substr($body, $i + 1, 3), $octal) === 1) {
            $out .= chr((int) octdec($octal[0]));
            $i += strlen($octal[0]);

            continue;
        }

        // A backslash before anything else is a literal backslash in PHP.
        $out .= '\\';
    }

    return $out;
}

/**
 * The placeholder {@see phpStringExpressions()} puts where a concatenated
 * expression it cannot resolve stood.
 *
 * ⛔ **NAMED ONCE BECAUSE THREE THINGS COMPARE AGAINST IT** — the scanner
 * writes it, the copy classifier accepts it in the model position, and
 * `ModelChokepointTest`'s controls drive it. ⚠️ **It is deliberately NOT the
 * empty seam `pregMatchCallPatterns()` leaves**: that function's consumers ask
 * about flags, where a seam is harmless, and this one's consumers ask what
 * stood in the model position, where an empty seam and a deleted model name are
 * the same string.
 */
function phpConcatenationPlaceholder(): string
{
    return '{EXPR}';
}

/**
 * Every string-literal expression in a chunk of PHP, with `.`-concatenation
 * joined and its decoded value.
 *
 * ⛔ **THIS IS NOT `pregMatchCallPatterns()` WITH A DECODER ON IT, AND THE
 * DIFFERENCE IS THE POPULATION.** That function's subject is *the first
 * argument of a `preg_match()` call*, and its own docblock names four patterns
 * in this tree it structurally cannot see — a pattern assigned to a variable on
 * one line and used on a later one, which is exactly how `InboxTest` and
 * `GbpTest` write theirs, and how every pattern handed to
 * `chokepointAllowlistOffences()` is written. This function's subject is *every
 * string literal in the file*, whatever it is later passed to, which is the
 * only population that can answer *"is this arm re-typed anywhere"*.
 * ⚠️ **`ModelChokepointTest` drives that difference rather than asserting it**:
 * a spelling this finds and `pregMatchCallPatterns()` does not is a floor on
 * the two not having converged.
 *
 * ⚠️ **COMMENTS ARE INVISIBLE HERE FOR FREE.** A `::(?!class\b)` written in a
 * comment is `T_COMMENT`, never `T_CONSTANT_ENCAPSED_STRING` — which matters,
 * because `StaffTest` explains one of its chokepoints by quoting it.
 *
 * @return list<array{line: int, value: string}>
 */
function phpStringExpressions(string $php): array
{
    $tokens = token_get_all($php);
    $count = count($tokens);
    $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    $expressions = [];

    for ($i = 0; $i < $count; $i++) {
        if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $line = $tokens[$i][2];
        $value = phpStringLiteralValue($tokens[$i][1]);
        $j = $i + 1;

        while ($j < $count) {
            while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], $skip, true)) {
                $j++;
            }

            if ($j >= $count || $tokens[$j] !== '.') {
                break;
            }

            $j++;

            while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], $skip, true)) {
                $j++;
            }

            if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
                $value .= phpStringLiteralValue($tokens[$j][1]);
                $j++;

                continue;
            }

            // Something that is not a literal — a variable, a call, a constant.
            // It is walked past to the next top-level `.` rather than resolved,
            // and what stood there is named rather than elided.
            $value .= phpConcatenationPlaceholder();
            $depth = 0;

            while ($j < $count) {
                $token = $tokens[$j];

                if ($token === '(' || $token === '[') {
                    $depth++;
                }

                if ($token === ')' || $token === ']') {
                    if ($depth === 0) {
                        break;
                    }

                    $depth--;
                }

                if ($depth === 0 && ($token === ',' || $token === ';' || $token === '.')) {
                    break;
                }

                $j++;
            }
        }

        $i = $j - 1;
        $expressions[] = ['line' => $line, 'value' => $value];
    }

    return $expressions;
}

/**
 * The needle that finds a re-typed spelling of the static-access arm, and it is
 * deliberately NOT that arm.
 *
 * ⛔ **A FINDER TAKEN FROM THE THING IT IS CHECKING FINDS NOTHING THE DAY THE
 * THING CHANGES, AND REPORTS THE TREE CLEAN** (12391). If
 * {@see modelChokepointCopySpellings()} looked for the arm's own text, then
 * editing the arm would empty its population and every copy in `tests/` would
 * go uncompared on the one commit where comparing them matters. So the needle
 * is the *family* — a static access followed by a negative lookahead — which is
 * what the shape has been since the day it was written and is one level above
 * what the arm says.
 *
 * ⚠️ **IT IS A FLOOR AND NOT A CEILING** (565): a copy written without a
 * lookahead at all is invisible here. That wider family is
 * {@see staticAccessShapeRegex()}'s, and `RegexCaseSensitivityTest` asks it a
 * different question — whether the pattern carries `/i` — over the same tree.
 */
function modelChokepointCopyNeedle(): string
{
    return '::(?!';
}

/**
 * Every verdict {@see modelChokepointCopyVerdict()} can return.
 *
 * ⛔ **ASKED OF THE CLASSIFIER RATHER THAN RE-TYPED BY ITS CONTROLS**, on
 * `CustomerRegionTest`'s `columnWriteArmIsolations()` rule (11390–11392): a
 * control set that names its own arms is satisfied by whichever arms it
 * happened to name, so a seventh verdict added tomorrow would land with no
 * control and nothing would say so. `ModelChokepointTest` asserts its control
 * set against this list in both directions.
 *
 * @return list<string>
 */
function modelChokepointCopyVerdicts(): array
{
    return ['not-a-pattern', 'agrees', 'agrees-fused', 'diverges-before', 'diverges-model',
        'diverges-after', 'diverges-flags'];
}

/**
 * Whether one spelling found in `tests/` is the `static-access` arm of
 * {@see modelChokepointArms()} with a model substituted, and if not, where it
 * parts company with it.
 *
 * ⛔ **BOTH SIDES COME FROM THE ARM, WHICH IS THE WHOLE POINT.** The arm is
 * resolved for a sentinel model and split at the sentinel, so the prefix
 * (`\b`), the core (`::(?!class\b)`) and the flags are read out of
 * `modelChokepointArms()` on every run. Nothing here re-types any of them —
 * `CLAUDE.md` §*What a chokepoint lint owes*: *a lint holding its own copy of
 * the pattern its guard reads is 8460's shape even when both copies are
 * correct today*, and this exists because the tree is full of them.
 * ⚠️ **THE COUNT IS DELIBERATELY NOT WRITTEN HERE** — 12190 recorded one, it was
 * already two smaller when this landed, and `ModelChokepointTest`'s failure
 * output is the census.
 *
 * ⚠️ **`not-a-pattern` IS A VERDICT AND NOT A SKIP.** A spelling that is not a
 * delimited regex is a fixture or a source-text needle — `RegexCaseSensitivity
 * Test` holds four, three code fragments it feeds to its own detector and one
 * `str_contains()` needle over `architecture_helpers.php` itself. Saying so is
 * what lets a control drive the arm; swallowing it silently is how a scanner
 * that has stopped parsing looks exactly like a tree with nothing in it.
 *
 * ⚠️ **A QUOTE IS NOT ACCEPTED AS A DELIMITER**, on the argument
 * {@see staticAccessShapeRegex()} already made for its own quote exclusion:
 * PCRE allows it, no pattern in this tree uses it, and accepting it would read
 * the PHP source of an interpolated arm — `'/\b'.$model.'::(?!class\b)/i'` — as
 * a pattern delimited by apostrophes.
 *
 * ⚠️ **THE DELIMITER ITSELF IS NOT COMPARED.** `#…#` and `/…/` are the same
 * regex, and reporting a delimiter change as a divergence would be a false
 * accusation of the kind 12192 warned about. The body and the flag SET are
 * compared; the delimiter is only parsed.
 */
function modelChokepointCopyVerdict(string $spelling): string
{
    $shape = modelChokepointCopyArmShape();
    $before = $shape['before'];
    $after = $shape['after'];

    $parts = modelChokepointCopyParts($spelling);

    if ($parts === null) {
        return 'not-a-pattern';
    }

    if (! str_starts_with($parts['body'], $before)) {
        return 'diverges-before';
    }

    $core = strpos($parts['body'], $after, strlen($before));

    if ($core === false) {
        return 'diverges-after';
    }

    $model = substr($parts['body'], strlen($before), $core - strlen($before));

    // A model position is a class name, the placeholder left where an
    // interpolated one stood, or an alternation of class names — which is how
    // `ConsentTest` and `ReviewsTest` write a chokepoint over several models at
    // once, and is the same regex as writing the arm once per model.
    $identifier = '[A-Za-z_][A-Za-z0-9_]*';
    $position = '/^(?:'.$identifier.'|'.preg_quote(phpConcatenationPlaceholder(), '/')
        .'|\((?:'.$identifier.'\|)+'.$identifier.'\))$/';

    if (preg_match($position, $model) !== 1) {
        return 'diverges-model';
    }

    $trailing = substr($parts['body'], $core + strlen($after));

    // ⛔ **THE ONE FUSION THAT IS TWO ARMS RATHER THAN A DIVERGENCE**, and it is
    // recognised by CONSTRUCTING it from the arms rather than by excusing it.
    // `GbpTest` writes `static-access|construction` as one alternation because
    // the lint reading it asks a single yes/no per file. Both halves are the
    // arms' own text, so calling that a divergence would waive the two live
    // copies of the `construction` arm — and an excused spelling is a spelling
    // nothing compares again (12192 named these as variants to exempt; exempting
    // them is what would have left them uncompared).
    if ($trailing !== '') {
        $construction = modelChokepointCopyParts(modelChokepointArmPattern($model, 'zz_table_sentinel_zz',
            'construction', 'Fix modelChokepointCopyVerdict() in architecture_helpers.php, which builds '
            .'the fused static-access|construction spelling out of that arm to recognise it.'));

        if ($construction === null || $trailing !== '|'.$construction['body']) {
            return 'diverges-after';
        }

        if (count_chars($parts['flags'], 3) !== count_chars($shape['flags'], 3)
            || count_chars($construction['flags'], 3) !== count_chars($shape['flags'], 3)) {
            return 'diverges-flags';
        }

        return 'agrees-fused';
    }

    if (count_chars($parts['flags'], 3) !== count_chars($shape['flags'], 3)) {
        return 'diverges-flags';
    }

    return 'agrees';
}

/**
 * The `static-access` arm taken apart into the four pieces a copy is compared
 * against: what stands before the model, what stands after it, the delimiter
 * and the flags.
 *
 * ⛔ **IT IS A FUNCTION SO THAT THE CONTROLS AND THE CLASSIFIER READ THE SAME
 * FOUR PIECES.** `ModelChokepointTest` builds one control fragment per verdict
 * by substituting into these, which is the only construction that cannot drift
 * from what the classifier compares — a control that re-typed `\b` or `/i`
 * would be a second copy of the arm inside the guard written to find second
 * copies of the arm, which is 12187's defect exactly, one layer up.
 *
 * @return array{delimiter: string, before: string, after: string, flags: string}
 */
function modelChokepointCopyArmShape(): array
{
    $sentinel = 'ZzModelSentinelZz';

    $arm = modelChokepointArmPattern($sentinel, 'zz_table_sentinel_zz', 'static-access',
        'Fix modelChokepointCopyArmShape() in architecture_helpers.php, which takes that arm apart to '
        .'compare every re-typed spelling of it in tests/ against it.');

    $parts = modelChokepointCopyParts($arm);

    if ($parts === null) {
        throw new RuntimeException('The static-access arm of modelChokepointArms() no longer parses as a '
            ."delimited regex: [{$arm}]. Every copy of it in tests/ is compared against it by splitting "
            .'it here, so this has to be answered rather than arriving as a divergence on every copy.');
    }

    $at = strpos($parts['body'], $sentinel);

    if ($at === false) {
        throw new RuntimeException('The static-access arm of modelChokepointArms() no longer interpolates '
            .'the model name into its pattern, so there is no model position to substitute and no way to '
            .'compare a copy against it. Fix modelChokepointCopyArmShape().');
    }

    return [
        'delimiter' => $parts['delimiter'],
        'before' => substr($parts['body'], 0, $at),
        'after' => substr($parts['body'], $at + strlen($sentinel)),
        'flags' => $parts['flags'],
    ];
}

/**
 * A delimited regex split into its delimiter, body and flags, or `null` when it
 * is not one.
 *
 * @return array{delimiter: string, body: string, flags: string}|null
 */
function modelChokepointCopyParts(string $spelling): ?array
{
    if (preg_match('/^([^A-Za-z0-9_\\\\\s\x27\x22])(.*)\1([a-zA-Z]*)$/s', $spelling, $match) !== 1) {
        return null;
    }

    return ['delimiter' => $match[1], 'body' => $match[2], 'flags' => $match[3]];
}

/**
 * Every re-typed spelling of the static-access arm in a chunk of PHP, with the
 * line it is written on and the classifier's verdict on it.
 *
 * @return list<array{line: int, spelling: string, verdict: string}>
 */
function modelChokepointCopySpellings(string $php): array
{
    $found = [];

    foreach (phpStringExpressions($php) as $expression) {
        if (! str_contains($expression['value'], modelChokepointCopyNeedle())) {
            continue;
        }

        $found[] = [
            'line' => $expression['line'],
            'spelling' => $expression['value'],
            'verdict' => modelChokepointCopyVerdict($expression['value']),
        ];
    }

    return $found;
}

/**
 * Every file `VoiceTest`'s containment lints scan (4513).
 *
 * ⛔ **`Services/Voice` ALONE WAS THE SCAN, AND THREE DOCBLOCKS DESCRIBED IT AS
 * "ANY FILE".** The rule held anyway — `OutboundTest`'s app-wide `Http::`
 * chokepoint means a request written outside the permit list fails there first —
 * but an overclaiming docblock is what stops the next reviewer looking, which is
 * `CLAUDE.md`'s 314–316 exactly. A job, a listener or the webhook controller is
 * where "just hang the call up" would actually be written, so they are scanned
 * too, and the prose in `InfobipVoiceProvider` and beside the permit list above
 * now names this function rather than a claim.
 *
 * ⚠️ **THE INTERFACE IS INCLUDED BY NAME.** It is one file rather than a
 * directory, and it is the single most likely place for a `dial()` to appear.
 *
 * @return list<SplFileInfo>
 */
function voiceFilesUnderTest(): array
{
    $files = [];

    foreach (['Services/Voice', 'Jobs/Voice', 'Listeners/Voice', 'Http/Controllers/Voice'] as $directory) {
        $path = app_path($directory);

        if (! File::isDirectory($path)) {
            // A directory that does not exist yet is not a failure — but it is
            // also not a pass, so it contributes nothing rather than silently
            // emptying the scan.
            continue;
        }

        foreach (File::allFiles($path) as $file) {
            $files[] = $file;
        }
    }

    foreach (File::allFiles(app_path('Contracts')) as $file) {
        if ($file->getFilename() === 'VoiceProvider.php') {
            $files[] = $file;
        }
    }

    return $files;
}

/**
 * A voice file's path relative to `app/`, for a lint's offender list.
 *
 * ⚠️ **NOT `getRelativePathname()`, WHICH IS RELATIVE TO WHICHEVER DIRECTORY WAS
 * SCANNED.** With more than one root that produces two `InfobipVoiceEvent.php`s
 * with no way to tell them apart, and an offender list nobody can act on is a
 * red build with no address.
 */
function voicePathOf(SplFileInfo $file): string
{
    return str_replace('\\', '/', str_replace(app_path().DIRECTORY_SEPARATOR, '', (string) $file->getRealPath()));
}

/**
 * Whether a voice file can issue an HTTP request at all (4512).
 *
 * ⛔ **THE VERB IS ONLY EVIDENCE OF A VENDOR WRITE WHEN THE RECEIVER IS AN HTTP
 * CLIENT, AND WIDENING THE SCAN IS WHAT MADE THAT TRUE.** `->post(`, `->put(`,
 * `->patch(` and `->delete(` are the mutating verbs of Laravel's HTTP client and
 * also of a filesystem, a cache and a collection. Under `Services/Voice` alone
 * that never collided; the first file the widened scan reached —
 * `Jobs/Voice/FetchVoicemailRecordingJob` — writes the voicemail audio with
 * `Storage::disk(self::DISK)->put()` and was reported as *placing a call*.
 *
 * ⚠️ **THE FIX IS THE PREDICATE THE LINT'S OWN COMMENT ALREADY CLAIMED**, not an
 * exemption list. It said *"`Http::` in any spelling, followed by a mutating
 * verb"* while matching the verb alone, so this makes the code do what the
 * sentence says. **An exemption list would have been the wrong answer twice
 * over**: it decays into "every file that legitimately writes a file", which is
 * 511's lint tuned until it catches nothing.
 *
 * ⛔ **AND IT LOSES NOTHING, BECAUSE OF HOW THE TWO LINTS COMPOSE.**
 * `OutboundTest`'s chokepoint permits `Http::` in named files only, so a voice
 * file that reaches Infobip **must** contain `Http::` and must have been added to
 * that permit list by name — at which point this lint sees it and refuses the
 * mutating verb. `PendingRequest` is matched too, because
 * `InfobipVoiceProvider::request()` returns one from a private helper and the
 * verb is then written on a variable in a different statement.
 *
 * ⚠️ **WHAT IT DOES NOT COVER IS SAID RATHER THAN GLOSSED** (314–316). A voice
 * file that built its own Guzzle client would evade this — and evades
 * `Http::preventStrayRequests()` and `OutboundTest` in exactly the same way, on
 * every vendor in this application. That hole is app-wide and already written
 * down beside the permit list; this function does not close it and does not
 * claim to.
 *
 * ⛔ **IT WAS TWO `str_contains()` NEEDLES AND BOTH SPELL A CLASS NAME, SO IT
 * READ THE ONE DIMENSION PHP DOES NOT** (12404). The verb regex above it
 * carries `/i`; ⛔ **the gate deciding whether the file is read at all did
 * not** — and a gate is upstream of everything, so `HTTP::post('/x')`,
 * `http::post('/x')` and `new PENDINGREQUEST()` made the file invisible to the
 * mutating-verb scan entirely rather than merely mis-reported. **A booted probe
 * under `Http::fake()` answered 200 to both mis-cased spellings**: they open
 * the socket. ⚠️ **There was no occupant, and the barrier was an accident** —
 * `InfobipVoiceProvider` is the only voice file on
 * {@see outboundHttpPermittedFiles()}, and what kept it visible was an
 * incidentally correctly-cased `PendingRequest` import, which is a class name
 * and folds too. ⚠️ **AND THAT MAKES `InfobipVoiceProvider` THE WORST FILE TO
 * MEASURE THE HOLE IN** — it carries eight of the two tokens, so mis-casing one
 * leaves the file visible through the others and the lint reddens as though
 * nothing were wrong. **Driven in `Services/Voice/VoiceCalls.php` instead, one
 * of twenty voice files carrying neither token**: with the gate as it was, a
 * planted `HTTP::withHeaders([])->post('/calls/1/calls')` left `VoiceTest`
 * **green at 8 tests / 28 assertions**; with the gate as it is, the same plant
 * reddens naming the file.
 *
 * ⛔ **`opensOutboundSocket()` IS IN THIS FILE, WAS FOLDED TO `(?i:Http::)` AT
 * 10910–10939, AND THIS SITE NEVER HEARD THE ARGUMENT.** One facade, two
 * functions, one file, opposite treatments. ⚠️ **AND THE REPAIR IS A SCOPED
 * REGEX RATHER THAN `stripos()`, DELIBERATELY** (12165): a substring needle
 * spelling a PHP call is structurally invisible to `RegexCaseSensitivityTest`,
 * whose population is `pregMatchCallPatterns()` — so becoming a regex is what
 * moves this site into the lint that would have found it.
 *
 * ⚠️ **THE FOLD IS OVER CLASS NAMES ONLY AND THAT IS THE WHOLE OF WHAT IS
 * SAFE.** PHP folds a class name, a static or instance method, a function name
 * and `::class`; it does **not** fold an enum case or a class constant, which
 * `constant()` throws on when mis-cased. Both needles here are class names.
 *
 * ⚠️ **NO `\b` IS ADDED.** The needles were substrings, so anchoring them now
 * would NARROW the gate, and a narrower gate is a file nobody scans — the
 * fail-open direction this repair exists to close.
 */
function voiceFileCanIssueAnHttpRequest(string $code): bool
{
    return preg_match('/(?i:Http::)|(?i:PendingRequest)/', $code) === 1;
}

/**
 * The mutating verbs of Laravel's HTTP client, as `VoiceTest` matches them.
 *
 * ⛔ **NAMED ONCE BECAUSE A CONTROL OVER THE LINT HAS TO DRIVE THE LINT'S OWN
 * PATTERN** (12405). A control re-typing this is a second copy of the rule
 * inside the guard written to check it — `CLAUDE.md` §*What a chokepoint lint
 * owes*, third bullet, and 12187's measured instance of it.
 *
 * ⚠️ **THE READ VERBS ARE DELIBERATELY ABSENT.** `get` and `head` are what this
 * application's voice driver legitimately does; the refusal is about placing,
 * hanging up or deleting.
 */
function voiceMutatingHttpVerbPattern(): string
{
    return '/->\s*(?:post|put|patch|delete)(?:Json)?\s*\(/i';
}

/**
 * Whether a file names the call-direction concept `29` §2.3's override row and
 * `database/migrations/…_create_calls_table.php`'s own docblock both refuse.
 *
 * ⛔ **PROJECT-WIDE, UNLIKE `voiceFilesUnderTest()`'s DIRECTORY SCAN, AND THAT
 * IS THE WHOLE REASON THIS FUNCTION EXISTS** — found by a wave-39 scout on this
 * exact commit. A `CallDirection` enum is the shape an outbound slice reaches
 * for first (`calls.direction` was refused specifically because a nullable
 * column "would also be a place to record a call this platform must never
 * place"), and it would naturally live under `app/Enums/`, which none of the
 * four directories `voiceFilesUnderTest()` walks includes. `VoiceTest`'s own
 * "nothing in the application can reach an endpoint that places a call" is a
 * claim about the *application*; a directory-scoped census cannot prove it.
 *
 * ⚠️ **`stripos()`, NOT `str_contains()` — CASE-INSENSITIVE ON PURPOSE, AND
 * PROVEN SO BY THE PLANTED-STRING TEST BESIDE IT** (10203). PHP resolves a
 * class or enum name case-insensitively; a case-sensitive match would miss
 * `calldirection` or `CALLDIRECTION` dispatching to the identical symbol.
 *
 * ⚠️ **THE NEEDLE IS THE WHOLE WORD `CallDirection`, NEVER THE BARE WORD
 * `outbound`.** `MessageCostKind::OutboundEmail` and `::OutboundSms` already
 * exist and name a message's cost direction, not a call's — a needle of
 * `outbound` alone would redden on both today, which is the lint-tuned-until-
 * it-catches-nothing failure (511) rather than a real finding.
 */
function namesCallDirectionConcept(string $code): bool
{
    return stripos($code, 'CallDirection') !== false;
}

/**
 * A JavaScript file with its comments removed and its string literals intact.
 *
 * ⛔ **THE ONE-LINE REGEX EVERY JS LINT IN THIS SUITE USED IS WRONG, AND IT IS
 * WRONG IN THE DIRECTION THAT MAKES A LINT PASS** (4575). Its second alternative
 * is `//[^\n]*` under the `s` modifier, which
 * treats the `//` in `https://…` as the start of a comment, so it deletes the
 * rest of that line — **including the call the line was making**. Found by
 * mutation, not by reading: planting
 * `window.fetch('https://ipinfo.example/json')` in `resources/js/pixel.js` left
 * *"the pixel makes no third-party request"* **green**, because the stripper had
 * already eaten the URL, the closing paren and everything after them. The one
 * mutation that matters most to a privacy lint over browser code — a call to
 * somebody else's host — was the one mutation that regex could not see, and
 * `29` §12.1's "never store raw IP" is exactly what an IP-echo service defeats.
 *
 * `phpWithoutComments()` beside this has always tokenized for the same reason
 * (`--` inside SQL); there was simply no JavaScript equivalent, so the JS lints
 * reached for the regex.
 *
 * ⚠️ **A DIVISION-VERSUS-REGEX-LITERAL AMBIGUITY IS NOT RESOLVED HERE AND SAYING
 * SO IS THE POINT** (352, 397). Distinguishing `a / b` from `/ab+/` needs the
 * parse state before the slash, which a scanner does not have. Neither
 * `pixel.js` nor `widget.js` contains a regex literal — the files this is used
 * on are deliberately dependency-free browser scripts — so a `/` that is not
 * `//` or `/*` is treated as an operator and left alone. **A regex literal
 * containing `//` or a quote would confuse this**, and the honest mitigation is
 * that adding one to either file is itself a reviewable act.
 */
function javascriptWithoutComments(string $js): string
{
    $out = '';
    $length = strlen($js);
    $i = 0;

    // '', "" and `` all behave the same for this purpose: a run that ends on its
    // own delimiter, with backslash escaping the next character.
    $quote = null;

    while ($i < $length) {
        $char = $js[$i];
        $next = $i + 1 < $length ? $js[$i + 1] : '';

        if ($quote !== null) {
            $out .= $char;

            if ($char === '\\') {
                // The escaped character is copied whole so a `\'` cannot close
                // the run and a `\\` cannot escape the delimiter after it.
                if ($next !== '') {
                    $out .= $next;
                    $i++;
                }
            } elseif ($char === $quote) {
                $quote = null;
            }

            $i++;

            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $out .= $char;
            $i++;

            continue;
        }

        if ($char === '/' && $next === '/') {
            while ($i < $length && $js[$i] !== "\n") {
                $i++;
            }

            continue;
        }

        if ($char === '/' && $next === '*') {
            $end = strpos($js, '*/', $i + 2);
            $i = $end === false ? $length : $end + 2;

            continue;
        }

        $out .= $char;
        $i++;
    }

    return $out;
}

/**
 * Every Blade file on the signed-out marketing surface, the shared layout
 * included.
 *
 * ⚠️ **IT LIVES HERE RATHER THAN IN A TEST FILE BECAUSE TWO FILES NEED IT.** It
 * was declared inside `MarketingPagesTest` when only that file read it; CC-2's
 * claim-law lint in `Architecture/MarketingTest` needs the identical set, and a
 * second global function of the same name is one of the four documented causes of
 * a run that prints zero bytes and exits non-zero (694, 808). `CLAUDE.md` states
 * the rule directly: shared helpers live here, and are never duplicated into a
 * domain file.
 *
 * @return list<SplFileInfo>
 */
function marketingViews(): array
{
    return array_merge(
        File::allFiles(resource_path('views/marketing')),
        File::allFiles(resource_path('views/components/marketing')),
    );
}

/**
 * Every customer-facing string this application ships in its own voice, labelled
 * with the template key it came from and the locale it is written in — CC-6 §1
 * and §2's shared corpus.
 *
 * ## ⛔ IT IS ONE FUNCTION BECAUSE THE TWO LAWS SCAN ONE SET
 *
 * `ClaimLawTest` refuses a figure in these strings and `WordLawTest` refuses a
 * word in them. Two enumerations of "the seeded corpus" would drift, and the
 * drift is silent in the worst direction: a fifth catalogue arriving would be
 * scanned by whichever list somebody remembered to extend, and the other law
 * would go on passing over a corpus it no longer covers. 960's argument for the
 * helper file, applied to a fixture rather than to a regex.
 *
 * ## ⚠️ THE LOCALE IS CARRIED RATHER THAN ASSUMED
 *
 * Every seeded review-ask template is Spanish today (LP-0's set), and an English
 * word law run over Spanish copy is the false-positive class that gets a lint
 * tuned until it catches nothing (511) — `expira` and `suspende` are ordinary
 * Spanish and neither is R48c's subject. So the locale travels with the string
 * and the caller decides, rather than this function deciding for both callers.
 *
 * ⚠️ **THE LADDER, THE PACKS AND THE MACROS ARE ALL `en`** and are labelled so
 * explicitly rather than defaulted, because a catalogue that grows a locale
 * column should have to come here and say what it is.
 *
 * ⚠️ **CAMPAIGN PACK MESSAGE BODIES ARE EMPTY TODAY AND THE NAMES ARE NOT.**
 * `CampaignPackCatalog` ships twelve packs with `messages => []` on purpose, so
 * a corpus built only from message bodies would be twelve empty lists — 256's
 * vacuum. The pack name and one-liner are authored and customer-facing, so those
 * are what carry the packs here, and the bodies join automatically the day they
 * are written.
 *
 * ⛔ **THIS SAID "AND RENDERED IN THE GALLERY" AND THERE IS NO GALLERY** (wave
 * 45). No screen renders a pack; `CampaignPacks::gallery()` has no caller
 * outside `tests/`. Two other artefacts said the same thing — `packs:sync` told
 * whoever ran the deploy that the names were *"live in the gallery"*, and
 * `CampaignPackCatalog` said *"what is missing is the copy, not the
 * mechanism"* — ⛔ **and that last one is the dangerous form, because TWO
 * absences are stacked and a file arguing there is one reads as proof the
 * mechanism is wired.** Both are deleted. **Authored and customer-facing is
 * still true, and is the whole reason this corpus takes them.**
 *
 * @return list<array{label: string, key: string, locale: string, text: string}>
 */
function seededCustomerFacingStrings(): array
{
    $strings = [];

    foreach (LifecycleRung::cases() as $rung) {
        $copy = LifecycleLadderCatalog::for($rung);

        foreach (['subject', 'text', 'email_insert'] as $part) {
            $value = $copy[$part] ?? null;

            if (is_string($value) && $value !== '') {
                $strings[] = [
                    'label' => "lifecycle {$rung->value} {$part}",
                    'key' => $rung->value,
                    'locale' => 'en',
                    'text' => $value,
                ];
            }
        }
    }

    foreach (ReviewAskCatalog::templates() as $template) {
        $strings[] = [
            'label' => "review ask {$template['key']} body",
            'key' => $template['key'],
            'locale' => $template['locale'],
            'text' => $template['body'],
        ];
    }

    foreach (CampaignPackCatalog::packs() as $pack) {
        $strings[] = [
            'label' => "campaign pack {$pack['key']} name",
            'key' => $pack['key'],
            'locale' => 'en',
            'text' => $pack['name'],
        ];

        $strings[] = [
            'label' => "campaign pack {$pack['key']} one-liner",
            'key' => $pack['key'],
            'locale' => 'en',
            'text' => $pack['one_liner'],
        ];

        foreach ($pack['messages'] as $index => $message) {
            $strings[] = [
                'label' => "campaign pack {$pack['key']} message {$index}",
                'key' => $pack['key'],
                'locale' => 'en',
                'text' => $message['body'],
            ];
        }
    }

    foreach (SupportMacroCatalog::macros() as $macro) {
        $strings[] = [
            'label' => "support macro {$macro['key']} title",
            'key' => $macro['key'],
            'locale' => 'en',
            'text' => $macro['title'],
        ];

        $strings[] = [
            'label' => "support macro {$macro['key']} body",
            'key' => $macro['key'],
            'locale' => 'en',
            'text' => $macro['body'],
        ];
    }

    return $strings;
}

/**
 * Every mention of the site-write switch, for the chokepoint that gives it one
 * name (5885).
 *
 * ⛔ **THE SUBJECT IS THE KEY, NOT THE WRITE.** `actuation.enabled` authorises
 * this platform to change other people's websites, and
 * {@see Publishing::authoriseSiteWrites()} takes PHP's
 * literal `true` so that no caller can turn it on without narrowing one — the
 * moment they have to check that a person pressed the second button. A caller
 * who can spell the key can write the row directly and never meet that type, so
 * what has to be scarce is the *name*: two files declare it, and a third
 * occurrence anywhere in `app/` is either a side door or a typo.
 *
 * ⚠️ **BOTH THE LITERAL AND THE CONSTANT**, because `Publishing::SWITCH_KEY` is
 * the tidier spelling of the same side door and is the one a careful author
 * would reach for.
 *
 * @return list<string>
 */
function siteWriteSwitchOffences(string $code, string $relative = 'fragment'): array
{
    $offences = [];

    if (str_contains($code, "'actuation.enabled'") || str_contains($code, '"actuation.enabled"')) {
        $offences[] = "{$relative}: names `actuation.enabled` itself";
    }

    // `\b` so a future `SITE_WRITE_SWITCH_KEY` is still seen, and so the
    // constant is caught however it is qualified.
    if (preg_match('/\b(?i:Publishing::)SWITCH_KEY\b/', $code) === 1) {
        $offences[] = "{$relative}: reaches Publishing::SWITCH_KEY";
    }

    return $offences;
}

/**
 * The files whose registry writes are not a door of their own (5904).
 *
 * ⚠️ **A LIST WITH A REASON PER ENTRY, THE RLS LINT'S SHAPE** (3146–3152). The
 * point of {@see registryWriteDoorOffences()} is that a *new* deliberate writer
 * has to be argued about, so the way past it is a line here rather than a
 * silence.
 *
 * @return array<string, string>
 */
function registryWriteDoorExemptions(): array
{
    return [
        // The registry writing itself — `resetToSeed()` routes through `set()`.
        'app/Services/Config/DefaultsRegistry.php' => 'is the registry',

        // `defaults:sync` writes the manifest seed for every declared key. It is
        // the reset path rather than a door, and its key is every key.
        'app/Console/Commands/SyncDefaultsRegistry.php' => 'writes every seed, and operates nothing',

        // The generic editor. It is what the registrations are subtracted from.
        'app/Livewire/Admin/PlatformSettings.php' => 'is the generic editor',

        // ⛔ **THE THIRD ANSWER TO THIS QUESTION, AND THE REASON IT IS NOT A
        // GAP** (5883, 5902). The site-write switch's two-step confirmation
        // lives *inside* the generic editor, so registering it in
        // `OperatedElsewhere` would tell that editor to refuse the door it
        // already is. ⚠️ This entry is keyed on the file rather than the key on
        // purpose: 5892 keeps that key's name scarce, and a lint naming it would
        // be the side door the scarcity exists to prevent.
        'app/Services/Content/Publishing.php' => 'its confirmation is the editor’s own second press (5883)',

        // ⛔ **THE FOURTH ANSWER, AND IT IS THE THIRD ONE'S EXACTLY** (11705).
        // The five capability claim switches gained a two-step confirmation that
        // also lives *inside* the generic editor, so registering them in
        // `OperatedElsewhere` would tell that editor to refuse the door it
        // already is. ⚠️ Keyed on the file rather than on the keys for the reason
        // the entry above gives, with one addition: there are **five** keys here,
        // and a lint spelling all five would be a third file naming them — which
        // is what `MarketingTest`'s *"only the enum and the manifest spell a
        // capability claim flag key"* exists to prevent.
        'app/Services/Marketing/MarketingClaims.php' => 'its confirmation is the editor’s own second press (11705)',
    ];
}

/**
 * Every registry key written outside the generic editor that has no door
 * registered for it (5904).
 *
 * ## ⛔ WHY THE DERIVATION IS INVERTED
 *
 * The defect this closes was not a forgotten key — it was that
 * {@see PlatformSettings} gained authority over a *derived
 * set* (every boolean-seeded key) that silently included switches argued about
 * elsewhere. A lint that listed the keys to protect would have the same problem
 * one layer up: it would pass on the day somebody adds the next one. **So the
 * subject is derived from the writes**, exactly as *every table without
 * row-level security is a named exception* is derived from `pg_class` rather
 * than from `app/Models` (3146–3152). A new `DefaultsRegistry::set()` anywhere
 * in `app/` reddens the build until somebody says which door it is.
 *
 * ⚠️ **IT READS THE PARSE TREE AND NEVER THE PROSE.** Two lints written on
 * 2026-08-20 matched a docblock that *mentioned* a key and read it as a use of
 * one. This finds `MethodCall` nodes named `set` with three arguments — the
 * shape of {@see DefaultsRegistry::set()} — inside files
 * that reference that class, and resolves the key argument through
 * `constant()`. A comment cannot be a `MethodCall`.
 *
 * ⛔ **WHAT IT CANNOT SEE, SAID RATHER THAN GLOSSED** — 5886's rule, about the
 * lint one slice earlier. Its subject list is files whose text mentions
 * `DefaultsRegistry`, so a writer that reaches the service without ever naming
 * it — `app('…')->set(…)` behind a string, or a union alias — is invisible to
 * it. That is a deliberate trade against the alternative, which is treating
 * every three-argument `->set()` in `app/` as a registry write and reddening on
 * caches and collections until somebody tunes it until it catches nothing (511).
 *
 * ⚠️ **A VARIABLE KEY IS NOT WAVED THROUGH.** `SendingControls::releasePlatform()`
 * loops over both halt constants and writes `$key`, which no static read can
 * resolve — so the fallback is every registry-key class constant named in the
 * *same method*, and a method that writes a variable while naming no registry
 * constant at all is itself the offence. A file that genuinely cannot be read
 * this way belongs in {@see registryWriteDoorExemptions()} with its reason.
 *
 * @return list<string>
 */
function registryWriteDoorOffences(): array
{
    $declared = array_flip(array_merge(
        array_keys(DefaultsManifest::settings()),
        array_keys(DefaultsManifest::declaredWithoutSeed()),
    ));

    $exempt = registryWriteDoorExemptions();

    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $finder = new NodeFinder;

    $offences = [];

    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = 'app/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());

        if (array_key_exists($relative, $exempt)) {
            continue;
        }

        $source = $file->getContents();

        // Only files that can hold a `DefaultsRegistry` — this is what keeps a
        // three-argument `->set()` on some unrelated collection out of the
        // subject list, rather than a guess about the receiver's name.
        if (! str_contains($source, 'DefaultsRegistry')) {
            continue;
        }

        $ast = $parser->parse($source);

        if ($ast === null) {
            continue;
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $ast = $traverser->traverse($ast);

        foreach ($finder->find($ast, fn (Node $n): bool => $n instanceof Class_) as $class) {
            // `NameResolver` deliberately leaves `self::` alone, so the class it
            // is written in is what resolves it.
            $self = $class->namespacedName?->toString();

            foreach ($finder->find($class, fn (Node $n): bool => $n instanceof ClassMethod) as $method) {
                $where = $relative.' — '.($class->name?->toString() ?? 'anonymous class')
                    .'::'.$method->name->toString().'()';

                $writes = $finder->find($method, fn (Node $n): bool => $n instanceof MethodCall
                    && $n->name instanceof Identifier
                    && $n->name->toString() === 'set'
                    && count($n->args) === 3);

                if ($writes === []) {
                    continue;
                }

                $keys = [];
                $unresolved = false;

                foreach ($writes as $write) {
                    $argument = registryWriteKeyArgument($write);
                    $resolved = registryKeyOfExpression($argument, $self, $declared);

                    if ($resolved === null) {
                        $unresolved = true;

                        continue;
                    }

                    $keys[] = $resolved;
                }

                if ($unresolved) {
                    // The fallback: every registry key this method can name.
                    foreach ($finder->find($method, fn (Node $n): bool => $n instanceof ClassConstFetch) as $constant) {
                        $resolved = registryKeyOfExpression($constant, $self, $declared);

                        if ($resolved !== null) {
                            $keys[] = $resolved;
                        }
                    }

                    if ($keys === []) {
                        $offences[] = "{$where} writes the registry with a key this lint cannot see — "
                            .'name the key as a class constant in this method, or list the file in '
                            .'registryWriteDoorExemptions() with the reason it is not a door';

                        continue;
                    }
                }

                foreach (array_unique($keys) as $key) {
                    if (OperatedElsewhere::has($key)) {
                        continue;
                    }

                    $offences[] = "{$where} writes `{$key}`, which the generic settings editor can also move";
                }
            }
        }
    }

    sort($offences);

    return array_values(array_unique($offences));
}

/**
 * The key argument of one `set()` call, positional or named.
 *
 * ⚠️ **NAMED ARGUMENTS ARE THE WHOLE REASON THIS IS NOT `$args[0]`.** PHP lets a
 * caller write `set(actor: $a, value: $v, key: $k)`, which is legal, in use
 * elsewhere in this codebase, and would hand position zero to a lint expecting a
 * key — reporting an unreadable write where there is a perfectly readable one,
 * or worse, resolving the actor and finding nothing.
 */
function registryWriteKeyArgument(MethodCall $write): ?Node
{
    $positional = null;

    foreach ($write->args as $position => $argument) {
        if (! $argument instanceof Arg) {
            continue;
        }

        if ($argument->name?->toString() === 'key') {
            return $argument->value;
        }

        if ($argument->name === null && $position === 0) {
            $positional = $argument->value;
        }
    }

    // A named-argument call that never says `key:` cannot be one of ours.
    return $positional;
}

/**
 * One `set()` key argument, resolved to a declared registry key or null.
 *
 * ⚠️ **`defined()` BEFORE `constant()`**, because a constant on a class that
 * does not autoload throws rather than returning null, and a lint that fatals on
 * a rename is one nobody can read the failure of.
 *
 * @param  array<string, int>  $declared
 */
function registryKeyOfExpression(?Node $expression, ?string $self, array $declared): ?string
{
    $value = null;

    if ($expression instanceof String_) {
        $value = $expression->value;
    }

    if ($expression instanceof ClassConstFetch
        && $expression->class instanceof Name
        && $expression->name instanceof Identifier) {
        $class = $expression->class->toString();

        if (($class === 'self' || $class === 'static') && $self !== null) {
            $class = $self;
        }

        $constant = $class.'::'.$expression->name->toString();

        if (defined($constant)) {
            $resolved = constant($constant);
            $value = is_string($resolved) ? $resolved : null;
        }
    }

    return $value !== null && array_key_exists($value, $declared) ? $value : null;
}

/**
 * Every PHP file the WordPress plugin ships.
 *
 * ⚠️ **DERIVED FROM THE DIRECTORY, NEVER FROM A LIST**, so a file added to
 * `plugins/wordpress/` is scanned the moment it exists rather than the moment
 * somebody remembers to name it. An enumerated list is 5842's finding: the lint
 * that passes on the tree it was written for **and** on the defect it exists to
 * catch.
 *
 * @return array<string, string> Path relative to the repository root => contents.
 */
function wordPressPluginSources(): array
{
    $root = base_path('plugins/wordpress');

    if (! is_dir($root)) {
        return [];
    }

    $sources = [];

    foreach (File::allFiles($root) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $sources['plugins/wordpress/'.$file->getRelativePathname()] = (string) file_get_contents($file->getPathname());
    }

    ksort($sources);

    return $sources;
}

/**
 * Function calls the WordPress plugin may never make.
 *
 * Matched as a function name followed by `(` — never as a method call, never as
 * a declaration, and never inside a string or a comment.
 *
 * ⛔ **THIS SAID *"A BARE FUNCTION NAME"* AND THAT WAS THE DEFECT WRITTEN DOWN
 * AS A DESIGN.** Every spelling PHP resolves to the global function is matched:
 * `shell_exec`, `\shell_exec` and `namespace\shell_exec`. A name carrying a
 * namespace is a different symbol and is answered by its own arm rather than by
 * this list — {@see forbiddenPluginConstructs()} for which spellings reach a
 * global symbol and why.
 *
 * ⚠️ **THE KEYS' CASE DOES NOT MATTER AND MUST NOT BE MADE TO**: the lookup
 * lowercases both sides, because PHP dispatches a function name
 * case-insensitively and an array key lookup does not (10203).
 *
 * @var array<string, string>
 */
const GOAIEZ_PLUGIN_FORBIDDEN_CALLS = [
    // Running code that arrived from somewhere else.
    'create_function' => 'runs a string as code',
    'assert' => 'runs a string as code on older PHP and is a debugging tool either way',
    'system' => 'runs a shell command',
    'exec' => 'runs a shell command',
    'shell_exec' => 'runs a shell command',
    'passthru' => 'runs a shell command',
    'proc_open' => 'runs a shell command',
    'popen' => 'runs a shell command',
    'pcntl_exec' => 'runs a shell command',
    'dl' => 'loads a PHP extension at runtime',
    'unserialize' => 'instantiates classes from a string and is a remote-code-execution primitive',
    'extract' => 'creates variables from an array, which is how request input becomes a variable name',
    'call_user_func' => 'dispatches through a value rather than a name, which is the variable-function arm wearing a string',
    'call_user_func_array' => 'dispatches through a value rather than a name, which is the variable-function arm wearing a string',
    'putenv' => 'changes the environment of a process that is not ours',
    'ini_set' => 'changes a customer server\'s PHP configuration',

    // Obfuscation. Guideline 4: "Code must be (mostly) human readable."
    'base64_decode' => 'is how unreadable code is smuggled past a reviewer',
    'gzinflate' => 'is how unreadable code is smuggled past a reviewer',
    'gzuncompress' => 'is how unreadable code is smuggled past a reviewer',
    'str_rot13' => 'is how unreadable code is smuggled past a reviewer',

    // Reaching out. This plugin answers requests and makes none.
    'wp_remote_get' => 'opens an outbound connection',
    'wp_remote_post' => 'opens an outbound connection',
    'wp_remote_head' => 'opens an outbound connection',
    'wp_remote_request' => 'opens an outbound connection',
    'wp_safe_remote_get' => 'opens an outbound connection',
    'wp_safe_remote_post' => 'opens an outbound connection',
    'wp_safe_remote_head' => 'opens an outbound connection',
    'wp_safe_remote_request' => 'opens an outbound connection',
    'curl_init' => 'opens an outbound connection',
    'curl_exec' => 'opens an outbound connection',
    'curl_setopt' => 'opens an outbound connection',
    'fsockopen' => 'opens an outbound connection',
    'pfsockopen' => 'opens an outbound connection',
    'stream_socket_client' => 'opens an outbound connection',
    'download_url' => 'downloads a file to a customer server',

    // Writing to somebody else\'s filesystem.
    'file_put_contents' => 'writes to a customer\'s filesystem',
    'fopen' => 'opens a file handle on a customer\'s filesystem',
    'fwrite' => 'writes to a customer\'s filesystem',
    'fputs' => 'writes to a customer\'s filesystem',
    'unlink' => 'deletes from a customer\'s filesystem',
    'rename' => 'moves a file on a customer\'s filesystem',
    'copy' => 'writes to a customer\'s filesystem',
    'mkdir' => 'writes to a customer\'s filesystem',
    'rmdir' => 'deletes from a customer\'s filesystem',
    'chmod' => 'changes permissions on a customer\'s filesystem',
    'touch' => 'writes to a customer\'s filesystem',
    'symlink' => 'writes to a customer\'s filesystem',
    'tempnam' => 'writes to a customer\'s filesystem',
    'tmpfile' => 'writes to a customer\'s filesystem',
    'move_uploaded_file' => 'writes to a customer\'s filesystem',
    'unzip_file' => 'writes to a customer\'s filesystem',
    'wp_mkdir_p' => 'writes to a customer\'s filesystem',
    'wp_delete_file' => 'deletes from a customer\'s filesystem',
    'file_get_contents' => 'reads a path, and a path can be a URL',
    'readfile' => 'reads a path, and a path can be a URL',

    // Updating itself, or anything else that runs code on the site.
    'activate_plugin' => 'activates plugin code',
    'activate_plugins' => 'activates plugin code',
    'deactivate_plugins' => 'deactivates plugin code',
    'delete_plugins' => 'deletes plugin code',
    'wp_update_plugins' => 'updates plugin code',
    'plugins_api' => 'asks for plugin code to install',
    'switch_theme' => 'changes which theme runs',
];

/**
 * Identifiers the plugin may never name at all, wherever they appear in code.
 *
 * @var array<string, string>
 */
const GOAIEZ_PLUGIN_FORBIDDEN_IDENTIFIERS = [
    'Plugin_Upgrader' => 'installs or updates plugin code',
    'Theme_Upgrader' => 'installs or updates theme code',
    'Core_Upgrader' => 'updates WordPress itself',
    'WP_Filesystem' => 'is write access to a customer\'s filesystem',
    'WP_Http' => 'opens an outbound connection',
    'Requests' => 'opens an outbound connection',
];

/**
 * Every forbidden construct in a plugin source file, as `file:line: reason`.
 *
 * ⛔ **TOKEN-BASED RATHER THAN A GREP, BECAUSE THIS FILE'S NEIGHBOURS EXPLAIN
 * THEMSELVES AT LENGTH.** A lint over raw text reddens on the sentence *"there
 * is no `eval` here"* and gets tuned until it catches nothing (511) — and it has
 * happened twice on this chain in two days (5811, 5841). `token_get_all()` is a
 * lexer: a comment is `T_COMMENT`, a string is `T_CONSTANT_ENCAPSED_STRING`, and
 * `eval` is `T_EVAL`. None of the three can be mistaken for another, so the
 * distinction is structural rather than a cleverer regex.
 *
 * ⛔ **AND A LEXER HAS ITS OWN BLIND SPOT: ON PHP 8 A NAMESPACE-QUALIFIED
 * NAME IS NOT `T_STRING`.** `shell_exec(…)` lexes as `T_STRING`;
 * `\shell_exec(…)` lexes as `T_NAME_FULLY_QUALIFIED`, and this walk
 * skipped every token that was not `T_STRING` — so **every entry in both lists
 * above was bypassed by one leading backslash** and
 * `\passthru( \base64_decode( … ) )` passed the build-failing test that
 * cites `29` §2 rule 34. ⚠️ **The shipped plugin never held a backslash, so
 * there was no occupant and nothing vulnerable was ever released** — it was a
 * permitted future violation, which is the only kind a lint can have.
 * ⛔ **The reason it survived is the axis, not the token id**: every fragment
 * the sibling test planted **was** unqualified, because they were written from
 * the same reading of PHP as the matcher, so **the example set shared the
 * matcher's blind spot exactly.** Every arm that names a symbol now carries a
 * qualified plant beside its unqualified one, and
 * `PluginSourceTest` asks the lists themselves for their own coverage.
 *
 * ⛔ **TWO COUNTS IN THIS DOCBLOCK WERE BORN STALE IN ONE COMMIT — CORRECTED
 * 12211.** The *"every entry in both lists"* sentence above read *"all
 * sixty-six entries"*. `git log -S` puts that sentence and two of the entries it
 * counts in a single commit, `a3074d52`, so **the two lists were already longer
 * than the figure claimed on the day the figure was written** — and the tense
 * reads present, over lists a later reader would count. **The number did no
 * work the word *every* does not do better**, and *every* cannot go stale: the
 * claim is about what the walk skipped, not about how long the lists are.
 * ⚠️ **A count typed beside a list that enumerates itself cannot notice the
 * list moving, and this one could not notice it moving in its own commit.**
 * ⛔ **AND IT WAS NOT THE ONLY ONE IN THAT COMMIT.** The paragraph above read
 * *"all twenty-three fragments the sibling test plants **are** unqualified"* —
 * present tense over a set the same commit rewrote from twenty-three fragments
 * to more, and contradicted by its own next sentence. It is a **historical**
 * claim, so the tense is what was wrong; the figure went with it because the
 * word *every* carries the claim and cannot age. ⚠️ **The identical sentence
 * survives in `PluginSourceTest`, which this lane could not edit.**
 *
 * @return list<string>
 */
function forbiddenPluginConstructs(string $php, string $relative = 'a file'): array
{
    $tokens = token_get_all($php);
    $offenders = [];

    $report = static function (int $line, string $what, string $why) use (&$offenders, $relative): void {
        $offenders[] = $relative.':'.$line.': '.$what.' — '.$why;
    };

    // ⛔ **PHP DISPATCHES A FUNCTION NAME CASE-INSENSITIVELY AND AN ARRAY KEY
    // LOOKUP DOES NOT** (10203). The token is lowercased below and the key was
    // not, so an entry added to `GOAIEZ_PLUGIN_FORBIDDEN_CALLS` as
    // `'WP_Remote_Get'` would have been born dead and silent — while the
    // identifier arm a dozen lines down lowercases both sides and is right.
    // ⚠️ **Every key is lowercase today and nothing asserted it**, which is
    // what makes the asymmetry invisible; the guard is now derived from the
    // lists in `PluginSourceTest` rather than written about them.
    $calls = array_change_key_case(GOAIEZ_PLUGIN_FORBIDDEN_CALLS, CASE_LOWER);

    // The significant tokens, with their positions, so "the next thing" skips
    // whitespace and comments without the caller doing it each time.
    $significant = [];

    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $significant[] = $token;
    }

    $count = count($significant);

    for ($i = 0; $i < $count; $i++) {
        $token = $significant[$i];
        $next = $significant[$i + 1] ?? null;
        $previous = $significant[$i - 1] ?? null;

        // A backtick is `shell_exec` wearing punctuation.
        // ⚠️ A BACKTICK HAS NO LINE NUMBER — `token_get_all()` returns bare
        // punctuation as a plain string with no position — so this one arm
        // reports 0. It is the only construct here that cannot name its line.
        if ($token === '`') {
            $report(0, 'a backtick', 'runs a shell command');

            continue;
        }

        // `$$name` — the value of one variable used as the name of another.
        if ($token === '$' && is_array($next) && $next[0] === T_VARIABLE) {
            $report((int) $next[2], 'a variable variable', 'turns a value into a symbol name');

            continue;
        }

        if (! is_array($token)) {
            continue;
        }

        [$id, $text, $line] = [$token[0], $token[1], $token[2]];

        if ($id === T_EVAL) {
            $report((int) $line, 'eval', 'runs a string as code');

            continue;
        }

        // `$callback( … )` and `$this->$method( … )`: dispatch through a value.
        if ($id === T_VARIABLE && $next === '(') {
            $report((int) $line, 'a variable function call', 'dispatches through a value rather than a name');

            continue;
        }

        // `$class::run( … )`: the same dispatch through a value, one operator
        // over. ⚠️ **THE ARM ABOVE CLAIMED THIS GROUND IN ITS REASON AND DID
        // NOT COVER IT** — `$c = 'WP_Filesystem'; $c::run();` reaches neither
        // the identifier list below (there is no identifier to read) nor the
        // call above (the next token is `::`, not `(`). Measured, not reasoned.
        if ($id === T_VARIABLE && is_array($next) && $next[0] === T_DOUBLE_COLON) {
            $report((int) $line, 'a variable static call', 'dispatches through a value rather than a name');

            continue;
        }

        if (in_array($id, [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
            for ($j = $i + 1; $j < $count; $j++) {
                $argument = $significant[$j];

                if ($argument === ';') {
                    break;
                }

                if (is_array($argument) && $argument[0] === T_VARIABLE) {
                    $report((int) $line, 'an include of a computed path', 'a path built from a value can be a path somebody sent us');

                    break;
                }

                if (is_array($argument)
                    && in_array($argument[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
                    && str_contains($argument[1], '://')) {
                    $report((int) $line, 'an include of a URL', 'loads code from somewhere else');

                    break;
                }
            }

            continue;
        }

        if (! in_array($id, [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE], true)) {
            continue;
        }

        // ⛔ **THE TWO LISTS NAME GLOBAL SYMBOLS, SO THE QUESTION IS WHICH
        // SPELLINGS REACH ONE — AND PHP'S NAME RESOLUTION DECIDES THAT, NOT
        // WHICHEVER SPELLING LOOKS LIKE AN EVASION.**
        //
        //   `shell_exec`            unqualified: a function name falls back to
        //                           the global namespace, so this is the global
        //                           one — the only spelling matched before this
        //   `\shell_exec`           fully qualified, one segment: the global one
        //   `namespace\shell_exec`  relative: the CURRENT namespace, which in
        //                           this plugin is the global one. ⚠️ **That is
        //                           a premise rather than a fact about PHP**, and
        //                           the lint beside this one holds it by asserting
        //                           the plugin declares no namespace at all
        //   `Foo\shell_exec`        qualified: a DIFFERENT symbol. Qualified and
        //                           relative names do **not** fall back to the
        //                           global namespace, so this cannot be the
        //                           forbidden function — and reporting it as one
        //                           would put a false sentence in a failure
        //                           message, which is how a lint gets tuned until
        //                           it catches nothing (511)
        //
        // So a name carrying a namespace gets its own arm and its own reason,
        // and is never relabelled as the function it merely resembles.
        $qualified = $id === T_NAME_RELATIVE ? substr($text, strlen('namespace\\')) : $text;
        $segments = explode('\\', ltrim($qualified, '\\'));

        if (count($segments) > 1) {
            $report(
                (int) $line,
                'the namespaced name '.$text,
                'names a symbol inside a namespace and this plugin declares none, so it resolves to nothing — `php -l` cannot see that, and the two lists this lint holds are written about global symbols'
            );

            continue;
        }

        // A method call and a declaration are not calls to the global function
        // of the same name. `$wpdb->prepare()` and `self::verify()` are the two
        // shapes this plugin actually uses, and without this arm the lint would
        // redden on every one of them.
        $isMember = is_array($previous)
            && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR], true);
        $isDeclaration = is_array($previous)
            && in_array($previous[0], [T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT], true);

        $lower = strtolower($segments[0]);

        foreach (GOAIEZ_PLUGIN_FORBIDDEN_IDENTIFIERS as $identifier => $why) {
            if ($lower === strtolower($identifier)) {
                $report((int) $line, $identifier, $why);
            }
        }

        if ($next !== '(' || $isMember || $isDeclaration) {
            continue;
        }

        if (isset($calls[$lower])) {
            $report((int) $line, $lower.'()', $calls[$lower]);
        }
    }

    sort($offenders);

    return $offenders;
}

/**
 * The `const NAME = array( … );` string list a plugin class declares.
 *
 * ⚠️ **PARSED RATHER THAN `require`d**, because these files open with
 * `defined( 'ABSPATH' ) || exit;` and a helper that loaded one would end the
 * test run rather than fail it.
 *
 * @return list<string>
 */
function pluginConstantList(string $php, string $constant): array
{
    $matched = preg_match(
        '/const\s+'.preg_quote($constant, '/').'\s*=\s*array\((.*?)\);/s',
        phpWithoutComments($php),
        $matches
    );

    if ($matched !== 1) {
        return [];
    }

    preg_match_all("/'([^']*)'/", $matches[1], $values);

    return $values[1];
}

/**
 * One method's body, sliced out of already-comment-stripped PHP source.
 *
 * ⚠️ **BRACE COUNTING RATHER THAN A PARSER, AND IT IS ENOUGH BECAUSE THE INPUT
 * IS STRIPPED.** A brace inside a comment or a string is the reason this shape
 * usually fails; comments are gone before this runs, and the WordPress plugin's
 * method bodies contain no string literal with an unbalanced brace in it — which
 * is asserted by every caller getting a non-empty body back.
 */
function pluginMethodBody(string $strippedPhp, string $method): string
{
    $start = strpos($strippedPhp, 'function '.$method.'(');

    if ($start === false) {
        return '';
    }

    $open = strpos($strippedPhp, '{', $start);

    if ($open === false) {
        return '';
    }

    $depth = 0;
    $length = strlen($strippedPhp);

    for ($i = $open; $i < $length; $i++) {
        $depth += $strippedPhp[$i] === '{' ? 1 : 0;
        $depth -= $strippedPhp[$i] === '}' ? 1 : 0;

        if ($depth === 0) {
            return substr($strippedPhp, $open, $i - $open + 1);
        }
    }

    return '';
}

/*
|--------------------------------------------------------------------------
| Policies that nothing asks (6442)
|--------------------------------------------------------------------------
|
| ⛔ **AN UNCALLED POLICY IS WORSE THAN A MISSING ONE.** It reads as
| enforcement, other policies cite it as the pattern they follow, and it is what
| stops the next reviewer looking — `CLAUDE.md`'s 314–316, whose fourth instance
| was a security defect for exactly that reason. On 2026-08-20
| `AutopilotSettingsPolicy` had **zero** call sites in `app/` while seven other
| policy files named it in their docblocks, and `OauthConnectionPolicy` had none
| either.
|
| ⚠️ **RESOLVED BY MODEL TYPE, NEVER BY THE ABILITY STRING OR THE POLICY'S
| NAME.** `$this->authorize('update', $conversation)` carries neither, and a grep
| for `TriageConversationPolicy` finds the docblock of a *different* policy
| citing it — the false positive the scout that found this defect actually hit.
| So the subject is the second argument of each authorization call, resolved to a
| class and handed to Laravel's own `Gate::getPolicyFor()`. The framework answers
| which policy a model reaches; this lint never guesses the mapping, which
| matters because there is no `Gate::policy()` call anywhere in this application
| and the mapping is therefore a naming convention.
|
| ⚠️ **AN ARGUMENT THIS CANNOT RESOLVE IS AN OFFENCE, NOT A SHRUG** (256, 511).
| Treating *"I cannot tell"* as *"probably fine"* is what makes a lint pass
| vacuously: one unreadable call site would let every policy behind it report as
| called. Reporting it costs one typed variable at the call site — it has already
| bought one, in `Account\ReviewRules::render()` — and keeps the answer honest.
| Five of the call sites in `app/` today reach their model through a variable, a
| helper's return type or a service's, so a literal-matching lint would have
| cried wolf about five policies that are asked perfectly well.
|
| ⛔ **AND THERE IS DELIBERATELY NO EXEMPTION LIST**, which is where this differs
| from `registryWriteDoorExemptions()`. A policy has a caller or it is deleted;
| an allowlist would be the third way, and the third way is how the file the
| whole authorization layer points at as its reference went a month without ever
| being invoked.
|
| ## ⚠️ WHAT IT CANNOT SEE, STATED SO NOBODY READS MORE INTO A GREEN RUN
|
|   - It scans `app/` only. A policy whose only caller is a **test** is not
|     enforced, and a two-argument Blade `@can` would be missed — there are none
|     in `resources/views` today.
|   - It says nothing about whether the call site is on the **right** path. One
|     `Gate::authorize()` passes this lint while four other write methods on the
|     same screen stay open, which is the exact state `Account\ReviewRules` was
|     in relative to its five siblings.
|   - It cannot see a `Gate::define()` nobody consults, which is the same defect
|     in a different mechanism — `viewHorizon` was dead code on 2026-08-19 for
|     precisely that reason. Widening this to chase gates in a codebase whose
|     admin gates are consulted through `AdminAccess::GATE` constants would be a
|     lint tuned until it caught nothing (511), so the limit is written down
|     instead.
|   - The receiver of an undeclared method call falls back to the receiver's own
|     class, which is how `Model::query()->findOrFail()` is read. It is bounded
|     to Eloquent models on purpose: a builder chain only ever starts at one, and
|     without the bound this would credit a policy for any chain that happened to
|     begin at a model.
|
*/

/**
 * The marker for a variable this lint has read and cannot type.
 *
 * A function rather than a constant because this file is `require`d rather than
 * autoloaded, and a file-scope `const` fatals on a second include.
 */
function unresolvedTypeMarker(): string
{
    return "\0unresolved";
}

/**
 * The marker for a literal `null`, which narrows nothing.
 *
 * `$settings = null;` before an `if` is not a claim that `$settings` is not a
 * model, and `$x === null ? null : $model` is a `?Model` whose null half the
 * call site has already refused.
 */
function nullLiteralMarker(): string
{
    return "\0null";
}

/**
 * The abilities-taking methods whose second argument names a model.
 *
 * `Gate::authorize($ability, $arguments)`, `User::can($abilities, $arguments)`
 * and their siblings all put the subject second. A one-argument call is a gate —
 * `$this->authorize(AdminAccess::GATE)` — and carries no model at all.
 *
 * @return list<string>
 */
function authorizationCallNames(): array
{
    return ['authorize', 'authorizeForUser', 'allows', 'denies', 'check', 'any', 'can', 'cannot', 'cant'];
}

/**
 * Classes whose appearance in a chain means the chain is not finished yet.
 *
 * A builder, a relation or a collection is plumbing between a model and the row
 * that comes out of it, so a method declared to return one is read as *"still
 * the receiver's model"* rather than as an answer.
 *
 * @return list<string>
 */
function eloquentPlumbingClasses(): array
{
    return [
        Builder::class,
        Illuminate\Database\Query\Builder::class,
        Relation::class,
        Illuminate\Database\Eloquent\Collection::class,
        Collection::class,
    ];
}

/**
 * Every class file under `app/Policies`, keyed by fully-qualified name.
 *
 * ⚠️ **DERIVED FROM THE DIRECTORY, NEVER FROM A LIST.** A policy added tomorrow
 * is under this lint the moment it exists rather than the moment somebody
 * remembers to name it — 5842's finding.
 *
 * @return array<string, string> FQCN => path relative to the repository root.
 */
function policyClasses(): array
{
    $classes = [];

    foreach (File::allFiles(app_path('Policies')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $name = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $file->getRelativePathname());

        $classes['App\\Policies\\'.$name] = 'app/Policies/'
            .str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());
    }

    ksort($classes);

    return $classes;
}

/**
 * Every policy `app/` never asks, and every authorization call this cannot read.
 *
 * @return list<string>
 */
function uncalledPolicyOffences(): array
{
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $finder = new NodeFinder;

    $asked = [];
    $offences = [];

    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = 'app/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());

        $ast = $parser->parse($file->getContents());

        if ($ast === null) {
            continue;
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $ast = $traverser->traverse($ast);

        foreach ($finder->find($ast, fn (Node $n): bool => $n instanceof Class_) as $class) {
            // `NameResolver` leaves `self::` and `$this` alone, so the class
            // they are written in is what resolves them.
            $self = $class->namespacedName?->toString();
            $members = classMemberTypes($class, $finder);

            foreach ($finder->find($class, fn (Node $n): bool => $n instanceof ClassMethod) as $method) {
                $where = $relative.' — '.($class->name?->toString() ?? 'anonymous class')
                    .'::'.$method->name->toString().'()';

                $scope = methodVariableTypes($method, $finder, $self, $members);

                foreach (authorizationCalls($method, $finder) as $call) {
                    $argument = authorizationSubjectArgument($call);

                    if ($argument === null) {
                        // A one-argument call, or a named-argument call that
                        // never says `arguments:`. Either way, a gate.
                        continue;
                    }

                    $resolved = authorizationSubjectClass($argument, $self, $members, $scope);

                    if ($resolved === '') {
                        // Read, and found not to be a class — a gate's own
                        // argument, which no policy answers.
                        continue;
                    }

                    if ($resolved === null) {
                        $offences[] = "{$where} authorizes against something this lint cannot resolve to a "
                            .'model, so it cannot tell which policy the call reaches — assign the model to '
                            .'a variable or a helper with a declared type, or pass `Model::class`';

                        continue;
                    }

                    $policy = Gate::getPolicyFor($resolved);

                    if ($policy !== null) {
                        $asked[$policy::class] = true;
                    }
                }
            }
        }
    }

    foreach (policyClasses() as $policy => $path) {
        if (array_key_exists($policy, $asked)) {
            continue;
        }

        $offences[] = "{$path} has no call site anywhere in app/ — a policy nothing asks reads as "
            .'enforcement and is not any (314–316). Give it a caller or delete it';
    }

    sort($offences);

    return array_values(array_unique($offences));
}

/**
 * Every `authorize`/`allows`/`can` call inside one method, closures included.
 *
 * ⚠️ **CLOSURES ARE WHY THIS SEARCHES THE WHOLE SUBTREE.**
 * `Admin\SendingControls::releaseTenant()` asks `release` from inside a closure
 * handed to `act()`, and a lint reading only top-level statements would have
 * reported `SendingPausePolicy` as uncalled — the exact false positive this file
 * exists to avoid.
 *
 * @return list<MethodCall|StaticCall>
 */
function authorizationCalls(ClassMethod $method, NodeFinder $finder): array
{
    $names = authorizationCallNames();

    /** @var list<MethodCall|StaticCall> $calls */
    $calls = $finder->find($method, fn (Node $n): bool => ($n instanceof MethodCall || $n instanceof StaticCall)
        && $n->name instanceof Identifier
        && in_array($n->name->toString(), $names, true)
        && count($n->args) >= 2);

    return $calls;
}

/**
 * The model argument of one authorization call, positional or named.
 *
 * ⚠️ **NAMED ARGUMENTS ARE WHY THIS IS NOT `$args[1]`** — the same reason
 * `registryWriteKeyArgument()` gives. The array form is unwrapped because
 * Laravel takes `$arguments` as *either* one subject or a list of them, so
 * `allows('update', [$post, $comment])` names its policy in position zero.
 */
function authorizationSubjectArgument(MethodCall|StaticCall $call): ?Node
{
    $subject = null;

    foreach ($call->args as $position => $argument) {
        if (! $argument instanceof Arg) {
            continue;
        }

        if ($argument->name?->toString() === 'arguments') {
            $subject = $argument->value;

            break;
        }

        if ($argument->name === null && $position === 1) {
            $subject = $argument->value;
        }
    }

    if ($subject instanceof ArrayNode) {
        $first = $subject->items[0] ?? null;

        $subject = $first instanceof ArrayItem ? $first->value : null;
    }

    return $subject;
}

/**
 * Declared return types of every method and declared types of every property on
 * one class, as a class name or `''` for a builtin.
 *
 * Promoted constructor properties are included: they are properties, and one is
 * an obvious subject for an `authorize()` in a controller.
 *
 * @return array{methods: array<string, string>, properties: array<string, string>}
 */
function classMemberTypes(Class_ $class, NodeFinder $finder): array
{
    $methods = [];
    $properties = [];

    foreach ($finder->find($class, fn (Node $n): bool => $n instanceof ClassMethod) as $method) {
        $type = declaredTypeName($method->returnType);

        if ($type !== null) {
            $methods[$method->name->toString()] = $type;
        }
    }

    foreach ($finder->find($class, fn (Node $n): bool => $n instanceof PropertyNode) as $property) {
        $type = declaredTypeName($property->type);

        if ($type === null) {
            continue;
        }

        foreach ($property->props as $declared) {
            $properties[$declared->name->toString()] = $type;
        }
    }

    foreach ($finder->find($class, fn (Node $n): bool => $n instanceof Param
        && $n->flags !== 0
        && $n->var instanceof Variable
        && is_string($n->var->name)) as $promoted) {
        $type = declaredTypeName($promoted->type);

        if ($type !== null && $promoted->var instanceof Variable && is_string($promoted->var->name)) {
            $properties[$promoted->var->name] = $type;
        }
    }

    return ['methods' => $methods, 'properties' => $properties];
}

/**
 * One declared type, reduced to a class name.
 *
 * ⚠️ **`?Plugin` AND `Plugin` ARE THE SAME ANSWER HERE**, because the call site
 * has already refused the null — `Account\WidgetInstall` aborts 404 on it two
 * lines above its `Gate::authorize()`. A union is deliberately **not** resolved:
 * two models mean two policies, and the honest answer is that this cannot tell.
 *
 * Returns `''` for a builtin — read successfully, and not a model.
 */
function declaredTypeName(?Node $type): ?string
{
    if ($type instanceof NullableType) {
        return declaredTypeName($type->type);
    }

    if ($type instanceof Identifier) {
        return in_array($type->toString(), ['static', 'self', 'parent'], true) ? null : '';
    }

    if ($type instanceof Name) {
        return $type->toString();
    }

    return null;
}

/**
 * Every variable in one method whose type this lint can read, closures included.
 *
 * Parameters first, then assignments in document order — an assignment is what a
 * controller actually does (`$row = InboundMedia::query()->findOrFail($media)`),
 * and it may legitimately overwrite a parameter's type. A variable assigned two
 * different classes resolves to neither.
 *
 * @param  array{methods: array<string, string>, properties: array<string, string>}  $members
 * @return array<string, string> Variable name => class name, `''` for a builtin,
 *                               or the unresolved marker.
 */
function methodVariableTypes(ClassMethod $method, NodeFinder $finder, ?string $self, array $members): array
{
    $types = [];

    foreach ($finder->find($method, fn (Node $n): bool => $n instanceof Param
        && $n->var instanceof Variable
        && is_string($n->var->name)) as $parameter) {
        $type = declaredTypeName($parameter->type);

        if ($type !== null && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
            $types[$parameter->var->name] = $type;
        }
    }

    foreach ($finder->find($method, fn (Node $n): bool => $n instanceof Assign
        && $n->var instanceof Variable
        && is_string($n->var->name)) as $assignment) {
        if (! $assignment instanceof Assign || ! $assignment->var instanceof Variable || ! is_string($assignment->var->name)) {
            continue;
        }

        $name = $assignment->var->name;
        $type = expressionClassName($assignment->expr, $self, $members, $types);

        // A null literal narrows nothing — `$settings = null;` before an `if`
        // is not a claim that `$settings` is not a model.
        if ($type === nullLiteralMarker()) {
            continue;
        }

        if ($type === null) {
            $types[$name] = unresolvedTypeMarker();

            continue;
        }

        if (array_key_exists($name, $types) && $types[$name] !== $type) {
            $types[$name] = unresolvedTypeMarker();

            continue;
        }

        $types[$name] = $type;
    }

    return $types;
}

/**
 * One authorization subject expression, resolved to a class name.
 *
 * `null` means unreadable and is an offence; `''` means read and found not to be
 * a class, which is a gate's own argument rather than a model.
 *
 * @param  array{methods: array<string, string>, properties: array<string, string>}  $members
 * @param  array<string, string>  $scope
 */
function authorizationSubjectClass(?Node $expression, ?string $self, array $members, array $scope): ?string
{
    $resolved = expressionClassName($expression, $self, $members, $scope);

    // A literal `null` handed to a gate is an argument, not a model.
    return $resolved === nullLiteralMarker() ? '' : $resolved;
}

/**
 * The class an expression evaluates to.
 *
 * ⚠️ **THE CHAIN IS WALKED TO ITS ROOT** because that is what an Eloquent lookup
 * looks like: `Voicemail::query()->findOrFail($id)` is a `MethodCall` on a
 * `MethodCall` on a `StaticCall`, and the only class name in it is at the
 * bottom. Where the receiver's class is known and the method is really declared
 * on it, **reflection answers instead** — which is how
 * `$plugins->setAllowedDomains($plugin, …)` is read as a `Plugin`.
 *
 * @param  array{methods: array<string, string>, properties: array<string, string>}  $members
 * @param  array<string, string>  $scope
 */
function expressionClassName(?Node $expression, ?string $self, array $members, array $scope): ?string
{
    if ($expression === null) {
        return null;
    }

    if ($expression instanceof ConstFetch && strtolower($expression->name->toString()) === 'null') {
        return nullLiteralMarker();
    }

    // ⚠️ **BEFORE THE OPERATOR ARM, BECAUSE `Coalesce` EXTENDS `BinaryOp`.** A
    // `$a ?? $b` reaching the literal group below would be read as arithmetic
    // and answered `''` — a model silently reported as not-a-model, which is
    // the one direction this lint may not fail in.
    if ($expression instanceof CoalesceNode) {
        return mergedExpressionClassNames([$expression->left, $expression->right], $self, $members, $scope);
    }

    if ($expression instanceof TernaryNode) {
        return mergedExpressionClassNames(
            [$expression->if ?? $expression->cond, $expression->else],
            $self,
            $members,
            $scope,
        );
    }

    // A literal, a cast, an operator, an array: read, and not a model.
    if ($expression instanceof String_
        || $expression instanceof IntNode
        || $expression instanceof FloatNode
        || $expression instanceof ConstFetch
        || $expression instanceof CastNode
        || $expression instanceof BinaryOpNode
        || $expression instanceof ArrayNode) {
        return '';
    }

    if ($expression instanceof Variable) {
        if (! is_string($expression->name)) {
            return null;
        }

        $type = $scope[$expression->name] ?? null;

        return $type === unresolvedTypeMarker() ? null : $type;
    }

    if ($expression instanceof ClassConstFetch && $expression->class instanceof Name) {
        $class = $expression->class->toString();

        if (($class === 'self' || $class === 'static') && $self !== null) {
            $class = $self;
        }

        // `Foo::class` names the model; any other constant is a gate's name.
        return $expression->name instanceof Identifier && $expression->name->toString() === 'class'
            ? $class
            : '';
    }

    if ($expression instanceof NewNode && $expression->class instanceof Name) {
        return $expression->class->toString();
    }

    if ($expression instanceof PropertyFetch || $expression instanceof NullsafePropertyFetch) {
        return $expression->var instanceof Variable
            && $expression->var->name === 'this'
            && $expression->name instanceof Identifier
                ? ($members['properties'][$expression->name->toString()] ?? null)
                : null;
    }

    if ($expression instanceof MethodCall || $expression instanceof NullsafeMethodCall) {
        if (! $expression->name instanceof Identifier) {
            return null;
        }

        $called = $expression->name->toString();

        // `$this->helper(…)` — the declared return type in this file is the
        // answer, and it is read from the AST because the class under lint may
        // not be loadable in the shape reflection wants.
        if ($expression->var instanceof Variable && $expression->var->name === 'this') {
            return $members['methods'][$called] ?? null;
        }

        $receiver = expressionClassName($expression->var, $self, $members, $scope);

        if ($receiver === null || $receiver === '' || $receiver === nullLiteralMarker()) {
            return $receiver === '' ? '' : null;
        }

        return receiverMethodReturnClass($receiver, $called);
    }

    if ($expression instanceof StaticCall && $expression->class instanceof Name) {
        $class = $expression->class->toString();

        return ($class === 'self' || $class === 'static') && $self !== null ? $self : $class;
    }

    return null;
}

/**
 * The one class a set of alternative expressions agree on, ignoring `null`.
 *
 * `$location === null ? null : $this->settingsFor($location)` is a `?Settings`
 * and the call site has already refused the null half — the same reading
 * `declaredTypeName()` gives `?Plugin`. Two arms naming two different models are
 * two policies and resolve to neither.
 *
 * @param  list<Node>  $expressions
 * @param  array{methods: array<string, string>, properties: array<string, string>}  $members
 * @param  array<string, string>  $scope
 */
function mergedExpressionClassNames(array $expressions, ?string $self, array $members, array $scope): ?string
{
    $answer = null;

    foreach ($expressions as $expression) {
        $resolved = expressionClassName($expression, $self, $members, $scope);

        if ($resolved === nullLiteralMarker()) {
            continue;
        }

        if ($resolved === null) {
            return null;
        }

        if ($answer !== null && $answer !== $resolved) {
            return null;
        }

        $answer = $resolved;
    }

    return $answer ?? '';
}

/**
 * What a method call on a known class returns, or the receiver when the method
 * is Eloquent's plumbing.
 *
 * ⛔ **THE FALLBACK IS BOUNDED TO MODELS ON PURPOSE.** `findOrFail()` is not
 * declared on a model at all — `__callStatic` forwards it to a builder — so an
 * unreflectable method has to mean *"still the receiver"* or every controller in
 * this application becomes unreadable. Letting that hold for a non-model would
 * credit a policy for any chain that happened to start at one, which is the
 * false negative this whole lint exists to avoid.
 */
function receiverMethodReturnClass(string $receiver, string $method): ?string
{
    $isModel = class_exists($receiver) && is_subclass_of($receiver, Model::class);

    if (! method_exists($receiver, $method)) {
        return $isModel ? $receiver : null;
    }

    try {
        $type = (new ReflectionMethod($receiver, $method))->getReturnType();
    } catch (ReflectionException) {
        return $isModel ? $receiver : null;
    }

    if (! $type instanceof ReflectionNamedType) {
        // A union, an intersection, or nothing declared at all.
        return $isModel ? $receiver : null;
    }

    if ($type->isBuiltin()) {
        return in_array($type->getName(), ['mixed', 'void', 'never'], true) && $isModel
            ? $receiver
            : '';
    }

    $name = $type->getName();

    if (in_array($name, ['static', 'self', 'parent'], true)) {
        return $receiver;
    }

    foreach (eloquentPlumbingClasses() as $plumbing) {
        if ($name === $plumbing || is_subclass_of($name, $plumbing)) {
            return $isModel ? $receiver : null;
        }
    }

    return $name;
}

/**
 * Every `catch (ConnectionException …) { … }` body in one file's source, as text.
 *
 * ⚠️ **A `ConnectionException` IS THE ONE FAILURE WHERE THE VENDOR SAID NOTHING**
 * — the request never became a response, so no fact about the far end can be
 * derived from it. That makes the *body* of this particular catch worth linting
 * on its own: it is the one place where a statement about somebody else can only
 * ever be an invention. 7015(e)'s defect lived in exactly such a body.
 *
 * ⚠️ **Brace-matched rather than regexed to the next `}`.** These bodies contain
 * closures, arrays and match arms, and a lint that stopped at the first closing
 * brace would read a two-line body out of a twenty-line one and pass on the
 * offence it was written to find — 398's shape, where something upstream refuses
 * first and the real check is never reached.
 *
 * ⚠️ **Pass source that has already been through {@see codeWithoutComments()}.**
 * `ZernioGbpClient` and `InfobipClient` both *quote* `catch (ConnectionException)`
 * inside docblocks that explain why they bind the variable, so this reader will
 * happily extract a body out of prose.
 *
 * ⚠️ **THAT GUARD HAS NO INSTANCE ON TODAY'S TREE AND IS STILL WORTH HAVING —
 * ESTABLISHED BY PLANTING ONE RATHER THAN ASSUMED** (7266). Removing the strip
 * leaves every current caller green, because no comment in `app/` contains both
 * a `catch (ConnectionException…) {` and the thing its lint is looking for. It
 * goes red the moment somebody writes the ordinary thing — a docblock recording
 * *"it used to read `} catch (ConnectionException $e) {` … `recordHealth(…)`"*,
 * which is exactly the correction 7261 wanted to leave behind. **A lint that
 * fails on the note explaining the fix is a lint that gets deleted.**
 *
 * Matches the bare class name and any leading-backslash or namespaced spelling,
 * with or without a bound variable, and with the type listed anywhere in a
 * multi-type catch.
 *
 * @return list<string> one entry per catch, the body between its braces
 */
function connectionFailureCatchBodies(string $code): array
{
    $bodies = [];
    $offset = 0;

    // ⚠️ CASE-INSENSITIVE, DELIBERATELY (10250): a caught class name is
    // PHP-identifier dispatch, so `catch (connectionexception $e)` reaches
    // the same handler.
    while (preg_match(
        '/catch\s*\(([^)]*\bConnectionException\b[^)]*)\)\s*\{/i',
        $code,
        $matches,
        PREG_OFFSET_CAPTURE,
        $offset,
    ) === 1) {
        $openingBrace = (int) $matches[0][1] + strlen((string) $matches[0][0]) - 1;

        $depth = 0;
        $length = strlen($code);
        $closingBrace = null;

        for ($index = $openingBrace; $index < $length; $index++) {
            if ($code[$index] === '{') {
                $depth++;

                continue;
            }

            if ($code[$index] !== '}') {
                continue;
            }

            $depth--;

            if ($depth === 0) {
                $closingBrace = $index;

                break;
            }
        }

        // An unbalanced tail means the file did not parse the way this reader
        // assumed. Taking the remainder is the direction that keeps the lint
        // able to accuse rather than quietly skipping the file.
        $closingBrace ??= $length;

        $bodies[] = substr($code, $openingBrace + 1, $closingBrace - $openingBrace - 1);

        $offset = $closingBrace;
    }

    return $bodies;
}

/**
 * The token before `$index` that is not whitespace, a comment or an attribute —
 * as a plain string, so `'::'` and `'->'` compare directly.
 *
 * ⚠️ **WRITTEN FOR THE EPOCH TRANSACTION LINT** (8200), which has to tell
 * `DB::transaction(` from any other `transaction(` and `->observe(` from
 * `Model::observe(`. A regex over the source cannot: a brace inside a string or
 * a comment moves the closure span and the lint is then confidently wrong about
 * where the transaction ends.
 *
 * @param  array<int, array{int, string, int}|string>  $tokens
 */
function previousMeaningfulToken(array $tokens, int $index): string
{
    for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
        $token = $tokens[$cursor];

        if (is_string($token)) {
            return $token;
        }

        if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_ATTRIBUTE], true)) {
            continue;
        }

        return $token[1];
    }

    return '';
}

/**
 * Every architecture-suite file whose exemption lists are read back by
 * {@see exemptedPathLists()}.
 *
 * @return list<string>
 */
function exemptionListSourceFiles(): array
{
    $files = [];

    foreach (File::files(base_path('tests/Feature/Architecture')) as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    // This file too. Six of the suite's exemption lists live here rather than in
    // the test that consumes them, and those are the ones with two consumers —
    // exactly the ones a rename is most likely to leave behind.
    $files[] = base_path('tests/Support/architecture_helpers.php');

    sort($files);

    return $files;
}

/**
 * Every list in the architecture suite that subtracts source-file paths from a
 * lint's own subject, keyed by `file:line label`.
 *
 * ## ⛔ WHY THIS EXISTS
 *
 * `TenancyTest`'s *every table without row-level security is a named exception*
 * has had a names-nothing arm since 3146, and it has been the exception rather
 * than the rule ever since: a census on 2026-08-23 found **120 path-shaped
 * exemption lists in this directory and 31 tests asking anything at all about
 * staleness** (8253). The rest are read in one direction only, so a permitted
 * file that is renamed, moved or deleted keeps its permission for ever — and the
 * next file to take that path inherits it without an argument. **This is the
 * second arm, written once for all of them**, rather than the seventh hand-copied
 * `foreach ($permitted as $file) { file_exists(...) }`.
 *
 * ## ⚠️ THE SUBJECT IS DERIVED TWICE, AND NEITHER DERIVATION IS ENOUGH ALONE
 *
 * A list is taken as an exemption if **something subtracts with it** — it is the
 * haystack of an `in_array()`, the array of an `array_key_exists()`, or any
 * argument after the first of `array_diff()`/`array_diff_key()`/
 * `array_intersect_key()` — **or** if its name is one of the words this codebase
 * uses for the idea. Both were measured against the suite on 2026-08-23: each
 * derivation alone found 117 lists, and each found **three the other missed**.
 * Usage misses `outboundHttpPermittedFiles()`, `supportStoreAllowlist()` and
 * `registryWriteDoorExemptions()`, whose consumers live in other files; the name
 * vocabulary misses `$notReaders`, `$readers` and `$drivers`, which are
 * subtractive under names nobody would have guessed. **A vocabulary is itself a
 * list that goes stale**, which is the failure this whole helper is about, so it
 * is not the only derivation.
 *
 * ## ⚠️ WHAT COUNTS AS A PATH LIST, AND WHY IT IS ALL-OR-NOTHING
 *
 * Every element must be a string literal ending in `.php` and containing a `/`,
 * on whichever side of the arrow carries the path. One non-path element
 * disqualifies the whole array: a mixed list is a list this cannot read
 * confidently, and guessing which half is a path is how a lint starts crying
 * wolf (511).
 *
 * ⛔ **AN ARRAY PASSED TO `toBe()` IS DELIBERATELY NOT A SUBJECT**, and that is
 * the line between the lists that need this arm and the ~70 that do not. An
 * expected-value list is already self-correcting: rename the file and the
 * harvested set stops matching the literal, and the lint that owns it goes red
 * on its own. **Only a subtractive list goes stale in silence.**
 *
 * @return array<string, list<string>>
 */
function exemptedPathLists(): array
{
    $vocabulary = '/(?:xempt|llowlist|ermitted|xclud|gnore|hitelist|aive|xception|eadOnly|nly)/';

    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $finder = new NodeFinder;

    $sources = [];
    $trees = [];

    foreach (exemptionListSourceFiles() as $file) {
        $sources[$file] = (string) file_get_contents($file);
        $trees[$file] = $parser->parse($sources[$file]) ?? [];
    }

    // Names used subtractively. Variables are lexical, so they are collected per
    // file; a helper function is global, so its callers may live anywhere in the
    // suite and those are collected across all of it.
    $subtractiveVariables = [];
    $subtractiveFunctions = [];
    $inlineLists = [];

    foreach ($trees as $file => $ast) {
        $subtractiveVariables[$file] = [];
        $inlineLists[$file] = [];

        foreach ($finder->findInstanceOf($ast, FuncCall::class) as $call) {
            if (! $call->name instanceof Name) {
                continue;
            }

            $positions = match ($call->name->toString()) {
                'in_array', 'array_key_exists' => [1],
                'array_diff', 'array_diff_key', 'array_intersect_key' => range(1, max(1, count($call->args) - 1)),
                default => null,
            };

            if ($positions === null) {
                continue;
            }

            foreach ($positions as $position) {
                $argument = $call->args[$position] ?? null;

                if (! $argument instanceof Arg) {
                    continue;
                }

                if ($argument->value instanceof Variable && is_string($argument->value->name)) {
                    $subtractiveVariables[$file][$argument->value->name] = true;
                }

                if ($argument->value instanceof FuncCall && $argument->value->name instanceof Name) {
                    $subtractiveFunctions[$argument->value->name->toString()] = true;
                }

                // ⛔ **AND THE HAYSTACK WRITTEN INLINE** (8463). The derivation
                // is *something subtracts with this array*, and where it is
                // spelled `in_array($relative, ['a.php', 'b.php'], true)` there
                // is no variable and no function for either arm above to find.
                // Five sites and twenty paths were invisible for that reason
                // alone — an accident of spelling, not a different idea.
                if ($argument->value instanceof ArrayNode) {
                    $inlineLists[$file][] = $argument->value;
                }
            }
        }
    }

    $lists = [];

    foreach ($trees as $file => $ast) {
        $label = basename((string) $file);

        foreach ($finder->findInstanceOf($ast, Assign::class) as $assignment) {
            if (! $assignment->var instanceof Variable
                || ! is_string($assignment->var->name)
                || ! $assignment->expr instanceof ArrayNode) {
                continue;
            }

            $name = $assignment->var->name;

            if (! isset($subtractiveVariables[$file][$name]) && preg_match($vocabulary, $name) !== 1) {
                continue;
            }

            $paths = pathListOfArrayNode($assignment->expr);

            if ($paths !== null) {
                $lists[$label.':'.$assignment->getStartLine().' $'.$name] = $paths;
            }
        }

        foreach ($inlineLists[$file] as $inline) {
            $paths = pathListOfArrayNode($inline);

            if ($paths !== null) {
                $lists[$label.':'.$inline->getStartLine().' (inline)'] = $paths;
            }
        }

        foreach ($finder->findInstanceOf($ast, Function_::class) as $function) {
            $name = $function->name->toString();

            if (! isset($subtractiveFunctions[$name]) && preg_match($vocabulary, $name) !== 1) {
                continue;
            }

            foreach ($finder->find($function->stmts, fn (Node $node): bool => $node instanceof Return_
                && $node->expr instanceof ArrayNode) as $return) {
                $paths = pathListOfArrayNode($return->expr);

                if ($paths !== null) {
                    $lists[$label.':'.$return->getStartLine().' '.$name.'()'] = $paths;
                }
            }
        }
    }

    ksort($lists);

    return $lists;
}

/**
 * The paths in an array node, or null if it is not entirely made of them.
 *
 * @return list<string>|null
 */
function pathListOfArrayNode(ArrayNode $array): ?array
{
    if ($array->items === []) {
        return null;
    }

    $paths = [];

    foreach ($array->items as $item) {
        if (! $item instanceof ArrayItem) {
            return null;
        }

        $candidate = match (true) {
            $item->key instanceof String_ => $item->key->value,
            $item->key === null && $item->value instanceof String_ => $item->value->value,
            default => null,
        };

        if ($candidate === null || ! exemptedPathLiteralLooksLikeAPath($candidate)) {
            return null;
        }

        $paths[] = $candidate;
    }

    return $paths;
}

/**
 * Every path this suite exempts by COMPARING against it rather than by listing it.
 *
 * ⛔ **THE OTHER HALF OF THE DIRECTORY, AND THE SWEEP'S TITLE WAS UNTRUE WITHOUT
 * IT** (8463). {@see exemptedPathLists()} reaches arrays. The same permission is
 * spelled `if ($relative === 'Models/User.php') { continue; }` at forty-nine
 * sites naming thirty-nine distinct paths, and every one of them was invisible —
 * so *"every path a lint exempts itself from names a file that still exists"*
 * was a claim about the 368 entries in array form and about nothing else. That
 * is 314-316's shape in a test title: the reader who checks that the sweep exists
 * stops looking for the paths it cannot see.
 *
 * ⚠️ **IT IS SOUND WHETHER THE COMPARISON EXEMPTS OR SELECTS**, which is why no
 * attempt is made to tell those apart. `=== 'x.php'` guarding a `continue` is an
 * exemption; `=== 'x.php'` guarding an assertion is a subject. **A path that
 * names no file makes the branch dead either way** — an exemption that subtracts
 * nothing, or a special case that never fires. Both are the failure this is
 * about, and a predicate that tried to distinguish them would be 511's lint
 * tuned until it caught neither.
 *
 * ⚠️ **WHAT IS STILL NOT REACHED, STATED RATHER THAN GLOSSED.** A path assembled
 * by concatenation, matched with `str_ends_with()`/`str_contains()`, or held in
 * a constant is invisible to both harvests. So the honest claim is *every path
 * this suite writes as a whole literal, in an array or in an identity
 * comparison*, and that is what the sweep's title now says.
 *
 * @return array<string, string>
 */
function exemptedPathComparisons(): array
{
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $finder = new NodeFinder;

    $found = [];

    foreach (exemptionListSourceFiles() as $file) {
        $ast = $parser->parse((string) file_get_contents($file)) ?? [];

        foreach ($finder->find($ast, fn (Node $node): bool => $node instanceof IdenticalNode
            || $node instanceof NotIdenticalNode) as $comparison) {
            /** @var IdenticalNode|NotIdenticalNode $comparison */
            $literal = match (true) {
                $comparison->left instanceof String_ && ! $comparison->right instanceof String_ => $comparison->left->value,
                $comparison->right instanceof String_ && ! $comparison->left instanceof String_ => $comparison->right->value,
                default => null,
            };

            if ($literal === null || ! exemptedPathLiteralLooksLikeAPath($literal)) {
                continue;
            }

            $found[basename((string) $file).':'.$comparison->getStartLine()] = $literal;
        }
    }

    ksort($found);

    return $found;
}

/**
 * Whether a string literal is the kind of source path these harvests read.
 *
 * Shared by {@see pathListOfArrayNode()} and {@see exemptedPathComparisons()} so
 * the two cannot drift into disagreeing about what a path is.
 */
function exemptedPathLiteralLooksLikeAPath(string $candidate): bool
{
    return str_contains($candidate, '/')
        && preg_match('#^[A-Za-z0-9_./-]+\.php$#', $candidate) === 1;
}

/**
 * Every exempted path that names no file.
 *
 * ⚠️ **FOUR ROOTS, BECAUSE THE SUITE SPELLS THESE FOUR WAYS** and no convention
 * makes them agree: `Services/Voice/VoiceSpend.php` is relative to `app/`,
 * `app/Services/Links/TenantLinks.php` to the repository, and
 * `views/feedback/thanks.blade.php` and `components/account/layout.blade.php`
 * to `resources/` and `resources/views/` respectively — each because the lint
 * that owns it keys its scan that way. A path is resolvable if it names a file
 * under any of the four.
 *
 * ⛔ **SO THIS PROVES "NAMES A FILE", NOT "NAMES THE RIGHT FILE"**, and that is
 * stated rather than glossed (314-316). Two lists could spell the same
 * permission against different roots and both resolve. What it does catch is the
 * one that actually happens: a permitted file renamed, moved or deleted, leaving
 * an exemption that reads as an argument and subtracts nothing.
 *
 * @return list<string>
 */
function exemptedPathOffences(): array
{
    $offences = [];

    foreach (exemptedPathLists() as $label => $paths) {
        foreach ($paths as $path) {
            if (! exemptedPathIsResolvable($path)) {
                $offences[] = $label.' -> '.$path;
            }
        }
    }

    foreach (exemptedPathComparisons() as $label => $path) {
        if (! exemptedPathIsResolvable($path)) {
            $offences[] = $label.' -> '.$path;
        }
    }

    sort($offences);

    return $offences;
}

/**
 * Whether an exempted path names a file under one of the four roots.
 *
 * Split out so the guard on the guard can drive it directly rather than through
 * a walk of the whole suite, which finds nothing and would prove nothing (256).
 */
function exemptedPathIsResolvable(string $path): bool
{
    foreach ([app_path(), base_path(), resource_path(), resource_path('views')] as $root) {
        if (is_file($root.'/'.$path)) {
            return true;
        }
    }

    return false;
}

/**
 * Every {@see registryWriteDoorExemptions()} entry that exempts nothing.
 *
 * ⛔ **THE ARM THE LIST'S OWN COMMENT ALREADY CLAIMED** (8257). Its docblock
 * says the derivation is *"the shape of every table without row-level security is
 * a named exception (3146-3152)"* — and that lint has three arms while this one
 * had a forward pass and no way to notice an exemption going dead. **A citation
 * is not a mechanism** (314-316), and a lint is the worst place to leave one,
 * because the reader who checks the reference stops looking at the code.
 *
 * ⚠️ **THE SUBJECT IS "WOULD THIS FILE BE LOOKED AT AT ALL", NOT "WOULD IT
 * OFFEND".** {@see registryWriteDoorOffences()} skips an exempted file before it
 * reads a line of it, so the honest question is whether the file still reaches
 * the registry the way the lint recognises: it exists, it names
 * `DefaultsRegistry`, and it holds a three-argument `->set()`. **Asking instead
 * whether it would be *reported* would be wrong**, because a file whose keys all
 * gained a door would then look stale while its exemption is still the reason
 * the door is not demanded of it — and deleting it on that reading re-opens the
 * hole 5904 closed.
 *
 * @return list<string>
 */
function registryWriteDoorExemptionOffences(): array
{
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $finder = new NodeFinder;

    $offences = [];

    foreach (array_keys(registryWriteDoorExemptions()) as $relative) {
        $path = base_path($relative);

        if (! is_file($path)) {
            $offences[] = $relative.' is exempt from the write-door lint and is not a file.';

            continue;
        }

        $source = (string) file_get_contents($path);

        if (! str_contains($source, 'DefaultsRegistry')) {
            $offences[] = $relative.' is exempt from the write-door lint and no longer names '
                .'DefaultsRegistry, so the lint would never have looked at it.';

            continue;
        }

        $ast = $parser->parse($source) ?? [];

        $writes = $finder->find($ast, fn (Node $n): bool => $n instanceof MethodCall
            && $n->name instanceof Identifier
            && $n->name->toString() === 'set'
            && count($n->args) === 3);

        if ($writes === []) {
            $offences[] = $relative.' is exempt from the write-door lint and holds no '
                .'three-argument ->set(), so it writes no registry key the lint could report.';
        }
    }

    sort($offences);

    return $offences;
}

/**
 * Every entry on a chokepoint allowlist that no longer covers anything.
 *
 * ⛔ **THE SECOND HALF OF "256's GUARD ON THE GUARD"** (8259). Six lints in this
 * suite close with `foreach ($permitted as $file) { file_exists(...) }` under
 * that comment, and two of them say *"an allowlist naming a file that does not
 * exist is a lint that has quietly stopped covering anything."* **That catches a
 * rename and nothing else.** A permitted file that stops making the call it was
 * permitted to make keeps its permission for ever, and the permission is a
 * standing statement that this one file may reach a chokepointed table, method
 * or key — so the entry outlives its argument in exactly the direction nobody
 * looks.
 *
 * ⚠️ **THE PATTERN IS THE CALLER'S AND IS DELIBERATELY LOOSER THAN THE LINT'S
 * OWN.** Most of these lists hold **the declaring class as well as its callers**
 * — `PlatformTexter.php` declares `replyToInbound()` and never writes
 * `->replyToInbound(` — so re-using the offence regex would report the one file
 * whose permission is least in doubt, which is 511's failure arriving inside the
 * fix for 256's. What is asked instead is that the file still **names the
 * subject at all**, which is the strongest thing true of both a declaration and
 * a call.
 *
 * ⚠️ **COMMENTS ARE STRIPPED**, for 3288's reason: this codebase explains its
 * load-bearing lines at length, so raw source lets a deleted call go on being
 * satisfied by the paragraph describing it — inside the guard whose whole job is
 * to notice that.
 *
 * ⚠️ **`$root` EXISTS BECAUSE THIS SUITE SPELLS A PATH FOUR WAYS** and no
 * convention makes them agree — {@see exemptedPathOffences()} carries the same
 * concession for the same reason. Most of these lists are relative to `app/`,
 * which is the default; `RetentionTest`'s compliance-log allowlist walks
 * `app/`, `database/` and `routes/` together and is therefore relative to the
 * repository. **Passing the wrong root makes every entry look deleted**, which
 * fails loudly rather than quietly and is the direction to be wrong in.
 *
 * @param  list<string>  $permitted  paths relative to `$root`, or to `app/`
 * @return list<string>
 */
function chokepointAllowlistOffences(array $permitted, string $pattern, string $subject, ?string $root = null): array
{
    $root ??= app_path();
    $offences = [];

    foreach ($permitted as $relative) {
        $path = rtrim($root, '/').'/'.$relative;

        if (! is_file($path)) {
            $offences[] = $relative.' is on the allowlist and does not exist. A stale '
                .'exemption is indistinguishable from a live one, and the next file created '
                .'at that path inherits the permission.';

            continue;
        }

        if (preg_match($pattern, phpWithoutComments((string) file_get_contents($path))) !== 1) {
            $offences[] = $relative.' is on the allowlist and no longer names '.$subject
                .', so the permission covers nothing. Remove it — a permission is a written '
                .'argument that this file may do the thing, and it should not outlive the doing.';
        }
    }

    sort($offences);

    return $offences;
}

/**
 * Every table in the live schema carrying a column of this name.
 *
 * ⛔ **THE PREMISE OF EVERY BARE-COLUMN-NAME LINT, MADE FALSIFIABLE** (8900).
 * A chokepoint lint that greps `app/`, `database/` and `routes/` for one column
 * name is only sound while the argument that named it holds — and that argument
 * is always *"these are the tables carrying this name, and here is why the other
 * ones cannot be what a hit means"*. That argument lives in a comment today, and
 * a comment cannot notice a migration adding the name to a fourth table. This
 * turns it into an assertion: pin the set, and the day it changes the build
 * hands the next person the reasoning instead of a mystery.
 *
 * ⛔ **`pg_attribute`, NOT `information_schema` (8421).** `information_schema`
 * filters by the READING role's privileges and the app connects as a non-owner,
 * so a privilege change would quietly shrink the answer. Callers assert with
 * `toBe([…])` against a non-empty list, which is fail-CLOSED either way — an
 * empty result is a failure rather than a pass — and that ordering is
 * deliberate: the sibling arm at `PlatformSettingTest` had to add a floor
 * because *its* shape was fail-open.
 *
 * ⚠️ **WHAT IT MISSES.** It reads the schema this suite migrated, so it is exact
 * about columns and says nothing about *views*, and nothing about a column that
 * exists only on production. `relkind` accepts ordinary and partitioned tables;
 * there are no partitioned tables in this schema today and the second letter is
 * there so a future one does not silently drop out of the census.
 *
 * @return list<string>
 */
function tablesCarryingColumn(string $column): array
{
    /** @var list<object{table_name: string}> $rows */
    $rows = DB::select(
        "SELECT c.relname AS table_name
           FROM pg_class c
           JOIN pg_namespace n ON n.oid = c.relnamespace
           JOIN pg_attribute a ON a.attrelid = c.oid
          WHERE n.nspname = current_schema()
            AND c.relkind IN ('r', 'p')
            AND a.attnum > 0
            AND NOT a.attisdropped
            AND a.attname = ?
          ORDER BY c.relname",
        [$column],
    );

    return array_values(array_map(
        static fn (object $row): string => (string) $row->table_name,
        $rows,
    ));
}

/**
 * Which files under `app/` write each {@see PlatformHealthSignal} case.
 *
 * ⛔ **EXTRACTED RATHER THAN COPIED (8460, and the wave-25 common rules'
 * restatement of it).** Two lints in `ObservabilityTest` need this derivation —
 * *"every health signal is recorded somewhere"* (7080–7099) and *"the vendor
 * error rate watches the AI router and nothing else"* (9280) — and the second
 * one is a guard over a **sentence on the Ops board**. A lint holding its own
 * re-typed twin of the pattern its sibling reads is 8460's shape even when both
 * copies are correct on the day they are written, and here the drift would be
 * silent in the worst direction: the census arm would go on passing while the
 * coverage arm measured a different set of recorders.
 *
 * ⚠️ **`$recorders` INCLUDES THE PRIVATE FUNNEL ON PURPOSE.**
 * `PlatformHealthSignal::Heartbeat`'s only writer is `PlatformHealth::beat()`,
 * which calls `$this->increment()` directly rather than one of the three public
 * recorders, so a list of the public verbs alone would report a signal written
 * on every scheduler tick as writerless.
 *
 * ⚠️ **COMMENTS ARE STRIPPED, AND THAT IS LOAD-BEARING RATHER THAN TIDY**
 * (2015, 8690). `WatchPlatformHealth`'s docblock argues at length about
 * `VendorCall` and writes nothing; `ReconcileZernioAccounts`' docblock names it
 * to say the opposite. A raw grep would read both arguments as the act.
 *
 * ⛔ **IT IS A FILE CENSUS AND NOT A CALL-GRAPH, AND THE ERROR RUNS LOUD.** A
 * file that names a case in a `match` arm beside an unrelated recording call is
 * counted as a writer, and a recorder handed its case as a *parameter* puts the
 * caller's file in the list instead of its own. Both directions change the set,
 * so a caller asserting set equality is told to come and look — which is the
 * opposite error direction from a column census, whose blind spots score a dead
 * column alive and are therefore silent.
 *
 * @return array<string, list<string>> case name => relative paths, every case
 *                                     present, sorted, `[]` where nothing writes
 */
function platformHealthSignalWriters(): array
{
    $enum = 'Enums/PlatformHealthSignal.php';
    $recorders = ['->recordFailure(', '->recordSuccess(', '->recordRun(', '$this->increment('];

    /** @var array<string, list<string>> $writers */
    $writers = [];

    foreach (PlatformHealthSignal::cases() as $case) {
        $writers[$case->name] = [];
    }

    foreach (File::allFiles(app_path()) as $file) {
        $relative = str_replace('\\', '/', $file->getRelativePathname());

        if ($relative === $enum) {
            continue;
        }

        $contents = codeWithoutComments($file);

        $records = false;

        foreach ($recorders as $recorder) {
            $records = $records || str_contains($contents, $recorder);
        }

        if (! $records) {
            continue;
        }

        foreach (PlatformHealthSignal::cases() as $case) {
            if (str_contains($contents, 'PlatformHealthSignal::'.$case->name)) {
                $writers[$case->name][] = $relative;
            }
        }
    }

    foreach ($writers as $case => $files) {
        sort($files);
        $writers[$case] = $files;
    }

    return $writers;
}

/*
|--------------------------------------------------------------------------
| Blade — the one stripper, and why `phpWithoutComments()` is not it
|--------------------------------------------------------------------------
|
| ⛔ **`token_get_all()` STRIPS NOTHING FROM A BLADE TEMPLATE, AND MEASURING
| THAT IS WHAT PRODUCED THIS BLOCK** (9338). A `.blade.php` file with no
| literal `<?php` block is **one** `T_INLINE_HTML` token: `{{-- … --}}`,
| `@if`, `{{ }}` and `<!-- -->` all arrive inside it and all come back
| verbatim, so `phpWithoutComments()` returns the file byte-identical.
| `registryScannedFiles()` above branches on `resources/views` for exactly
| this reason. **So a lint arm asserting a subject is PRESENT in a template is
| satisfied by the paragraph above the line it guards** — 2015 and 8690, in
| the one direction where those bite.
|
| ⚠️ **A `.blade.php` FILE PASSES AN `$file->getExtension() !== 'php'` FILTER**,
| which is how several lints came to believe they were stripping when they were
| not. The extension is `php`; the content is not.
|
| ⛔ **THIS IS THE ONLY HOME FOR THE PATTERN, AND IT WAS THE NINTH COPY WHEN IT
| WAS WRITTEN.** Eight independent spellings of `/\{\{--.*?--\}\}/s` existed
| across the suite, and `AdminNavTest`'s docblock asserted *"there is no Blade
| stripper there and this is its only reader"* — false on the day it was
| written, with six copies already in the tree and a seventh landing in the
| same wave. `tests/Feature/Architecture/BladeScanningTest.php` now fails the
| build on a tenth.
|
| ⚠️ **STRIPPING IS NOT A UNIFORM IMPROVEMENT AND MUST NOT BE SWEPT ACROSS THE
| SUITE.** Roughly twenty NEGATIVE arms read templates raw — a forbidden `#hex`,
| a forbidden `dark:`, a forbidden price literal — and there raw is *stricter*:
| stripping loosens them, and the loud false red they give on a sentence
| explaining the prohibition is the trade this codebase has already chosen
| (8698). Two POSITIVE arms are raw on purpose because their subject **is** a
| comment — `AccountScreensTest`'s `location-picker: rendered by ` escape marker
| and `ScreenStates::ABSENCE_MARKER`, which spent eighteen hours matching
| nothing when it was read from blanked source (3008). **The measurement is per
| site.**
|
*/

/**
 * The one Blade-comment pattern in this suite.
 *
 * `{{-- --}}` does not nest in Blade, so the lazy `.*?` is correct; `/s` is
 * what lets a comment span lines, which almost all of them do here.
 *
 * ⚠️ **AN HTML COMMENT IS DELIBERATELY NOT MATCHED.** Blade renders `<!-- -->`
 * to the client, so a `route()` inside one is a real `href` and a class name
 * inside one is really in the response body. Removing those would loosen every
 * arm that reads a template for something the browser receives.
 *
 * ⚠️ **A `route()` INSIDE A `@php` BLOCK SURVIVES BOTH THIS AND
 * `phpWithoutComments()`** — the first because it is not a `{{-- --}}`
 * comment, the second because the whole template is inline HTML to the lexer.
 * A template that writes the guarded subject inside `@php` is being read with
 * the wrong instrument rather than read wrongly.
 *
 * ⛔ **WHERE IT DISAGREES WITH BLADE ITSELF — MEASURED AGAINST THE COMPILER,
 * NOT REASONED** (9344). `CompilesComments::compileComments()` builds
 * `/{{--(.*?)--}}/s` from `$contentTags`, which is this pattern character for
 * character. **But `BladeCompiler::compileString()` calls
 * `storeUncompiledBlocks()` FIRST**, so `@php` and `@verbatim` bodies are
 * swapped out for placeholders before Blade's own strip ever runs. Three
 * divergences follow, all confirmed by compiling the fixtures:
 *
 * - `@php $x = '{{-- hi --}}'; @endphp` — **this strips it; Blade keeps it as
 *   PHP source.**
 * - `@verbatim {{-- v --}} @endverbatim` — **this strips it; Blade RENDERS it
 *   to the browser verbatim.** That is the one that matters to a *negative*
 *   arm: a forbidden token inside `@verbatim` really does reach a customer,
 *   and a stripped read cannot see it. It is one more reason not to sweep the
 *   negative arms.
 * - a stray `--}}` inside a `@php` body while a comment is open earlier —
 *   **this closes at the stray one; Blade closes at the real one.**
 *
 * ⚠️ **NONE OF THE THREE EXISTS IN `resources/views` TODAY** — measured across
 * every template: no `@php` or `@verbatim` body contains either delimiter, and
 * `{{--` and `--}}` balance in every file. So this is a limit to know about
 * rather than a defect to fix, and the day one appears the right move is to
 * ask why a template is hiding markup in a `@php` block.
 */
const BLADE_COMMENT_PATTERN = '/\{\{--.*?--\}\}/s';

/**
 * A Blade template's source with its `{{-- … --}}` comments removed.
 *
 * The default. Reach for it in any arm that asserts a template **contains**
 * something — a component tag, a `route()` literal, a method call — because
 * the one thing this codebase reliably does with a load-bearing line is write
 * a paragraph above it, and a paragraph is exactly as greppable as an anchor.
 */
function bladeWithoutComments(string $blade): string
{
    return (string) preg_replace(BLADE_COMMENT_PATTERN, '', $blade);
}

/**
 * The same strip, offset-preserving: every comment becomes blank space of the
 * same length, so byte offsets and line numbers still point at the real file.
 *
 * ⚠️ **NOT INTERCHANGEABLE WITH THE ONE ABOVE, WHICH IS WHY BOTH ARE HERE.**
 * `ScreenStates` reports the line a loop is on and resolves a guard by walking
 * backwards from an offset; removing the comments outright would shift both.
 * It is the only reader that needs this, and it is the one copy of the eight
 * that could not have been replaced by any of the others.
 */
function bladeCommentsBlanked(string $blade): string
{
    return (string) preg_replace_callback(
        BLADE_COMMENT_PATTERN,
        fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]),
        $blade,
    );
}

/**
 * The third reader of the same pattern: the comments themselves, each with the
 * line it starts on.
 *
 * ⚠️ **THE INVERSE OF THE TWO ABOVE, AND IT IS A DIFFERENT JOB RATHER THAN A
 * NEGATION OF THEIRS.** Both of those exist so an arm asserting a template
 * *contains* something is not satisfied by the paragraph explaining it. This
 * one exists for the arm whose subject **is** the paragraph — `CitationTest`
 * reads every comment in the tree for the name of a test that does not exist,
 * and a citation is only ever written in prose.
 *
 * ⛔ **IT IS HERE RATHER THAN IN ITS CALLER BECAUSE `BladeScanningTest` SAID SO
 * ON THE RUN THAT ADDED IT** (9667). The caller shipped a private copy of the
 * pattern, the whole suite went red, and the failure message named the file and
 * the fix. That lint is 8460's shape enforced: a ninth copy is a ninth rule,
 * **even when the ninth copy is character-for-character correct**.
 *
 * The line number is `substr_count()` over the prefix rather than anything
 * cleverer, which is O(file) per comment and irrelevant at this size.
 *
 * @return list<array{0: int, 1: string}>
 */
function bladeComments(string $blade): array
{
    if (preg_match_all(BLADE_COMMENT_PATTERN, $blade, $matches, PREG_OFFSET_CAPTURE) === 0) {
        return [];
    }

    $comments = [];

    foreach ($matches[0] as [$text, $offset]) {
        $comments[] = [substr_count(substr($blade, 0, $offset), "\n") + 1, $text];
    }

    return $comments;
}

/**
 * Every `preg_match`/`preg_match_all` call in `$code`, with its pattern
 * argument reassembled when it is a plain string literal (optionally
 * concatenated with a variable, which is walked past rather than resolved —
 * this cannot see what a variable holds).
 *
 * ⛔ **THE FIRST DRAFT STOPPED AT THE FIRST INTERPOLATED VARIABLE, WHICH IS
 * EXACTLY `modelChokepointOffences()`'s OWN SHAPE — `'/\b'.$model.'::(?!class
 * \b)/'`** (10250–10259). A pattern built as `string . $var . string` has its
 * *closing* delimiter and its flags in the THIRD segment, invisible to a
 * reader that gives up at the second. Walking the whole argument expression —
 * joining every string-literal segment across every `.` — is what lets this
 * function see the flags on a pattern like that one at all.
 *
 * ⚠️ **WHAT IT STILL CANNOT SEE, STATED RATHER THAN DISCOVERED LATER** (352,
 * 397, 565's rule): a pattern assigned to a variable on one line and handed to
 * `preg_match` on a later one — found four real instances of exactly this
 * shape by a manual grep for `(?!class\b)` that this function's caller could
 * not have found on its own (`InboxTest.php`, `GbpTest.php` twice,
 * `supportStorePattern()` in this file). A census built only on this function
 * is a floor, not a ceiling, in the same sense `tablesCarryingColumn()`'s own
 * docblock states for its subject.
 *
 * @return list<array{line: int, pattern: string}>
 */
function pregMatchCallPatterns(string $code): array
{
    $tokens = token_get_all($code);
    $count = count($tokens);
    $calls = [];

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        // ⚠️ CASE-INSENSITIVE ON THE FUNCTION NAME, ON THIS OWN LINT'S OWN
        // RULE — CLOSED 2026-08-27 (10480-10499). PHP dispatches a function
        // call case-insensitively, so `PREG_MATCH(…)` reaches this same
        // built-in and was invisible to a case-sensitive `in_array()` — the
        // one shape a lint about case-insensitive dispatch cannot itself
        // afford to miss.
        if (! is_array($token) || $token[0] !== T_STRING
            || ! in_array(strtolower($token[1]), ['preg_match', 'preg_match_all'], true)) {
            continue;
        }

        $line = $token[2];
        $j = $i + 1;

        while ($j < $count && $tokens[$j] !== '(') {
            $j++;
        }

        if ($j >= $count) {
            continue;
        }

        $j++;

        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }

        $parts = [];
        $depth = 0;
        $k = $j;

        while (isset($tokens[$k])) {
            $t = $tokens[$k];

            if ($t === '(') {
                $depth++;
                $k++;

                continue;
            }

            if ($t === ')') {
                if ($depth === 0) {
                    break;
                }

                $depth--;
                $k++;

                continue;
            }

            if ($t === ',' && $depth === 0) {
                break;
            }

            if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) {
                $parts[] = $t[1];
            }

            $k++;
        }

        $calls[] = ['line' => $line, 'pattern' => implode('', $parts)];
    }

    return $calls;
}

/**
 * The label {@see classNameOnlyRegexShapes()} files the static-access shape
 * under, named once so that {@see regexPatternsMissingCaseInsensitivity()} can
 * recognise its own sixth entry without holding a second copy of the string.
 */
function staticAccessShapeLabel(): string
{
    return 'a static access on a named class (`Xxx::`)';
}

/**
 * The regex that finds a static-access class reference inside a pattern STRING
 * — the sixth shape, added 2026-08-28 (10910–10939).
 *
 * ⛔ **THE FIVE SHAPES BELOW DETECTED STATIC ACCESS ONLY BY THE LITERAL
 * `(?!class\b)` IDIOM, SO EVERY `Xxx::Case`, `Xxx::CONSTANT` AND `Xxx::method(`
 * PATTERN WRITTEN WITHOUT THAT LOOKAHEAD WAS INVISIBLE TO THIS LINT.** Measured
 * on the tree this was written against: 19 in-scope patterns carried a `::` and
 * matched none of the five, of which 15 named a literal class or the `self`
 * keyword and carried no case-insensitivity at all. Every one of them was a
 * *"does this forbidden construction appear outside its permitted home"* lint,
 * so a missed match is a **permitted violation** — the fail-open direction.
 *
 * ⚠️ **THE EXCLUSION WAS SILENT RATHER THAN ARGUED**, which is what makes this
 * a hole and not a scope. `classNameOnlyRegexShapes()`'s docblock puts exactly
 * two exclusions on the record — a bare `->word(`/`::word(` method call, and a
 * `use` import — and the method-call argument is about the token AFTER the
 * operator: it can be a property, a class constant or an enum case, all
 * case-SENSITIVE. ✅ **That argument is correct and does not reach the token
 * BEFORE `::`, which by PHP's own grammar can only ever be a class name, a
 * namespace-qualified class name, or one of `self`/`static`/`parent` — and all
 * four are dispatched case-insensitively.** So the class name is always in
 * scope even where the thing after `::` is not, which is why the remedy at
 * every site is the scoped `(?i:Xxx)::Case` form and ⛔ **never a blanket `/i`,
 * which would make a real enum case insensitive too.**
 *
 * ⚠️ **WRITTEN WITH `\x27`/`\x22` RATHER THAN QUOTE CHARACTERS**, so that the
 * regex needs no PHP string escaping at all: a pattern text is raw source,
 * quotes and backslashes included, and an escaped-quote character class in a
 * quoted string is the shape this whole lint exists because people get wrong.
 *
 * Two alternatives, and both are load-bearing:
 *
 * - `IDENT::` where the character before `IDENT` is not a quote and not an
 *   identifier character. ⛔ **The quote exclusion is what keeps a Postgres
 *   cast out** — `StaffTest`'s `"/^'(.*)'::/"` is `'value'::type` in SQL, where
 *   the left side is data and nothing about it is case-insensitive. A `*`, `]`,
 *   `|` or `)` before `::` means the class name is itself a character class or
 *   an alternation (`VisibilityTest:539`, `CredentialsTest:290`,
 *   `GbpTest:239`), which is already case-insensitive by construction.
 * - `''::` — the seam {@see pregMatchCallPatterns()} leaves where it walked
 *   past an interpolated variable, so `'\b'.$model.'::class'` is still seen as
 *   a class reference. `PixelTest:976` is the occupant: three arms of one `||`,
 *   two carrying `/i` and the third not.
 *
 * ⚠️ **THE CANONICAL REMEDY PUTS THE `::` INSIDE THE GROUP — `(?i:Xxx::)Case`,
 * NOT `(?i:Xxx)::Case` — AND THAT IS A COUNTING DECISION, NOT A TASTE ONE.**
 * Both are correct PCRE and {@see staticAccessesMissingCaseInsensitivity()}
 * accepts both. But `(?i:Xxx)::` leaves a `)` where the class name was, so the
 * pattern stops matching this shape at all: a tree fixed entirely that way
 * drives the per-shape anti-vacuity floor in `RegexCaseSensitivityTest` to
 * **zero**, and the floor cannot tell a fixed corpus from a matcher that has
 * stopped matching. ⛔ **Fixing every site correctly must not be able to blind
 * the guard**, so the spelling that stays countable is the one to write.
 * * ⚠️ **KNOWN NARROWNESS, STATED RATHER THAN DISCOVERED LATER** (565's rule): a
 * pattern whose preg delimiter is itself a quote character — `preg_match("'Foo
 * ::'", …)`, legal PCRE — would have its leading class name excluded by the
 * quote rule. There is no such pattern in this tree and the shape is not worth
 * a false positive on every SQL cast.
 */
function staticAccessShapeRegex(): string
{
    return '/(?<![\x27\x22A-Za-z0-9_])(?:\\\\+b)?([A-Za-z_][A-Za-z0-9_]*)::|([\x27\x22]{2})::/';
}

/**
 * Every static-access class reference in a pattern STRING, with the byte
 * offset it starts at — the per-occurrence half of the shape above.
 *
 * @return list<array{name: string, offset: int}>
 */
function staticAccessClassNames(string $pattern): array
{
    if (preg_match_all(staticAccessShapeRegex(), $pattern, $matches, PREG_OFFSET_CAPTURE) === 0) {
        return [];
    }

    $found = [];

    foreach (array_keys($matches[0]) as $i) {
        // The seam alternative captures the two delimiter quotes an
        // interpolated variable was walked past between, which names nothing a
        // reader could look up — so it is reported as what it is.
        $name = $matches[1][$i][0] !== ''
            ? $matches[1][$i][0]
            : 'an interpolated class name';

        $found[] = ['name' => (string) $name, 'offset' => (int) $matches[0][$i][1]];
    }

    return $found;
}

/**
 * The static-access class references in a pattern STRING that no
 * case-insensitivity reaches.
 *
 * ⛔ **PER OCCURRENCE, NOT PER PATTERN, AND THAT IS THE POINT.**
 * {@see patternHasCaseInsensitivity()} answers *"does this pattern carry `/i`
 * or an inline `(?i:` ANYWHERE"*, which is the right question for the other
 * five shapes — each of those patterns names exactly one thing. It is the wrong
 * question here: a pattern may legitimately scope `(?i:…)` over one class name
 * (10256's `GbpTest` case) and then name a second one case-sensitively three
 * characters later, and a whole-pattern answer would call that covered.
 *
 * ⚠️ **A `(?i:…)` GROUP IS CREDITED ONLY WHILE IT IS STILL OPEN, AND "STILL
 * OPEN" IS ANSWERED BY THE CRUDEST TEST THAT CANNOT FAIL OPEN**: the nearest
 * `(?i:` before the class name, with no `)` of any kind between it and the
 * name. So `(?i:L1Event::|L2FactSession::)` credits both names — a real
 * alternation of class names under one flag, `WarehouseTest`'s shape — while
 * `(?i:Foo)::Bar|Baz::` credits `Foo` and reddens on `Baz`, which is the
 * finding this per-occurrence check exists for.
 *
 * ⛔ **A NESTED GROUP, AN ESCAPED `\)` OR A `[)]` CLASS INSIDE THE `(?i:`
 * READS AS CLOSED AND REDDENS.** That is deliberate: a regex parser written
 * inside a lint has its own bug surface, and the only bug that matters here is
 * one that says *covered* when nothing covers it. Every simplification above
 * errs the other way, and the remedy the message asks for — scope the flag
 * tightly around the class name — is what the site should have done anyway. A
 * trailing `/i` and an inline `(?i)` both cover every occurrence after them,
 * because both genuinely do.
 *
 * @return list<string> the offending class references, in source order
 */
function staticAccessesMissingCaseInsensitivity(string $pattern): array
{
    if (patternHasTrailingCaseInsensitiveFlag($pattern)) {
        return [];
    }

    $offenders = [];

    foreach (staticAccessClassNames($pattern) as $access) {
        $before = substr($pattern, 0, $access['offset']);

        if (str_contains($before, '(?i)')) {
            continue;
        }

        $scoped = strrpos($before, '(?i:');

        if ($scoped !== false && ! str_contains(substr($before, $scoped + 4), ')')) {
            continue;
        }

        $offenders[] = $access['name'];
    }

    return $offenders;
}

/**
 * The six regex shapes that, by PHP's own grammar, can only ever name a class
 * or an interface — never a property, an enum case, a class constant, or a
 * table/column name — so a hand-rolled pattern built on one of them needs
 * case-insensitivity every time (10250–10259, the argument this table is read
 * against rather than restated; the sixth is 10910–10939's).
 *
 * ⛔ **DELIBERATELY SIX, NOT EVERY SHAPE ANY WAVE HAS FOUND AND FIXED.** A bare
 * `->word(` method call is NOT here: it can equally be a property read
 * (case-sensitive), a class constant or enum case (case-sensitive), or a
 * genuine method (case-insensitive), and telling those apart needs reflection
 * against the real class or a human reading the call site —
 * `PlatformCredentials::(get|has)`, `Tenancy::actingAs`, `->consentRecords(`
 * and every other method-call fix 10250's wave made were each read and verified
 * by hand, not matched by this list. ⚠️ **`::word(` IS NO LONGER IN THAT
 * SENTENCE**, because the sixth shape reads the token BEFORE the `::` rather
 * than after it, and that token is always a class reference — see
 * {@see staticAccessShapeRegex()} for the whole argument. `use Namespace\Xxx;`
 * is excluded still, on a narrower argument than the others': 10257 found that
 * a genuinely mis-cased import fails PSR-4 autoloading outright on this
 * filesystem in the general case, so "always needs `/i`" is not the universal
 * claim for it that it is for the six below.
 *
 * @return array<string, string> label => a regex run against a PATTERN
 *                               STRING (the text captured by
 *                               {@see pregMatchCallPatterns()}), never
 *                               against source code directly
 */
function classNameOnlyRegexShapes(): array
{
    return [
        'a static-access exclusion (`Xxx::(?!class\b)`)' => '/\(\?!class\\\\b\)/',
        'a construction (`new Xxx`)' => '/\\\\bnew\\\\s/',
        'a caught type (`catch (Xxx`)' => '/catch\\\\s/',
        'an interface (`implements Xxx`)' => '/implements\\\\s/',
        'a parent class (`extends Xxx`)' => '/extends\\\\s/',
        staticAccessShapeLabel() => staticAccessShapeRegex(),
    ];
}

/**
 * Whether a pattern STRING closes with an `i` among its trailing regex flags.
 *
 * ⚠️ **ITS OWN FUNCTION BECAUSE TWO CALLERS NEED EXACTLY THIS HALF AND ONE OF
 * THEM MUST NOT HAVE THE OTHER HALF** (8460): {@see patternHasCaseInsensitivity()}
 * unions it with "an inline `(?i:` anywhere", and
 * {@see staticAccessesMissingCaseInsensitivity()} may not, because "anywhere"
 * is the whole-pattern answer it exists to refuse. A re-typed twin of this
 * regex in the second caller is the shape `CLAUDE.md` names — correct in both
 * copies today and one edit from not being.
 */
function patternHasTrailingCaseInsensitiveFlag(string $pattern): bool
{
    return (bool) preg_match('/\/[a-zA-Z]*i[a-zA-Z]*[\'"]?\s*$/', rtrim($pattern));
}

/**
 * Whether a pattern STRING (not source code) already carries case
 * insensitivity over the shape it was matched on — a trailing `/i` (with
 * other flags in any order), or an inline `(?i:…)` group.
 *
 * ⚠️ **THE INLINE FORM EXISTS BECAUSE A TRAILING `/i` IS SOMETIMES WRONG**
 * (10256): `GbpTest`'s Google-review-source value also matches the literal
 * string `'google'` in the SAME pattern, which must stay case-sensitive as
 * data. `(?i:ReviewSource::(?:try)?from)` scopes the flag to exactly the
 * class-name-and-method-name portion — this function accepts either spelling
 * as satisfying the requirement, because both are real ways of answering it.
 *
 * ⛔ **THIS IS A WHOLE-PATTERN ANSWER AND THE STATIC-ACCESS SHAPE DOES NOT USE
 * IT** — see {@see staticAccessesMissingCaseInsensitivity()} for why a pattern
 * naming two class names cannot be answered once.
 */
function patternHasCaseInsensitivity(string $pattern): bool
{
    return patternHasTrailingCaseInsensitiveFlag($pattern)
        || str_contains($pattern, '(?i:')
        || str_contains($pattern, '(?i)');
}

/**
 * Every `preg_match`/`preg_match_all` pattern in `$code` naming one of
 * {@see classNameOnlyRegexShapes()}'s shapes with no case-insensitivity
 * anywhere on it.
 *
 * ⚠️ **THE STATIC-ACCESS SHAPE IS CHECKED ALONGSIDE THE OTHER FIVE RATHER THAN
 * INSIDE THEIR `break`** (10910–10939). The five are mutually exclusive in
 * practice and a pattern is counted once against them; a `::` reference can sit
 * in the same pattern as any of them — `'/\bnew\s+Foo\b|Bar::BAZ/'` is one
 * pattern with two subjects — so folding it into that loop would let the first
 * matching shape consume the pattern and hide the second finding.
 *
 * @return list<array{line: int, shape: string, pattern: string}>
 */
function regexPatternsMissingCaseInsensitivity(string $code): array
{
    $offences = [];

    foreach (pregMatchCallPatterns($code) as $call) {
        foreach (staticAccessesMissingCaseInsensitivity($call['pattern']) as $name) {
            $offences[] = [
                'line' => $call['line'],
                'shape' => staticAccessShapeLabel().' — '.$name,
                'pattern' => $call['pattern'],
            ];
        }

        foreach (classNameOnlyRegexShapes() as $label => $shapeRegex) {
            if ($label === staticAccessShapeLabel()) {
                continue;
            }

            if (preg_match($shapeRegex, $call['pattern']) !== 1) {
                continue;
            }

            if (! patternHasCaseInsensitivity($call['pattern'])) {
                $offences[] = ['line' => $call['line'], 'shape' => $label, 'pattern' => $call['pattern']];
            }

            break;
        }
    }

    return $offences;
}

/**
 * The text a carrier-flag reader's claim tokens are checked against —
 * `MessagingTest`'s *"no reader of the carrier flag treats an unknown outcome
 * as a send"*.
 *
 * ⛔ **REPLACES A FIXED `substr($chunk, 0, 400)` WINDOW, WHICH WAS BOTH A FALSE
 * NEGATIVE AND A FALSE POSITIVE WAITING TO HAPPEN — MEASURED 2026-08-27
 * (10480-10499).** A raw character count has no relationship to where a
 * statement actually ends: `SendReviewInviteJob.php`'s `match` arm order was
 * reordered in production code purely to push a legitimate, unrelated
 * `InviteAttemptStatus::Sent` past character 400, and the margin the scout
 * measured — 259 characters — is one deleted `match` arm from re-tripping the
 * false positive the reorder was hiding.
 *
 * $chunk starts immediately AFTER the `mayHaveReachedCarrier` token, so any
 * `)` or `}` encountered before this function has seen a matching `(` or `{`
 * closes something that was opened BEFORE the split point — it is ignored
 * rather than treated as going negative. The window ends at the first `;`
 * seen while both depths are back at zero (the rest of the statement), or —
 * if the read is inside something that opens a block, such as an `if` — at
 * the matching close of that block (the block it opens, per the comment this
 * function's caller already carried and could not enforce). A 2,000-character
 * ceiling is a safety valve for a run-on line, never the intended boundary.
 */
function carrierClaimStatementWindow(string $chunk): string
{
    $braceDepth = 0;
    $parenDepth = 0;
    $ceiling = min(strlen($chunk), 2000);

    for ($i = 0; $i < $ceiling; $i++) {
        $char = $chunk[$i];

        if ($char === '(') {
            $parenDepth++;
        } elseif ($char === ')') {
            if ($parenDepth > 0) {
                $parenDepth--;
            }
        } elseif ($char === '{') {
            $braceDepth++;
        } elseif ($char === '}') {
            if ($braceDepth > 0) {
                $braceDepth--;

                if ($braceDepth === 0) {
                    return substr($chunk, 0, $i + 1);
                }
            }
        } elseif ($char === ';' && $braceDepth === 0 && $parenDepth === 0) {
            return substr($chunk, 0, $i + 1);
        }
    }

    return substr($chunk, 0, $ceiling);
}

/**
 * Whether `$code` contains an `orderByRaw(...)` literal with a `DESC` clause
 * that has no paired `NULLS LAST` — `ConventionsTest`'s *"no descending order
 * relies on Postgres putting NULLs first"*, the `orderByRaw` arm.
 *
 * ⛔ **REPLACES A GREEDY `[^'"]*` THAT ONLY EVER TESTED THE LAST `DESC` IN THE
 * LITERAL — MEASURED 2026-08-27 (10480-10499).** `[^'"]*\bDESC\b` backtracks
 * from the end of the string, so a multi-column literal such as
 * `'created_at desc, id desc nulls last'` was tested only against its
 * trailing `id desc nulls last` and never flagged the unpaired `created_at
 * desc` at the front — a false green on exactly the column this lint exists
 * to catch, with the exempt, non-nullable `id` column carrying the remedy.
 * This checks every comma-separated clause of every `orderByRaw` literal
 * independently, so a `DESC` anywhere in the list without its own `NULLS
 * LAST` is caught regardless of what a later clause in the same string says.
 */
function orderByRawMissingNullsLast(string $code): bool
{
    if (preg_match_all('/orderByRaw\s*\(\s*[\'"]([^\'"]*)[\'"]/i', $code, $matches) === 0) {
        return false;
    }

    foreach ($matches[1] as $literal) {
        foreach (explode(',', $literal) as $clause) {
            if (preg_match('/\bDESC\b/i', $clause) === 1 && preg_match('/NULLS\s+LAST/i', $clause) !== 1) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Every string a `Foo::BAR` constant reference in this source resolves to.
 *
 * ⛔ **THE POINT IS THAT IT RESOLVES NOTHING BY NAME.** `RetentionTest`'s
 * *"a horizon whose period is an operator setting names the row its own pruner
 * reads"* needs to know which registry key a command actually reads, and the
 * obvious matcher is `/(\w+)::RETENTION_KEY/` — which is a lint holding its own
 * copy of a naming convention — `CLAUDE.md` §"What a chokepoint lint owes, and
 * none of it is the lint itself": *"A lint holding its own copy of the pattern
 * its guard reads"* is 8460's shape even when both copies are correct today.
 * ⚠️ **THIS CITED §"Three properties every chokepoint lint owes" UNTIL
 * 2026-08-28 (11596)**, a heading that no longer exists — renamed at 11398
 * because it said *"Three properties"* over four bullets — and it named the
 * bullet by its ORDINAL, which the same edit made fragile. **The bullet's own
 * words are quoted instead, so the citation survives the next reordering.** **A pruner whose constant is called `PERIOD_KEY`
 * would be invisible to it and the horizon beside it could name any row at
 * all.** This resolves every constant reference in the file and lets the
 * caller ask which of the resulting strings are registry keys, so the
 * convention is never the thing being matched.
 *
 * ⚠️ **THE CLASS HALF IS MATCHED CASE-INSENSITIVELY AND THE CONSTANT HALF IS
 * NOT, AND BOTH HALVES ARE DELIBERATE** (10203, and `_COMMON.md`'s correction
 * to it). **PHP resolves a class name case-insensitively**, so
 * `pruneownerchannel::RETENTION_KEY` is valid and an import map keyed on the
 * author's own spelling would miss it; **a class constant is case-SENSITIVE**,
 * so `::retention_key` is a different name and matching it would resolve a
 * constant that does not exist. `RetentionTest`'s floor drives both spellings
 * through this function rather than asserting the property in a comment.
 *
 * ⚠️ **ONE HOP AND NO FURTHER.** `self::`, `static::` and any class imported by
 * a `use` statement in the same file resolve; a constant reached through a
 * variable, a string class name or a second import does not. **That is a floor
 * rather than a census**, and the caller must not read an empty result as
 * "this file reads no registry key".
 *
 * @param  string  $source  the PHP source, so a test can drive it with a
 *                          fragment rather than only with a file on disk
 * @return list<string> every distinct string value, in first-seen order
 */
function stringConstantsResolvedIn(string $source): array
{
    $imports = [];

    if (preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+(\w+))?\s*;/mi', $source, $matches, PREG_SET_ORDER) !== 0) {
        foreach ($matches as $one) {
            $fqcn = $one[1];
            $alias = ($one[2] ?? '') !== ''
                ? $one[2]
                : substr($fqcn, (int) strrpos($fqcn, '\\') + 1);

            $imports[mb_strtolower($alias)] = $fqcn;
        }
    }

    $own = null;

    if (preg_match('/^namespace\s+([A-Za-z0-9_\\\\]+)\s*;/mi', $source, $namespace) === 1
        && preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/mi', $source, $class) === 1) {
        $own = $namespace[1].'\\'.$class[1];
    }

    $values = [];

    if (preg_match_all('/([A-Za-z_][A-Za-z0-9_\\\\]*)\s*::\s*([A-Z][A-Z0-9_]*)\b/', $source, $matches, PREG_SET_ORDER) === 0) {
        return [];
    }

    foreach ($matches as [, $short, $constant]) {
        $lower = mb_strtolower($short);

        $fqcn = in_array($lower, ['self', 'static'], true)
            ? $own
            : ($imports[$lower] ?? null);

        if ($fqcn === null || ! class_exists($fqcn) || ! defined($fqcn.'::'.$constant)) {
            continue;
        }

        $value = constant($fqcn.'::'.$constant);

        if (is_string($value)) {
            $values[$value] = true;
        }
    }

    return array_keys($values);
}

/**
 * The case-SENSITIVE tokens a pattern's trailing `/i` wrongly covers — wave 41
 * lane D (11079).
 *
 * ⛔ **10913's RULE IS *"NEVER A BLANKET `/i`"* AND NOTHING ENFORCED IT.**
 * {@see staticAccessesMissingCaseInsensitivity()} returns `[]` on any trailing
 * `/i`, so a blanket flag **satisfies** the sixth shape — which is right about
 * the class name and says nothing about everything else the flag now covers.
 * The canonical remedy that rule states is the scoped `(?i:Xxx::)Case` form,
 * and until this function nothing could tell a pattern that used it from one
 * that reached for `/i` and moved on.
 *
 * ⛔ **PHP DISPATCHES A CLASS NAME, A METHOD AND A FUNCTION CASE-INSENSITIVELY
 * AND A CLASS CONSTANT, A BACKED-ENUM CASE AND A PROPERTY CASE-SENSITIVELY**
 * (`_COMMON.md`'s correction to 10203). So `creditkind::Refund` is the same
 * enum case and `CreditKind::REFUND` is a **different** constant that does not
 * exist — and a blanket `/i` over `CreditKind::Refund` matches the second,
 * which is how a lint stops distinguishing two enum cases from each other.
 *
 * ⚠️ **THE SUBJECT IS THE TOKEN'S SHAPE AND NOT WHAT IT RESOLVES TO, WHICH IS
 * A DELIBERATE NARROWING WITH A MEASUREMENT BEHIND IT.** A regex text carries
 * no type information, so *"is `::from` a method or a constant?"* cannot be
 * answered from the pattern. Asking instead *"is this token followed by
 * `(`?"* was tried and flags **ten** correct patterns, because a chokepoint
 * usually ends at `\b` rather than at an open bracket
 * (`'/\bSendPermit::grant\b/i'`, `'/SendingNumber::from\b/i'`). What
 * distinguishes them reliably here is convention this codebase already
 * enforces: **a method is camelCase, a constant is `ALL_CAPS` and an enum case
 * is TitleCase** (`CLAUDE.md` §Style). Measured across `app/` and `tests/`:
 * **259 patterns carry a trailing `/i` and this shape flags exactly one.**
 *
 * ⚠️ **KNOWN NARROWNESS, STATED RATHER THAN DISCOVERED LATER** (565's rule): a
 * single-word `ALL_CAPS` token of two or more letters is flagged and a
 * one-letter one is not, a PascalCase *method* would be a false positive, and
 * a property (`::$name`) is out of scope because no pattern in this tree names
 * one. **The flag is not the whole rule** — a blanket `/i` also covers string
 * keys, SQL literals and English copy, and telling those apart needs a person.
 * ⛔ **So an empty result is not "this `/i` is fine".**
 *
 * @return list<string> the offending tokens, in first-seen order
 */
function blanketCaseInsensitivityOffences(string $pattern): array
{
    if (! patternHasTrailingCaseInsensitiveFlag($pattern)) {
        return [];
    }

    $shape = '/::(?!class\b)((?:[A-Z][a-z0-9][A-Za-z0-9]*)|(?:[A-Z][A-Z0-9]*_[A-Z0-9_]+)|(?:[A-Z]{2,}))(?![A-Za-z0-9_])/';

    if (preg_match_all($shape, $pattern, $matches) === 0) {
        return [];
    }

    return array_values(array_unique($matches[1]));
}

/**
 * A console command's own source file, plus the source of every `App\` class its
 * entry point is handed — wave 42 lane D, decision 11240.
 *
 * ⛔ **A PRUNER USUALLY DOES NOT READ ITS OWN RETENTION ROW, AND A LINT THAT
 * READS ONLY THE COMMAND FILE SAYS IT READS NOTHING.** Two of this
 * application's four period-driven pruners delegate: `PruneAutomationRuns`
 * calls `AutomationRunRetention::retentionDays()` and
 * `PruneReviewLossSnapshots` calls `GoogleRatingSnapshotRetention::retentionDays()`.
 * In both commands the **only** occurrence of the retention constant is inside
 * an operator-facing sentence naming the row it is set in — so
 * `RetentionTest`'s biconditional was, in those two cases, held up by a piece
 * of English copy. Rewording it reddened the build with a false failure, and
 * the obvious repair for a false failure (`'key' => null`) silently restores
 * the defect 11070-11076 closed.
 *
 * ⚠️ **THE INJECTED COLLABORATOR AND NOT EVERY IMPORT.** `handle()`'s and the
 * constructor's `App\` parameter types are the classes Laravel resolves *for*
 * this command; a `use` statement is anything the file mentions. Following
 * every import is wider than the relationship being modelled and risks the
 * opposite false failure — a horizon carrying no key looking as though its
 * pruner read one, because some unrelated dependency resolves an unseeded key.
 * ⚠️ **Measured across all sixteen horizon commands at the moment this was
 * written**: this hop adds exactly two keys, both of them the delegating
 * pruners' own, and adds nothing to any of the twelve horizons that carry no
 * key.
 *
 * ⛔ **ONE HOP AND DELIBERATELY NOT TRANSITIVE.** A second hop reaches
 * `DefaultsRegistry` from almost anywhere and would start resolving rows
 * nobody's pruner reads. **The narrowness is what keeps the exemption list at
 * zero**, which is `TableHorizons`' own refusal of a manufactured entry.
 *
 * ⚠️ **A BUILT-IN, A UNION, AN INTERSECTION AND A NON-`App\` TYPE ARE ALL
 * SKIPPED**, and a class with no file on disk (an internal or an anonymous
 * one) contributes nothing rather than throwing.
 *
 * ⚠️ **KNOWN NARROWNESS, STATED RATHER THAN DISCOVERED LATER** (565's rule). A
 * collaborator resolved **inside** a method body — `app(Foo::class)`,
 * `resolve()`, a facade, a static call — is invisible here, because the type is
 * not on any signature this can reflect. **So a pruner that moved its registry
 * read behind one of those would put the false failure back**, and the fix at
 * that point is to inject it rather than to widen this: an injected
 * collaborator is the relationship being modelled, and a container lookup in a
 * method body is any class in the application.
 *
 * @param  class-string  $class
 * @return list<string> absolute paths, the command's own first, each once
 */
function sourceFilesOfCommandAndItsInjectedCollaborators(string $class): array
{
    $reflection = new ReflectionClass($class);

    $files = [];

    $own = $reflection->getFileName();

    if ($own !== false) {
        $files[$own] = true;
    }

    $parameters = [];

    if ($reflection->hasMethod('handle')) {
        $parameters = $reflection->getMethod('handle')->getParameters();
    }

    if ($reflection->getConstructor() !== null) {
        $parameters = [...$parameters, ...$reflection->getConstructor()->getParameters()];
    }

    foreach ($parameters as $parameter) {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            continue;
        }

        $name = $type->getName();

        if (! str_starts_with($name, 'App\\') || ! class_exists($name)) {
            continue;
        }

        $path = (new ReflectionClass($name))->getFileName();

        if ($path !== false) {
            $files[$path] = true;
        }
    }

    return array_keys($files);
}

/**
 * Every numbered row in `docs/DECISIONS.md`, in file order.
 *
 * ⛔ **ONE PARSER, BECAUSE TWO LINTS ASK ABOUT THE SAME ROWS AND A SECOND COPY
 * OF THIS PATTERN WOULD BE 8460's SHAPE EVEN WHILE BOTH COPIES AGREED**
 * (`CLAUDE.md` §Convention tests). `ConventionsTest`'s *no decision number
 * appears twice* asks which numbers exist; `CitationTest`'s line-citation lint
 * asks which row owns a given line. **Those are two questions about one
 * addressing scheme**, and the day somebody adds a third row spelling, a
 * private copy in either file goes quietly blind to it — which is exactly how
 * the prose form went unwatched for a whole wave (10010).
 *
 * ⚠️ **TWO SPELLINGS AND THE SECOND IS RARE, WHICH IS WHY IT NEEDS NAMING.**
 * Almost every row is a table row — `| 9899 | … |` — and a **prose** form
 * exists and is in use: `**9895. …`, opening a paragraph rather than a cell.
 * Both are legitimate; wave 32 split four–two between them because nothing had
 * said which (10010, and `BUILDER-PROMPT.md` §50).
 *
 * ⚠️ **THE DIGIT BOUND IS DELIBERATELY ABSENT.** `\d+` has no ceiling to
 * outgrow; `(\d{1,4})` had one and the wave that crossed 10000 would have
 * walked straight through it, silently (10000). The same bug had already
 * happened once in `agents/roster.sh`, in the tool built to prevent it.
 *
 * ⛔ **`form` IS RETURNED RATHER THAN THE TWO LISTS**, because the callers'
 * anti-vacuity floors are **per spelling** — a floor over the union passes at
 * 8,000-plus table rows while every prose row goes unread, which is
 * `CLAUDE.md`'s *an anti-vacuity floor guards the axis it was written for and
 * no other*.
 *
 * @return list<array{number: int, form: string, index: int}> `index` is the
 *                                                            0-based line index of the row's first line
 */
function decisionRows(string $document): array
{
    $rows = [];

    foreach (preg_split('/\R/', $document) ?: [] as $index => $line) {
        if (preg_match('/^\|\s*(\d+)\s*\|/', $line, $matches) === 1) {
            $rows[] = ['number' => (int) $matches[1], 'form' => 'table', 'index' => $index];

            continue;
        }

        if (preg_match('/^\*\*(\d+)\.\s/', $line, $matches) === 1) {
            $rows[] = ['number' => (int) $matches[1], 'form' => 'prose', 'index' => $index];
        }
    }

    return $rows;
}
