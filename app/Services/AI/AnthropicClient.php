<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class AnthropicClient
{
    public function messages(string $system, array $messages, array $tools = [], ?array $toolChoice = null): array
    {
        $payload = [
            'model'      => config('services.anthropic.model'),
            'max_tokens' => 1024,
            'system'     => $system,
            'messages'   => $messages,
        ];

        if ($tools) {
            $payload['tools'] = $tools;
        }
        if ($toolChoice) {
            $payload['tool_choice'] = $toolChoice;
        }

        return Http::withHeaders([
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(60)
            ->retry(2, 1000)   // retry twice on connection errors or 5xx
            ->post('https://api.anthropic.com/v1/messages', $payload)
            ->throw()
            ->json();
    }
}
