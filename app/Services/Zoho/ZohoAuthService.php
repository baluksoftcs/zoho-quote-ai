<?php

namespace App\Services\Zoho;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZohoAuthService
{
    private const CACHE_KEY = 'zoho.access_token';

    public function getAccessToken(): string
    {
        // Return the cached token if we have one; otherwise refresh and cache for 55 minutes
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(55), function () {
            return $this->refreshAccessToken();
        });
    }

    public function forgetToken(): void
    {
        // Used when Zoho rejects a token early (e.g. revoked)
        Cache::forget(self::CACHE_KEY);
    }

    private function refreshAccessToken(): string
    {
        // A lock stops two queue workers refreshing at the same moment
        return Cache::lock('zoho.token_refresh', 10)->block(5, function () {
            $response = Http::asForm()->post(config('zoho.accounts_url') . '/oauth/v2/token', [
                'grant_type'    => 'refresh_token',
                'client_id'     => config('zoho.client_id'),
                'client_secret' => config('zoho.client_secret'),
                'refresh_token' => config('zoho.refresh_token'),
            ]);

            $token = $response->json('access_token');

            // Zoho sometimes returns HTTP 200 with an "error" key, so check for the token itself
            if (! $response->successful() || ! $token) {
                throw new RuntimeException('Zoho token refresh failed: ' . $response->body());
            }

            return $token;
        });
    }
}
