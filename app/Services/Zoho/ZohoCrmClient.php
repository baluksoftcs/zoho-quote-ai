<?php

namespace App\Services\Zoho;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ZohoCrmClient
{
    public function __construct(private ZohoAuthService $auth) {}

    public function getProducts(): array
    {
        return $this->get('/crm/v8/Products', [
            'fields' => 'Product_Name,Product_Code,Unit_Price,Product_Active',
            'per_page' => 200,
        ])->json('data', []);
    }

    public function findProductBySku(string $sku): ?array
    {
        $response = $this->get('/crm/v8/Products/search', [
            'criteria' => "(Product_Code:equals:{$sku})",
        ]);

        // Zoho returns 204 No Content when nothing matches
        return $response->status() === 204 ? null : ($response->json('data.0') ?? null);
    }

    public function getAccount(string $accountId): ?array
    {
        $field = config('zoho.crm_price_book_field');

        return $this->get("/crm/v8/Accounts/{$accountId}", [
            'fields' => "Account_Name,{$field}",
        ])->json('data.0');
    }

    public function getPriceBookProducts(string $priceBookId): array
    {
        return $this->get("/crm/v8/Price_Books/{$priceBookId}/Products", [
            'fields' => 'Product_Name,Product_Code,list_price',
        ])->json('data') ?? [];
    }

    private function get(string $path, array $query = []): Response
    {
        $response = $this->send($path, $query);

        // Token expired or revoked early: clear it and retry once
        if ($response->status() === 401) {
            $this->auth->forgetToken();
            $response = $this->send($path, $query);
        }

        $response->throw();   // throw on any other 4xx/5xx
        return $response;
    }

    private function send(string $path, array $query): Response
    {
        return Http::withToken($this->auth->getAccessToken(), 'Zoho-oauthtoken')
            ->baseUrl(config('zoho.api_domain'))
            ->timeout(15)
            ->get($path, $query);
    }
}
