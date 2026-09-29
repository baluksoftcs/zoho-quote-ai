<?php

namespace App\Services\Quote;

final class ExtractedQuote
{
    /** @param ExtractedItem[] $items  @param string[] $unclearPoints */
    public function __construct(
        public readonly array $items,
        public readonly ?string $requestedDiscountNote,
        public readonly ?string $deliveryNotes,
        public readonly array $unclearPoints,
    ) {}

    // Built ONLY from validated data, so unknown keys (e.g. "price") are dropped
    public static function fromValidated(array $data): self
    {
        $items = array_map(fn (array $i) => new ExtractedItem(
            productReference: trim($i['product_reference']),
            quantity: isset($i['quantity']) ? (int) $i['quantity'] : null,
            note: $i['note'] ?? null,
        ), $data['items'] ?? []);

        return new self(
            items: $items,
            requestedDiscountNote: $data['requested_discount_note'] ?? null,
            deliveryNotes: $data['delivery_notes'] ?? null,
            unclearPoints: array_values($data['unclear_points'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'items' => array_map(fn (ExtractedItem $i) => [
                'product_reference' => $i->productReference,
                'quantity'          => $i->quantity,
                'note'              => $i->note,
            ], $this->items),
            'requested_discount_note' => $this->requestedDiscountNote,
            'delivery_notes'          => $this->deliveryNotes,
            'unclear_points'          => $this->unclearPoints,
        ];
    }
}
