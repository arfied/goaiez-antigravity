<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Services\Config\DefaultsRegistry;
use App\Services\Messaging\Composer\NameNormaliser;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * The personalised-picture pipeline — `SL-2`'s *"MMS personalized-pic"*, and the
 * record's own wording: *"1-pic MMS with the customer's name on the owner's
 * photo, SMS fallback"*.
 *
 * ## The fallback is the feature, not the failure case
 *
 * ⚠️ **EVERY REASON THIS CANNOT PRODUCE A PICTURE ENDS IN A PLAIN SMS, AND
 * NEVER IN A FAILED SEND.** No base photo, no usable first name, no readable
 * font, an unreadable image, an overlay that ends up too large — each of them
 * returns null and the campaign texts the person anyway. An MMS pipeline that
 * could fail a send would make a decoration into a dependency, and it would do
 * it at the moment somebody is running a campaign rather than at the moment
 * somebody is configuring one.
 *
 * ⚠️ **NO NAME MEANS NO PICTURE, DELIBERATELY.** The whole content of the
 * overlay is the person's own name; `ReactComposer` has *"there"* for the
 * sentence, and there is no equivalent for a photograph — a picture of the
 * owner's shopfront with the word "there" painted across it is worse than the
 * shopfront on its own, and much worse than no picture. {@see NameNormaliser}
 * answers null and this stops.
 *
 * ## What is stored, where, and for how long
 *
 * ⛔ **THE RENDERED IMAGE CARRIES A REAL PERSON'S NAME, SO IT IS NOT ON A PUBLIC
 * DISK.** It goes to the private disk under an unguessable path and is reached
 * through a **signed, expiring** route, so a URL that leaks out of a carrier's
 * logs stops working. `29` §2's rule is about identifiers in URLs and this is
 * its neighbour: the name is in the bytes rather than the path, and the path is
 * still not something to leave enumerable.
 *
 * ⚠️ **THE WINDOW HAS TO COVER A CARRIER'S OWN FETCH AND ITS RETRIES**, which is
 * why it is a registry key rather than an hour somebody picked — an expiry
 * shorter than a carrier's retry schedule produces an MMS that arrives with a
 * broken picture, and nothing in this application would ever hear about it.
 *
 * ## The size and shape ceiling
 *
 * The T101 rider refines `REACT-1`'s targets to **≤500 KB** and a **9:16**
 * frame. Both are enforced here rather than assumed: an over-size MMS is
 * rejected by the carrier rather than by us, which is the failure that arrives
 * as a delivery receipt nobody reads.
 */
final class CampaignMedia
{
    /** The T101 rider's ceiling. Bytes, not kilobytes, to avoid the rounding. */
    private const int MAX_BYTES = 500_000;

    /** The T101 rider's frame. Portrait, as a phone holds it. */
    private const int WIDTH = 1080;

    private const int HEIGHT = 1920;

    public function __construct(
        private readonly DefaultsRegistry $registry,
        private readonly NameNormaliser $names,
    ) {}

    /**
     * A fetchable URL for this recipient's picture, or null for a plain SMS.
     */
    public function urlFor(Campaign $campaign, CampaignRecipient $recipient, Customer $customer): ?string
    {
        $path = $this->renderFor($campaign, $recipient, $customer);

        if ($path === null) {
            return null;
        }

        return URL::temporarySignedRoute('campaign.media', $this->expiry(), [
            // ⚠️ **THE TENANT TRAVELS IN THE SIGNED URL BECAUSE THE ROW CANNOT
            // BE READ WITHOUT IT.** `campaign_recipients` is tenant-owned and
            // RLS-forced, and a carrier arrives with no session, no cookie and
            // no host to resolve a tenant from — the same shape `feedback_pages`
            // solved by being un-tenanted, which is not open to a table holding
            // send records. The signature is what makes the parameter
            // trustworthy: an unsigned or edited URL is refused before the
            // controller runs.
            'business' => $recipient->business_id,
            'recipient' => $recipient->getKey(),
        ]);
    }

