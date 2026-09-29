<?php

namespace App\Services\Quote;

final class ExtractedItem
{
    public function __construct(
        public readonly string $productReference,
        public readonly ?int $quantity,   // null = salesperson didn't state it
        public readonly ?string $note,
    ) {}
}
