<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessQuoteRequest;
use App\Models\AiQuoteRequest;
use Illuminate\Http\Request;

class ZohoWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'request_id'     => ['required', 'string', 'max:50'],
            'customer_id'    => ['nullable', 'string', 'max:50'],
            'crm_account_id' => ['nullable', 'string', 'max:50'],
            'notes'          => ['required', 'string', 'max:5000'],
        ]);

        // Idempotency: the same Creator record is only ever processed once
        $record = AiQuoteRequest::firstOrCreate(
            ['zoho_request_id' => $data['request_id']],
            [
                'zoho_customer_id' => $data['customer_id'] ?? null,
                'crm_account_id'   => $data['crm_account_id'] ?? null,
                'notes'            => $data['notes'],
            ]
        );

        if (! $record->wasRecentlyCreated) {
            return response()->json(['status' => 'duplicate_ignored'], 200);
        }

        ProcessQuoteRequest::dispatch($record->id);

        // Reply fast; the heavy work happens in the queue
        return response()->json(['status' => 'accepted'], 202);
    }
}