    /**
     * Destroy every rendered picture this account ever sent.
     *
     * ⛔ **THIS IS THE KIND THE RECORD SAYS CANNOT BE SWEPT, AND THE RECORD IS
     * RIGHT ABOUT THE WRONG MOMENT** (8876, 8948(d)). The path is
     * `campaign-media/{campaign}/…` — **no business id anywhere in it** — and
     * `campaigns.business_id` cascades, so *after* an erasure there is no id
     * left to build a prefix from and no manual sweep is available. All of that
     * is true. **What it does not follow from is that the erasure cannot do
     * it**: `TenantDeletion::execute()` runs inside `Tenancy::actingAs()` and
     * **before** `$business->delete()`, so at the one moment that matters every
     * one of this tenant's campaign ids is still readable. Reading before the
     * transaction and acting on what was read is the ordering
     * `TenantDeletion::uncreditedPayments()` already uses for the same reason:
     * *after the commit there is nothing left to read*.
     *
     * ⛔ **SO THE HAZARD IS NOT "NO PREFIX EXISTS", IT IS "NO PREFIX SURVIVES"**,
     * and the fix is to sweep while it is still there rather than to change the
     * path format. Changing the format would be a migration plus a backfill over
     * objects only the bucket can enumerate, and it would buy a manual
     * remediation nobody would ever run.
     *
     * ⛔ **THE ERASURE USED TO MAKE THESE PICTURES PERMANENT** (8871), and this
     * is the largest writer in the application — **one object per recipient**
     * (4762) — so a thousand-recipient campaign is a thousand objects. Every one
     * of them has a named living person's first name burnt into the pixels,
     * which is why the class docblock keeps them off a public disk; a leaked
     * object is personal data whether or not a row points at it.
     *
     * ⚠️ **PER CAMPAIGN RATHER THAN PER RECIPIENT ROW**, which is
     * `ExportBuilder::purgeAllFor()`'s reasoning: `renderFor()` puts the bytes
     * and *then* saves `media_path`, so a failure between the two leaves an
     * object no row names, and a row-driven delete cannot see it. A campaign id
     * is globally unique, so a prefix built from one can only ever hold this
     * tenant's objects.
     *
     * ⚠️ **THE DISK IS `disk()` — THE WRITER'S OWN METHOD**, not a repeated
     * literal. `StorageTest` exists to keep `StorageRetention`'s copy of the
     * string in step with this class; a third copy here would be a third thing
     * to keep in step, and calling the method is the version that cannot drift.
     *
     * ⛔ **`campaigns.base_image_path` IS DELIBERATELY NOT TOUCHED**, and the
     * reason is not that it is writerless. It **is** writerless — `Campaigns::
     * create()` takes `?string $baseImagePath = null` and no caller in `app/`
     * supplies one (4956, re-verified: the only callers of that method are in
     * `tests/`) — but a purge written now would have to guess a disk, and the
     * uploader that eventually writes the column may well not choose this one. A
     * purge that names the wrong disk deletes nothing and reads as coverage,
     * which is the failure the erasure exists to prevent. `StorageRetention`
     * already rules that whoever builds the uploader adds the kind, the size
     * column, the retention key and its prune arm in the same change; **this
     * method is a fifth thing in that list**, and it is stated here rather than
     * pre-built.
     *
     * ⚠️ **THE COST IS TWO STORE CALLS PER CAMPAIGN, AND IT IS ACCEPTED RATHER
     * THAN OPTIMISED.** Narrowing the walk to campaigns whose recipients name a
     * `media_path` would be cheaper and would reintroduce exactly the orphan the
     * prefix sweep exists to catch. An erasure happens once per account for
     * ever, and a local business has tens of campaigns rather than thousands, so
     * `CLAUDE.md`'s marginal-cost tiebreaker is the third of three here and the
     * second one — less stored personal data — points the other way.
     *
     * @return bool false when anything may still be there, which refuses the
     *              whole deletion rather than completing it with the picture
     *              surviving
     */
    public function purgeAllFor(): bool
    {
        // ⚠️ **NO ARGUMENT, AND THE ABSENCE IS THE FINDING.** Every other purge
        // in this erasure takes a business id because the id is in the path.
        // Here it is not, so there is nothing honest to do with one — the tenant
        // arrives through `disk()`, which fails closed on it, and the prefixes
        // come from rows the tenant scope has already filtered.
        $campaignIds = Campaign::query()->orderBy('id')->pluck('id')->all();

        // ⛔ **THERE IS NO `if ($campaignIds === []) { return true; }` HERE, AND
        // THERE WAS UNTIL A MUTATION COULD NOT REDDEN IT** (9011). Every sibling
        // purge needs an explicit guard so that an account which stored nothing
        // never asks a store to delete anything; this one gets the same property
        // from the loop below, because a tenant with no campaigns has no prefix
        // to build and the body never runs. The early return was therefore a
        // branch nothing could drive red — 256's shape, in the slice that
        // added it — and the honest version is one instrument rather than two.
        //
        // ⚠️ **`disk()` FAILS CLOSED ON THE TENANT AND IS DELIBERATELY NOT
        // WRAPPED.** A missing tenant here is a programming error, and turning
        // it into a `false` would report *"the bucket is unreachable"* and defer
        // every statutory erasure on the platform with a sentence sending an
        // operator to check credentials that are fine.
        $disk = $this->disk();
        $clear = true;

        foreach ($campaignIds as $campaignId) {
            $prefix = 'campaign-media/'.$campaignId;

            try {
                $disk->deleteDirectory($prefix);

                if ($disk->allFiles($prefix) !== []) {
                    $clear = false;
                }
            } catch (Throwable) {
                // ⚠️ Refuses rather than throws, decision 823's rule: this is
                // reached from a sweep that walks every due deletion, and one
                // unreachable prefix must not abandon the rest of the queue.
                $clear = false;
            }
        }

        return $clear;
    }

