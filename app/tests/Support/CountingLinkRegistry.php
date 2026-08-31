<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Links\LinkRegistry;
use App\Models\Conversation;
use App\Services\Agent\AgentComposer;
use App\Services\Links\TenantLink;
use App\Services\Links\TenantLinks;

/**
 * The real registry with a counter around {@see shortLinkFor()} — T176 P6 (4271).
 *
 * ⛔ **IT DELEGATES RATHER THAN SIMULATES, AND THAT IS THE POINT.** The claim
 * under test is R14's — *"every agent-sent link rides the short-link service"* —
 * and a fake that returned a plausible string would prove only that
 * {@see AgentComposer} calls **something**. Every answer here
 * is {@see TenantLinks}', including the tenant scoping and the cross-tenant
 * refusal; what is added is the tally that makes *"minted through the contract
 * rather than assembled"* falsifiable — a composer that built
 * `'https://…/'.$token` by hand would leave this at zero while the prompt still
 * carried a link.
 *
 * ⚠️ **NOT A SECOND `LinkRegistry` IMPLEMENTATION IN `app/`.** `LinksTest`'s
 * *"the links contract has exactly one implementation"* lint scans `app/` alone,
 * deliberately: a refusing or stand-in implementation shipped beside the real one
 * is how a suite goes green against the wrong one, and this class cannot be
 * resolved outside a test that binds it by hand.
 */
final class CountingLinkRegistry implements LinkRegistry
{
    /** @var list<string> Every short link minted through this registry, in order. */
    public array $minted = [];

    public function __construct(private readonly TenantLinks $inner) {}

    /**
     * Bind this in front of the real registry for the rest of the test.
     */
    public static function bind(): self
    {
        $registry = new self(app(TenantLinks::class));

        app()->instance(LinkRegistry::class, $registry);

        return $registry;
    }

    public function booking(): ?TenantLink
    {
        return $this->inner->booking();
    }

    public function payment(): ?TenantLink
    {
        return $this->inner->payment();
    }

    /**
     * @return array<string, TenantLink>
     */
    public function documents(): array
    {
        return $this->inner->documents();
    }

    public function document(string $slug): ?TenantLink
    {
        return $this->inner->document($slug);
    }

    public function shortLinkFor(TenantLink $link, Conversation $conversation): string
    {
        $url = $this->inner->shortLinkFor($link, $conversation);

        $this->minted[] = $url;

        return $url;
    }

    /**
     * @return array<string, bool>
     */
    public function grounded(): array
    {
        return $this->inner->grounded();
    }
}
