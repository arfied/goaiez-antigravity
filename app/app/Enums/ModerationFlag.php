<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a first-party review is withheld from display (`17` FPR-02).
 *
 * FIRST-PARTY ONLY, AND THE DISTINCTION IS A RULE RATHER THAN A SCOPE. `29` §2
 * rule 1: a Google review is never held, hidden, approved, or moderated. These
 * flags exist because a first-party review is text this platform would publish
 * on the tenant's own surfaces under the tenant's own name — moderating what we
 * publish is not the same act as moderating what Google publishes, and only the
 * first is ours to do.
 *
 * `Refused` IS NOT AN ERROR. A model declining to classify text returns a
 * successful HTTP 200 (see AiResponse). The classifier balking at customer
 * writing is signal that the writing is awkward, so it withholds for a human to
 * read rather than passing as clean — but nothing failed, and it is not recorded
 * as a failure.
 *
 * TWO OF THESE ARE OURS AND ARE NEVER OFFERED TO A MODEL — see
 * modelChoosable(). `Refused` and `Unrecognised` describe what happened to the
 * *verdict*, not what is in the text, and a model allowed to pick either could
 * make its own answer indistinguishable from the platform's reading of it.
 */
enum ModerationFlag: string
{
    case Harassment = 'harassment';
    case HateSpeech = 'hate_speech';
    case Sexual = 'sexual';
    case Violence = 'violence';

    /** Commercial solicitation or bot text. Never an angry or repetitive customer. */
    case Spam = 'spam';

    /**
     * An UNRELATED third party's contact details in the review body.
     *
     * Never a staff member or the business named in a complaint: "Dr Patel at 14
     * Mill Street left me waiting ninety minutes" is an honest negative review,
     * and withholding it would be covert review gating dressed as moderation.
     * The carve-out is stated in ReviewModerator's system prompt too, because
     * this docblock is not something the model ever reads.
     */
    case PersonalData = 'personal_data';

    /** The model declined to classify this text. Not a failure; see above. */
    case Refused = 'refused';

    /**
     * The model named a reason this vocabulary has no word for.
     *
     * OUR LABEL, NOT AN INVENTED CATEGORY, and that is what keeps decision 344
     * intact: it does not claim the review is spam or harassment, it records
     * that the classifier asked for it to be withheld in terms nobody here can
     * read. Withholding on that is the fail-closed answer — the alternative,
     * which shipped and was found by the whole-branch review, was publishing
     * text the model had asked to hold back.
     */
    case Unrecognised = 'unrecognised';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * The vocabulary a model is allowed to choose from.
     *
     * Storable is a wider set than choosable, deliberately. `Refused` is
     * produced by reading `AiResponse::$refused`, and a model that could *also*
     * return the string "refused" would make a genuine refusal and a classified
     * one the same row — which is exactly the separation decision 347 works to
     * keep. `Unrecognised` is the platform's own reading of an unreadable
     * answer, so offering it as an option would let a model assert it.
     *
     * @return list<string>
     */
    public static function modelChoosable(): array
    {
        $choosable = [];

        foreach (self::cases() as $case) {
            if ($case->isPlatformWritten()) {
                continue;
            }

            $choosable[] = $case->value;
        }

        return $choosable;
    }

    /**
     * Whether this flag describes the verdict rather than the text.
     *
     * A foreach and this predicate rather than array_filter(): filtering out a
     * case that is not last leaves a gap in the keys, and the resulting array is
     * no longer the `list<string>` a JSON schema's `enum` must be — it would
     * serialise as an object. The two cases happen to sit at the end today,
     * which is exactly the kind of accident a later reordering breaks silently.
     */
    public function isPlatformWritten(): bool
    {
        return match ($this) {
            self::Refused, self::Unrecognised => true,
            self::Harassment, self::HateSpeech, self::Sexual, self::Violence, self::Spam, self::PersonalData => false,
        };
    }

    /**
     * A value a model produced, or null.
     *
     * Dropped rather than coerced, which is the opposite of ReviewTheme and is
     * deliberate: inventing a withholding reason outside this vocabulary would
     * withhold a customer's review for a reason no human can read or appeal.
     *
     * DROPPING IS NOT THE SAME AS CLEARING. ReviewModerator turns "every value
     * the model named was dropped" into `Unrecognised` rather than into an empty
     * flag list, because an empty flag list publishes the review.
     */
    public static function tryFromModel(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
