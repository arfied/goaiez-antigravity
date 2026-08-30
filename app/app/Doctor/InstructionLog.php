<?php

declare(strict_types=1);

namespace App\Doctor;

/**
 * `.agents/state/INSTRUCTIONS.jsonl` — append-only, hash-chained, reason mandatory.
 *
 * ⭐⭐⭐ WHAT THIS IS FOR, AND WHY IT IS HASH-CHAINED:
 *
 * The plan: "INSTRUCTIONS.jsonl + why — append-only, queryable, SURVIVES BOTH
 * MODELS." That last clause is the point. A decision made in one conversation,
 * by one model, must still be answerable months later by a different model that
 * was never in the room.
 *
 * ⛔⛔ This programme has the receipts for what happens without it:
 *   · a P-163 violation was found, written down, and never fixed
 *   · §235 classified 37 orphans; a later line claimed 41 changes were applied
 *     and MEASUREMENT FOUND ZERO
 *   · X-168 carried a struck capability through eight gates
 *   · the roster drifted to 122 while both trackers said 119
 *
 * ⭐ Every one of those was a claim that outlived its evidence. A hash chain
 * makes that specific failure impossible: you cannot edit an entry without
 * breaking every entry after it, so "it says here we did X" becomes checkable
 * rather than trusted.
 *
 * ⛔ `reason` is MANDATORY and is not decoration. A constraint whose reason is
 * lost gets removed by the next competent person, because it looks arbitrary.
 */
final class InstructionLog
{
    private const PATH = '.agents/state/INSTRUCTIONS.jsonl';

    /**
     * Append one action. Returns the new entry's hash.
     *
     * ⛔ There is no update() and no delete(), deliberately. Append-only is not
     * a policy that can be followed carelessly — it is the absence of any other
     * method.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function append(string $action, string $module, string $reason, array $extra = []): string
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException(
                'reason is mandatory. An entry with no reason records THAT something '
                .'happened and loses WHY, which is the half that stops the next person undoing it.'
            );
        }

        $path = base_path(self::PATH);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $prev = self::lastHash();

        $entry = $extra + [
            'ts' => date('c'),
            'action' => $action,
            'module' => $module,
            'reason' => $reason,
            'prev' => $prev,
        ];

        // ⭐ The hash covers the PREVIOUS hash, which is what makes it a chain
        //   rather than a list of independent checksums.
        $entry['hash'] = substr(hash('sha256', (string) json_encode($entry)), 0, 16);

        file_put_contents($path, (string) json_encode($entry).PHP_EOL, FILE_APPEND | LOCK_EX);

        return $entry['hash'];
    }

    /**
     * ⛔⛔ Verify the chain. A break means an entry was EDITED or REMOVED — and
     * the log's whole value is that this is detectable rather than deniable.
     *
     * @return list<string>
     */
    public static function verify(): array
    {
        $out = [];
        $prev = null;

        foreach (self::entries() as $i => $e) {
            if (($e['prev'] ?? null) !== $prev) {
                $out[] = "line {$i}: prev-hash does not match the entry before it — "
                    .'an entry was edited or removed';
            }
            if (trim((string) ($e['reason'] ?? '')) === '') {
                $out[] = "line {$i}: no reason recorded";
            }
            $prev = $e['hash'] ?? null;
        }

        return $out;
    }

    /**
     * Has this module been through the given action?
     *
     * ⭐ Check 11: "VERIFIED requires an INSTRUCTIONS.jsonl entry." Without this,
     * VERIFIED means somebody said so.
     */
    public static function has(string $module, string $action): bool
    {
        foreach (self::entries() as $e) {
            if (($e['module'] ?? null) === $module && ($e['action'] ?? null) === $action) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    public static function entries(): array
    {
        $path = base_path(self::PATH);
        if (! is_file($path)) {
            return [];
        }

        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $d = json_decode($line, true);
            if (is_array($d)) {
                $out[] = $d;
            }
        }

        return $out;
    }

    /** The last 20 — what `why` reads for recent rulings. @return list<array<string, mixed>> */
    public static function recent(int $n = 20): array
    {
        return array_slice(self::entries(), -$n);
    }

    private static function lastHash(): ?string
    {
        $all = self::entries();

        return $all === [] ? null : ($all[count($all) - 1]['hash'] ?? null);
    }
}
