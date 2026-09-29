<?php

namespace App\Services\Quote;

use App\Services\Zoho\ZohoCreatorClient;
use App\Services\Zoho\ZohoCrmClient;
use Illuminate\Support\Collection;

class ProductResolver
{
    private const STOPWORDS = ['a', 'an', 'the', 'for', 'of', 'and', 'with', 'nos', 'no', 'pcs', 'pc', 'qty', 'unit', 'units', 'piece', 'pieces'];

    public function __construct(
        private ZohoCrmClient $crm,
        private ZohoCreatorClient $creator,
    ) {}

    /** @return array{resolved: array, unresolved: array, warnings: string[]} */
    public function resolve(ExtractedQuote $intent): array
    {
        // Only active CRM products are quotable
        $products = collect($this->crm->getProducts())
            ->filter(fn ($p) => ($p['Product_Active'] ?? false) === true)
            ->values();

        // Creator mirror records, keyed by SKU, for the subform lookup
        $creatorBySku = collect($this->creator->getRecords('All_Product_Catalogs'))
            ->keyBy(fn ($r) => strtoupper($r['SKU'] ?? ''));

        $resolved = $unresolved = $warnings = [];

        foreach ($intent->items as $item) {
            $match = $this->match($item->productReference, $products);

            if ($match['type'] === 'ambiguous') {
                $unresolved[] = ['reference' => $item->productReference,
                    'reason' => 'Ambiguous; could be: ' . implode(', ', $match['candidates'])];
                continue;
            }
            if ($match['type'] === 'none') {
                $unresolved[] = ['reference' => $item->productReference, 'reason' => 'No matching active product'];
                continue;
            }

            $product = $match['product'];
            $sku = strtoupper($product['Product_Code'] ?? '');

            if (! $creatorBySku->has($sku)) {
                $unresolved[] = ['reference' => $item->productReference,
                    'reason' => "Matched {$product['Product_Name']} but it is missing from the Creator catalog (sync needed)"];
                continue;
            }
            if ($item->quantity === null) {
                $unresolved[] = ['reference' => $item->productReference,
                    'reason' => "Matched {$product['Product_Name']} but quantity was not stated"];
                continue;
            }
            if ($match['type'] === 'partial') {
                $warnings[] = "Matched \"{$item->productReference}\" to {$product['Product_Name']} by partial name; please verify.";
            }

            $resolved[] = [
                'reference'          => $item->productReference,
                'quantity'           => $item->quantity,
                'note'               => $item->note,
                'match_type'         => $match['type'],
                'crm_product'        => $product,
                'creator_product_id' => $creatorBySku[$sku]['ID'],
            ];
        }

        return compact('resolved', 'unresolved', 'warnings');
    }

    private function match(string $reference, Collection $products): array
    {
        // 1. SKU mentioned anywhere in the reference
        $upper = strtoupper($reference);
        $bySku = $products->filter(fn ($p) => ! empty($p['Product_Code'])
            && str_contains($upper, strtoupper($p['Product_Code'])));
        if ($bySku->count() === 1) {
            return ['type' => 'sku', 'product' => $bySku->first()];
        }

        // 2. Exact name after normalising
        $refTokens = $this->tokens($reference);
        if (! $refTokens) {
            return ['type' => 'none'];
        }
        $exact = $products->filter(fn ($p) => $this->tokens($p['Product_Name']) === $refTokens);
        if ($exact->count() === 1) {
            return ['type' => 'exact', 'product' => $exact->first()];
        }

        // 3. All typed words appear in exactly one product name
        $partial = $products->filter(fn ($p) => ! array_diff($refTokens, $this->tokens($p['Product_Name'])));
        if ($partial->count() === 1) {
            return ['type' => 'partial', 'product' => $partial->first()];
        }
        if ($partial->count() > 1) {
            return ['type' => 'ambiguous', 'candidates' => $partial->pluck('Product_Name')->all()];
        }

        return ['type' => 'none'];
    }

    // "Smart Locks, 4-Door!" → ["4", "door", "lock", "smart"]
    private function tokens(string $text): array
    {
        $words = preg_split('/\s+/', trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($text))));

        $words = array_map(fn ($w) => (strlen($w) > 3 && str_ends_with($w, 's') && ! str_ends_with($w, 'ss'))
            ? substr($w, 0, -1) : $w, $words);

        $words = array_values(array_unique(array_filter($words, fn ($w) => $w !== '' && ! in_array($w, self::STOPWORDS))));
        sort($words);

        return $words;
    }
}
