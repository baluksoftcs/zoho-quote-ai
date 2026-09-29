<?php

namespace App\Services\Quote;

use App\Services\Zoho\ZohoCrmClient;

class QuotePricer
{
    public function __construct(private ZohoCrmClient $crm) {}

    public function price(array $resolvedItems, ?string $crmAccountId): array
    {
        $priceBookName = null;
        $bookPrices = [];   // CRM product ID => list price
        $warnings = [];

        if ($crmAccountId) {
            $account = $this->crm->getAccount($crmAccountId)
                ?? throw new QuoteProcessingException("CRM account {$crmAccountId} not found.");

            $book = $account[config('zoho.crm_price_book_field')] ?? null;

            if (! empty($book['id'])) {
                $priceBookName = $book['name'] ?? 'Price Book';
                foreach ($this->crm->getPriceBookProducts($book['id']) as $row) {
                    $bookPrices[$row['id']] = $row['list_price'] ?? null;
                }
            }
        } else {
            $warnings[] = 'No CRM account linked to this customer; standard list prices used.';
        }

        $lines = [];
        $subtotal = 0;

        foreach ($resolvedItems as $item) {
            $product = $item['crm_product'];

            if (isset($bookPrices[$product['id']])) {
                $unit = Money::toPaise($bookPrices[$product['id']]);
                $source = "Price Book: {$priceBookName}";
            } elseif (isset($product['Unit_Price'])) {
                $unit = Money::toPaise($product['Unit_Price']);
                $source = 'Standard list price';
            } else {
                throw new QuoteProcessingException("{$product['Product_Name']} has no price in CRM.");
            }

            $lineTotal = $unit * $item['quantity'];
            $subtotal += $lineTotal;

            $lines[] = [
                'creator_product_id' => $item['creator_product_id'],
                'crm_product_id'     => $product['id'],
                'product_name'       => $product['Product_Name'],
                'quantity'           => $item['quantity'],
                'unit_price_paise'   => $unit,
                'line_total_paise'   => $lineTotal,
                'price_source'       => $source,
            ];
        }

        $tax = Money::percentOf($subtotal, config('zoho.gst_basis_points'));

        return [
            'lines'          => $lines,
            'subtotal_paise' => $subtotal,
            'tax_paise'      => $tax,
            'total_paise'    => $subtotal + $tax,
            'gst_bp'         => config('zoho.gst_basis_points'),
            'price_book'     => $priceBookName,
            'warnings'       => $warnings,
        ];
    }
}
