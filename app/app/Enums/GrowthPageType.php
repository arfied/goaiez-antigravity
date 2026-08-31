<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of page a growth-page candidate is — doc `33`'s content brief:
 * *"target page type (post/service/area/landing)"*, plus the careers page doc
 * `16` §1 and §8 name as the one legitimate use of Google's Indexing API.
 *
 * ⚠️ **THE FIVE ARE TAKEN FROM THE DOCUMENTS AND NOT INVENTED HERE.** `33`
 * names four and `16` names the fifth; nothing else in the source material
 * enumerates a page kind, so nothing else is declared. A sixth arrives with the
 * slice that has a document behind it.
 *
 * ⛔ **`Careers` IS DECLARED AND ITS SOURCE OF TRUTH IS AN OPEN QUESTION**
 * (`BUILD-PLAN` §2.11.6 question 4): *"nothing in the schema holds a job opening
 * and no surface captures one"*. Declaring the word costs nothing and lets slice
 * E's Indexing-API guard be written against a vocabulary rather than a string
 * literal; **generating one is blocked on the owner answering where a job comes
 * from**, and inventing a tenant-facing job editor to unblock it would collide
 * with the no-toggle rule.
 *
 * ⚠️ **THIS IS NOT THE PAGE'S SCHEMA MARKUP AND MUST NEVER BE READ AS IT.**
 * Slice E's build-failing gate — *no non-`JobPosting` URL reaches the Indexing
 * API* — is asserted against the markup actually on the page, because the type
 * recorded here is our intention and the markup is the fact Google will read.
 */
enum GrowthPageType: string
{
    /** A blog post. `33`: "post". */
    case Post = 'post';

    /** A page about one service the business performs. `33`: "service". */
    case Service = 'service';

    /** A page about one place the business serves. `33`: "area". */
    case Area = 'area';

    /** A conversion-shaped landing page. `33`: "landing". */
    case Landing = 'landing';

    /**
     * A careers page carrying `JobPosting` markup. `16` §8.
     */
    case Careers = 'careers';
}