    /**
     * Render the overlay if it is not already on disk, and answer its path.
     */
    private function renderFor(Campaign $campaign, CampaignRecipient $recipient, Customer $customer): ?string
    {
        if ($campaign->base_image_path === null) {
            return null;
        }

        if ($recipient->media_path !== null && $this->disk()->exists($recipient->media_path)) {
            // Already rendered on an earlier pass. Re-rendering would be a
            // second image for one send, and the first one is what the recipient
            // row already points at.
            return $recipient->media_path;
        }

        $name = $this->names->firstName($customer->name);

        if ($name === null) {
            return null;
        }

        $font = $this->fontPath();

        if ($font === null) {
            // The application ships a font (4365–4368), so reaching this means
            // the file is genuinely missing on this box or the key has been
            // pointed somewhere unreadable. Stated in the log rather than
            // thrown: the campaign still sends, as plain text, and the operator
            // needs to know why the pictures stopped rather than why the sends
            // did.
            Log::warning('No overlay font is readable, so campaign MMS is falling back to SMS.', [
                'campaign_id' => $campaign->getKey(),
            ]);

            return null;
        }

        $bytes = $this->compose($campaign->base_image_path, $name, $font);

        if ($bytes === null) {
            return null;
        }

        $path = 'campaign-media/'.$campaign->getKey().'/'.Str::random(40).'.jpg';

        // ⛔ **THE RETURN IS CHECKED, AND IT WAS NOT** (9405). Every disk in
        // `config/filesystems.php` is configured `'throw' => false`, so a write
        // this store refuses is a `false` and not an exception — and the three
        // lines below used to run regardless. What that produced is the shape
        // 1903 found on the export disk and 4517 found on the voicemail one,
        // arriving here in its most expensive form: `media_path` named an object
        // that is not there, `urlFor()` signed a URL for it, and `compose()`
        // attached it — so the recipient's message became an **MMS**, which
        // `MessageCostKind` prices at two credits rather than one, and the
        // carrier fetching that URL met `CampaignMediaController`'s 404. The
        // tenant paid twice for a text with no picture on it and nothing
        // anywhere said so.
        //
        // ⚠️ **NULL RATHER THAN A THROW, WHICH IS THIS CLASS'S OWN RULE** —
        // `compose()`'s docblock four methods down: *"every failure here returns
        // null rather than throwing"*, because a marketing send to one named
        // person must not become a failed job retried three times. Returning
        // null degrades the send to the plain SMS it can still be, at the one
        // credit it is worth, which is `29` §2 rule 43's surviving half.
        //
        // ⚠️ **LOGGED AT WARNING BESIDE THE FONT BRANCH ABOVE**, and for its
        // reason: the operator needs to know why the pictures stopped rather
        // than why the sends did. No recipient, no name and no path — the
        // campaign id is the whole of it.
        if ($this->disk()->put($path, $bytes) === false) {
            Log::warning('A campaign picture could not be stored, so this send is falling back to SMS.', [
                'campaign_id' => $campaign->getKey(),
            ]);

            return null;
        }

        $recipient->media_path = $path;
        // ⚠️ **THE SIZE IS RECORDED BECAUSE THIS IS THE LARGEST OBJECT WRITER IN
        // THE APPLICATION** (4762). Every other kind writes one object per
        // account or per event; this writes **one per recipient**, so a
        // thousand-recipient campaign is a thousand objects where an export is
        // one. `StorageFootprint` sums this column, and without it the footprint
        // would omit its biggest line while reading as complete.
        $recipient->media_bytes = strlen($bytes);
        $recipient->save();

        return $path;
    }

