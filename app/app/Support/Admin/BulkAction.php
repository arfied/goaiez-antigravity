<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * An action applied to selected rows.
 *
 * Two properties are not conveniences:
 *
 * `destructive` drives a typed-reason confirmation. `29` §19.5 requires
 * destructive CS actions to carry one, and a bulk action is the shape where a
 * mis-click costs the most.
 *
 * `audited` is on by default and cannot be turned off from the call site. Every
 * sensitive action reaches the append-only audit log (`29` §2 rule 42), and a
 * bulk operation is exactly the kind of thing someone later wants to know the
 * actor for.
 *
 * @template TModel of Model
 */
final class BulkAction
{
    private bool $destructive = false;

    /**
     * The handler carries the class template, which is what lets make() infer
     * the concrete model from its argument rather than widening to Model.
     *
     * @param  Closure(Collection<int, TModel>, ?string): void  $handler
     */
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        private readonly Closure $handler,
    ) {}

    /**
     * Method-level template rather than the class one: a static factory has no
     * instance to take TModel from, so it is inferred from the handler's
     * signature at the call site.
     *
     * @template TMake of Model
     *
     * @param  Closure(Collection<int, TMake>, ?string): void  $handler
     * @return self<TMake>
     */
    public static function make(string $key, string $label, Closure $handler): self
    {
        return new self($key, $label, $handler);
    }

    /**
     * Requires a typed reason before it will run.
     *
     * @return self<TModel>
     */
    public function destructive(bool $destructive = true): self
    {
        $this->destructive = $destructive;

        return $this;
    }

    public function isDestructive(): bool
    {
        return $this->destructive;
    }

    /**
     * @param  Collection<int, TModel>  $records
     */
    public function run(Collection $records, ?string $reason = null): void
    {
        ($this->handler)($records, $reason);
    }
}
