<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A physical place of business. The unit most automation is scoped to —
 * `29` §2 rule 40 requires jobs to be location-scoped, not merely
 * business-scoped.
 *
 * ⛔ **`is_autopilot_active` HAS NO WRITER AND, SINCE 8580, NO READER EITHER.**
 * Every occurrence of it in this repository is its own `$table->boolean(...)`
 * line, an index over it, the cast below, `LocationFactory`'s `=> true`, and —
 * until 8580 — one render on the CS console. **Nothing in `app/` has ever
 * written it**, so the only value it has held on any deployment is its schema
 * default of `true`, and the console said *"Autopilot on"* for every location on
 * the platform whatever was actually happening to it.
 *
 * ⚠️ **THE COLUMN IS DELIBERATELY LEFT IN PLACE** (8581). It is reportable as
 * dead, not droppable by a lane: 8390's drop of the last such pair took an
 * explicit owner ruling. A grep for the name must land here rather than on the
 * column, which is 8397's rule.
 *
 * ⚠️ **THE INDEX IS THE PART TO READ TWICE.** `locations` carries
 * `['business_id', 'is_autopilot_active']` — a query somebody intended and
 * nobody wrote, *"every live location for this tenant"*. **No `where` on this
 * column exists anywhere in the repository**, so the index has never served a
 * predicate. It is not removed here for the same reason the column is not.
 *
 * ⚠️ **`current_rating` AND `review_count` ARE THE OPPOSITE CASE AND SIT IN THE
 * SAME CAST BLOCK.** Both read as equally inert and both are written by
 * `Visibility\CompetitorSignals::recordOurOwnRating()` on a daily sweep.
 * `CLAUDE.md` keeps that pair as the reason a **call-site** check beats a
 * docblock; this docblock is a pointer to that check and never a substitute for
 * it.
 */
final class Location extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /**
     * `business_id` stays guarded: it is the tenant key, and mass-assigning it
     * from request input is how a row crosses the boundary. BelongsToTenant
     * fills it from context on create.
     *
     * ⚠️ **`timezone` IS GUARDED TOO, AND IT IS THE MORE DANGEROUS OF THE PAIR
     * IT SHARES A SLICE WITH** (1610). It decides the hours a tenant may
     * lawfully text their customers, `App\Services\Tenant\LocationTimezone` is
     * its only writer, and a chokepoint lint holds it there — but a lint is a
     * textual claim and cannot see `Location::query()->update(['timezone' =>
     * …])` or a `fill()` from request input. Guarding closes the shapes the lint
     * cannot reach; the lint closes the ones guarding cannot (a direct property
     * assignment). Neither replaces the other.
     *
     * ⚠️ **FACTORIES ARE UNAFFECTED** — `Factory::makeInstance()` constructs
     * inside `Model::unguarded()`.
     *
     * ⚠️ **`website_url` AND ITS FOUR COMPANIONS ARE GUARDED ON THE SAME
     * ARGUMENT, AND THE STAKE IS A STRANGER'S WEBSITE** (5540). The address is
     * what a later slice publishes pages to and injects markup into, and decision
     * 1083's franchisor case is what happens when it is wrong: we actuate against
     * somebody who is not our customer. `App\Services\Tenant\LocationWebsite` is
     * the only writer of the address and the confirmation,
     * `App\Services\Actuation\SiteProbe` the only writer of the three detection
     * facts, and a chokepoint lint holds both — but a lint is a textual claim and
     * cannot see a `fill()` from request input. Guarding closes what the lint
     * cannot reach.
     *
     * ⚠️ **THE ABOUT-PAGE PAIR IS GUARDED ON A NARROWER ARGUMENT THAN THE
     * ADDRESS AND A SHARPER ONE THAN THE PAUSE COLUMNS** (5741). It is `29` §2
     * rule 36's gate — *auto-published content carries a real author byline
     * linked to a genuine About page* — so a request body that could write it is
     * a request body that decides where this platform's byline points on
     * somebody else's website. `App\Services\Tenant\LocationWebsite` is its only
     * writer too, and the same chokepoint lint holds it.
     *
     * ⚠️ **AND THE TWO CONTENT-PAUSE COLUMNS ARE GUARDED FOR THE SAME REASON
     * ONE RUNG DOWN** (5666). They are what the daily self-audit uses to stop
     * this platform publishing to a site Google is already unhappy with, so a
     * request body that could clear them is a request body that restarts
     * publishing into a manual action. `App\Services\Content\ContentSelfAudit`
     * is the only writer.
     *
     * ⚠️ **AND THE CONTACT PAIR IS GUARDED ON THE NARROWEST ARGUMENT OF THE
     * FOUR, WHICH IS ALSO THE ONE WITH A STRANGER AT THE END OF IT** (6100).
     * `primary_phone` is interpolated verbatim into the carrier-mandated HELP
     * reply — a text message to a member of the public who asked who was texting
     * them — and `address` is put in front of a language model as the answer to
     * *"where are you"*. A request body that could write either is a request body
     * that decides what this platform tells somebody else's customer.
     * `App\Services\Tenant\LocationDetails` is the only writer of both, a
     * chokepoint lint holds the two confirmation columns there, and two
     * biconditional CHECKs make a value without a confirmation unrepresentable.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'timezone',
        'primary_phone',
        'primary_phone_confirmed_at',
        'address',
        'address_confirmed_at',
        'website_url',
        'website_confirmed_at',
        'website_scanned_at',
        'wordpress_detected_at',
        'cloudflare_detected_at',
        'about_url',
        'about_url_confirmed_at',
        'content_generation_paused_at',
        'content_generation_pause_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_rating' => 'decimal:1',
            'review_count' => 'integer',
            'boost_score' => 'integer',
            'is_autopilot_active' => 'boolean',
            'primary_phone_confirmed_at' => 'datetime',
            'address_confirmed_at' => 'datetime',
            'website_confirmed_at' => 'datetime',
            'website_scanned_at' => 'datetime',
            'wordpress_detected_at' => 'datetime',
            'cloudflare_detected_at' => 'datetime',
            'about_url_confirmed_at' => 'datetime',
            'content_generation_paused_at' => 'datetime',
            'opening_hours' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The name the disclosure has to carry.
     *
     * `24` §3.2 is explicit that a consent disclosure names the business and
     * not GO AI EZ, because the business is the sender of record. Falls back
     * to the location's own name, since provisioning names the first location
     * after the business and a multi-location tenant may not have renamed it.
     *
     * Reads the `business` relation rather than querying it out — every
     * caller that needs this also has the tenant boundary already loaded,
     * and reading the relation lets it cache instead of running a second
     * query in the same request.
     */
    public function businessName(): string
    {
        // Not $this->business?->name: PHP already evaluates a property access
        // on a null object as null, so the nullsafe operator here would only
        // restate what ?? already guarantees.
        $name = $this->business->name ?? $this->name;

        return trim($name) === '' ? $this->name : $name;
    }
}