    /**
     * Draw the name onto the base photograph, in the T101 frame and under the
     * T101 ceiling.
     *
     * ⚠️ **EVERY FAILURE HERE RETURNS NULL RATHER THAN THROWING.** A malformed
     * upload, a GD build without JPEG, a font that will not load: each of them
     * would otherwise turn one bad picture into a failed marketing send for one
     * named person, retried three times by the queue.
     */
    private function compose(string $basePath, string $name, string $font): ?string
    {
        if (! $this->disk()->exists($basePath)) {
            Log::warning('A campaign names a base image that is not on the disk.');

            return null;
        }

        $source = @imagecreatefromstring((string) $this->disk()->get($basePath));

        if ($source === false) {
            Log::warning('A campaign base image could not be decoded.');

            return null;
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        // Cover rather than stretch: a shopfront squashed into portrait is a
        // worse advertisement than one cropped to it.
        $this->cover($source, $canvas);
        imagedestroy($source);

        $this->drawName($canvas, $name, $font);

        $bytes = $this->encodeUnder(self::MAX_BYTES, $canvas);
        imagedestroy($canvas);

        return $bytes;
    }

    /**
     * Scale and centre-crop the source to fill the canvas.
     *
     * @param  \GdImage  $source
     * @param  \GdImage  $canvas
     */
    private function cover($source, $canvas): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $scale = max(self::WIDTH / $sourceWidth, self::HEIGHT / $sourceHeight);
        $cropWidth = (int) round(self::WIDTH / $scale);
        $cropHeight = (int) round(self::HEIGHT / $scale);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            (int) round(($sourceWidth - $cropWidth) / 2),
            (int) round(($sourceHeight - $cropHeight) / 2),
            self::WIDTH,
            self::HEIGHT,
            $cropWidth,
            $cropHeight,
        );
    }

    /**
     * Put the name across the lower third, on a band that keeps it legible.
     *
     * ⚠️ **THE BAND IS NOT DECORATION.** White text on an unknown photograph is
     * unreadable about half the time, and the half it is unreadable on is
     * whichever picture the tenant happened to upload. The band makes the
     * contrast a property of the render rather than of the input, which is the
     * only version of this that is safe to run unattended.
     *
     * @param  \GdImage  $canvas
     */
    private function drawName($canvas, string $name, string $font): void
    {
        $size = 72;
        $bandTop = (int) (self::HEIGHT * 0.62);
        $bandHeight = 220;

        $band = imagecolorallocatealpha($canvas, 0, 0, 0, 45);
        $ink = imagecolorallocate($canvas, 255, 255, 255);

        if ($band === false || $ink === false) {
            return;
        }

        imagefilledrectangle($canvas, 0, $bandTop, self::WIDTH, $bandTop + $bandHeight, $band);

        // Shrink until it fits the frame with a margin. A long name rendered off
        // the edge is the same defect as a truncated one and is harder to see in
        // a thumbnail.
        while ($size > 24) {
            $box = @imagettfbbox($size, 0, $font, $name);

            if ($box !== false && ($box[2] - $box[0]) <= self::WIDTH - 160) {
                break;
            }

            $size -= 4;
        }

        $box = @imagettfbbox($size, 0, $font, $name);

        if ($box === false) {
            return;
        }

        $x = (int) ((self::WIDTH - ($box[2] - $box[0])) / 2);
        $y = $bandTop + (int) ($bandHeight / 2) + (int) (($box[1] - $box[7]) / 2);

        @imagettftext($canvas, $size, 0, $x, $y, $ink, $font, $name);
    }

    /**
     * Encode as JPEG, stepping the quality down until it is under the ceiling.
     *
     * ⚠️ **THE LAST STEP IS A REFUSAL, NOT A WORSE PICTURE.** If 40% quality is
     * still over 500 KB the input was extraordinary, and sending an over-size
     * MMS means the carrier rejects it — a failure that arrives as a delivery
     * receipt nobody is reading. A plain SMS is the better outcome.
     *
     * @param  \GdImage  $canvas
     */
    private function encodeUnder(int $ceiling, $canvas): ?string
    {
        foreach ([82, 70, 58, 46, 40] as $quality) {
            ob_start();
            imagejpeg($canvas, null, $quality);
            $bytes = (string) ob_get_clean();

            if (strlen($bytes) <= $ceiling) {
                return $bytes;
            }
        }

        Log::warning('A campaign image would not fit the MMS ceiling, so this send falls back to SMS.');

        return null;
    }

    /**
     * The font the overlay is drawn with.
     *
     * ✅ **THE APPLICATION SHIPS ONE NOW, AND THIS DOCBLOCK ARGUED THE OPPOSITE
     * UNTIL 2026-08-16** (4365–4368). It said a TTF *"would mean a new directory
     * nobody agreed to for a file whose terms nobody has read"* — and what the
     * argument actually bought was a seeded path (`/usr/share/fonts/…/DejaVu`)
     * that is absent on the production box, so **every** personalised overlay
     * took the degrade branch below and the feature was inert everywhere. The
     * terms are read: Public Sans Bold under the SIL Open Font License 1.1, with
     * the licence text committed beside the file at `resources/fonts/OFL.txt`,
     * and Public Sans is already this product's declared body face.
     *
     * ⚠️ **A RELATIVE SEED IS RESOLVED AGAINST `base_path()` AND AN ABSOLUTE ONE
     * IS NOT.** The shipped font lives at a path relative to the application
     * root, which differs per install; an operator pointing at a different face
     * writes an absolute path and it is used exactly as written. Two spellings,
     * one key, and the absolute branch is what keeps this a default rather than
     * a hardcoding.
     *
     * ⚠️ **THE DEGRADE BRANCH STAYS AND IS NOT NOW UNREACHABLE.** A deployment
     * that ships `app/` without `resources/`, an operator who points the key at
     * a path that has moved, a file mode nobody checked — each still ends in a
     * plain SMS rather than a failed send, which is this class's whole rule.
     */
    private function fontPath(): ?string
    {
        $path = (string) $this->registry->value('campaigns.overlay_font_path');

        if ($path === '') {
            return null;
        }

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        return is_readable($path) ? $path : null;
    }

    private function expiry(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours($this->registry->int('campaigns.media_url_ttl_hours'));
    }

    private function disk(): Filesystem
    {
        Tenancy::idOrFail();

        return Storage::disk('local');
    }
}
