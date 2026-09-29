<?php

namespace App\Jobs;

use App\Models\AiQuoteRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessQuoteRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $aiQuoteRequestId) {}

    public function handle(): void
    {
        $req = AiQuoteRequest::findOrFail($this->aiQuoteRequestId);
        $req->update(['status' => 'processing']);

        // Placeholder: the AI extraction and pricing pipeline goes here later
        Log::info('Processing quote request', [
            'zoho_request_id' => $req->zoho_request_id,
            'notes'           => $req->notes,
        ]);
    }
}