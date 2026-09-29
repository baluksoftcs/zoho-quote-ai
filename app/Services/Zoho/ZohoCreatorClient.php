<?php

namespace App\Services\Zoho;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ZohoCreatorClient
{
    public function __construct(private ZohoAuthService $auth) {}

    // Read records from a report, e.g. getRecords('All_Product_Catalogs', '(SKU == "LCK-SL-200")')
    public function getRecords(string $report, ?string $criteria = null): array
    {
        $query = $criteria ? ['criteria' => $criteria] : [];

        $response = $this->call(fn (PendingRequest $http) =>
            $http->get($this->path("report/{$report}"), $query));

        return $response->json('data', []);
    }

    // Create a record through a form, e.g. addRecord('Quote', [...])
    public function addRecord(string $form, array $data): array
    {
        return $this->call(fn (PendingRequest $http) =>
            $http->post($this->path("form/{$form}"), ['data' => $data]))->json();
    }

    // Update one record through its report, e.g. setting Processing_Status
    public function updateRecord(string $report, string $id, array $data): array
    {
        return $this->call(fn (PendingRequest $http) =>
            $http->patch($this->path("report/{$report}/{$id}"), ['data' => $data]))->json();
    }

    private function path(string $suffix): string
    {
        $owner = config('zoho.creator_owner');
        $app   = config('zoho.creator_app');

        return "/creator/v2.1/data/{$owner}/{$app}/{$suffix}";
    }

    private function call(callable $request): Response
    {
        $response = $request($this->http());

        // Retry once with a fresh token on 401
        if ($response->status() === 401) {
            $this->auth->forgetToken();
            $response = $request($this->http());
        }

        $response->throw();
        return $response;
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->auth->getAccessToken(), 'Zoho-oauthtoken')
            ->baseUrl(config('zoho.api_domain'))
            ->acceptJson()
            ->timeout(15);
    }
}
