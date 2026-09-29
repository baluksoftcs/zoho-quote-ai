<?php

return [
    'accounts_url'  => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.in'),
    'api_domain'    => env('ZOHO_API_DOMAIN', 'https://www.zohoapis.in'),
    'client_id'     => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
    'creator_owner' => env('ZOHO_CREATOR_OWNER'),
    'creator_app'   => env('ZOHO_CREATOR_APP'),
    'webhook_secret'=> env('ZOHO_WEBHOOK_SECRET'),
];
