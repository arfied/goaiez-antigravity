<?php

declare(strict_types=1);

namespace App\Modules\X215\Domain;

final class X215Engine
{
    public function validateSignature(string $originalHash, string $currentHash): void
    {
        if ($originalHash !== $currentHash) {
            throw new \DomainException('REFUSES: an altered document');
        }
    }

    public function validateCommentEdit(): void
    {
        throw new \DomainException('REFUSES: a comment NEVER edits a signed document');
    }
}
