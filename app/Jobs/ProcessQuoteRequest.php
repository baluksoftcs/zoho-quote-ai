<?php

namespace App\Jobs;

use App\Models\AiAuditLog;
use App\Models\AiQuoteRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Quote\Money;
use App\Services\Quote\ProductResolver;
use App\Services\Quote\QuoteIntentExtractor;
use App\Services\Quote\QuotePricer;
use App\Services\Quote\QuoteProcessingException;
use App\Services\Zoho\ZohoCreatorClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

class ProcessQuoteRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60];   // seconds between retries (transient errors only)

    public function __construct(public int $aiQuoteRequestId) {}

    public function handle(
        QuoteIntentExtractor $extractor,
        ProductResolver $resolver,
        QuotePricer $pricer,
        ZohoCreatorClient $creator,
    ): void {
        $req = AiQuoteRequest::findOrFail($this->aiQuoteRequestId);

        // A previous attempt already created the quote; just finish up (no duplicates)
        if ($req->zoho_quote_id) {
            $this->updateCreatorRequest($creator, $req, 'Done');
            return;
        }

        $req->update(['status' => 'processing']);

        $audit = AiAuditLog::firstOrCreate(
            ['ai_quote_request_id' => $req->id],
            ['zoho_request_id' => $req->zoho_request_id, 'notes' => $req->notes,
             'model' => config('services.anthropic.model')]
        );

        try {
            // 1 + 2. Extract and validate
            $extraction = $extractor->extract($req->notes);
            $audit->update([
                'raw_ai_output'    => $extraction['raw'],
                'validated_output' => $extraction['intent']->toArray(),
            ]);

            // 3. Resolve products
            $resolution = $resolver->resolve($extraction['intent']);
            $audit->update(['resolution' => $resolution]);

            if (! $resolution['resolved']) {
                throw new QuoteProcessingException('No products could be matched. '
                    . collect($resolution['unresolved'])->map(fn ($u) => "{$u['reference']}: {$u['reason']}")->implode('; '));
            }

            // 4. Price from CRM
            $pricing = $pricer->price($resolution['resolved'], $req->crm_account_id);
            $warnings = $this->buildWarnings($extraction, $resolution, $pricing);
            $audit->update(['pricing' => $pricing, 'warnings' => $warnings]);

            // 5. Create the draft in Creator (always AI Draft)
            $result = $creator->addRecord('Quote', [
                'Customer'      => $req->zoho_customer_id,
                'Quote_Request' => $req->zoho_request_id,
                'Status'        => 'AI Draft',
                'Line_Items'    => array_map(fn ($l) => [
                    'Product'          => $l['creator_product_id'],
                    'Qty'              => $l['quantity'],
                    'Unit_Price'       => Money::format($l['unit_price_paise']),
                    'Discount_Percent' => 0,
                    'Line_Total'       => Money::format($l['line_total_paise']),
                    'Price_Source'     => $l['price_source'],
                ], $pricing['lines']),
                'Subtotal'    => Money::format($pricing['subtotal_paise']),
                'Tax'         => Money::format($pricing['tax_paise']),
                'Total'       => Money::format($pricing['total_paise']),
                'AI_Warnings' => $warnings ? implode("\n", $warnings) : 'None',
            ]);

            $quoteId = data_get($result, 'data.ID')
                ?? throw new QuoteProcessingException('Creator did not return a Quote ID: ' . json_encode($result));

            $req->update(['zoho_quote_id' => $quoteId, 'status' => 'done']);
            $audit->update(['zoho_quote_id' => $quoteId, 'status' => 'done']);

            $this->updateCreatorRequest($creator, $req, 'Done');

        } catch (QuoteProcessingException $e) {
            // Business failure: retrying won't help
            $this->markFailed($creator, $req, $audit, $e->getMessage());
        } catch (RequestException $e) {
            // 4xx from Zoho or Anthropic = bad request, don't retry; 5xx = rethrow and retry
            if ($e->response->clientError()) {
                $this->markFailed($creator, $req, $audit, 'API rejected request: ' . $e->response->body());
                return;
            }
            throw $e;
        }
    }

    // Runs after all retries are used up
    public function failed(Throwable $e): void
    {
        $req = AiQuoteRequest::find($this->aiQuoteRequestId);
        if (! $req) {
            return;
        }
        $audit = AiAuditLog::where('ai_quote_request_id', $req->id)->first();
        $this->markFailed(app(ZohoCreatorClient::class), $req, $audit, 'System error: ' . $e->getMessage());
    }

    private function buildWarnings(array $extraction, array $resolution, array $pricing): array
    {
        $intent = $extraction['intent'];
        $w = $extraction['warnings'];

        if ($intent->requestedDiscountNote) {
            $w[] = "Salesperson mentioned pricing/discount: \"{$intent->requestedDiscountNote}\". NOT applied; review and apply manually if approved.";
        }
        if ($intent->deliveryNotes) {
            $w[] = "Delivery notes: {$intent->deliveryNotes}";
        }
        foreach ($intent->unclearPoints as $point) {
            $w[] = "Unclear: {$point}";
        }
        foreach ($resolution['unresolved'] as $u) {
            $w[] = "Not added: \"{$u['reference']}\". {$u['reason']}";
        }

        return array_merge($w, $resolution['warnings'], $pricing['warnings']);
    }

    private function markFailed(ZohoCreatorClient $creator, AiQuoteRequest $req, ?AiAuditLog $audit, string $reason): void
    {
        $req->update(['status' => 'failed']);
        $audit?->update(['status' => 'failed', 'error' => $reason]);
        $this->updateCreatorRequest($creator, $req, 'Failed', $reason);
    }

    private function updateCreatorRequest(ZohoCreatorClient $creator, AiQuoteRequest $req, string $status, ?string $reason = null): void
    {
        try {
            $data = ['Processing_Status' => $status];
            if ($reason) {
                $data['Failure_Reason'] = Str::limit($reason, 1900);
            }
            $creator->updateRecord('Quote_Request_Report', $req->zoho_request_id, $data);
        } catch (Throwable $e) {
            // Never let a status update hide the real outcome; the audit log still has it
            Log::error('Could not update Quote_Request status in Creator', [
                'zoho_request_id' => $req->zoho_request_id, 'error' => $e->getMessage(),
            ]);
        }
    }
}