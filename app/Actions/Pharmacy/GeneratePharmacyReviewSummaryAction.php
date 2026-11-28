<?php

namespace App\Actions\Pharmacy;

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

class GeneratePharmacyReviewSummaryAction
{
    public function execute(array $signals): ?string
    {
        $key = config('services.openai.key');
        if (blank($key)) {
            return null;
        }

        $payload = [
            'matches' => [
                'total' => (int) $signals['match_count'],
                'name_only' => (int) $signals['name_only_count'],
                'address_only' => (int) $signals['address_only_count'],
                'name_and_address' => (int) $signals['name_and_address_count'],
            ],
            'completeness' => [
                'contact_provided' => (bool) $signals['has_contact'],
                'opening_hours_provided' => (bool) $signals['has_opening_hours'],
            ],
        ];

        try {
            $response = Http::withToken($key)->acceptJson()->connectTimeout(3)->timeout(10)
                ->withoutRedirecting()
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.pharmacy_review_model'),
                    'store' => false,
                    'instructions' => 'Summarize these aggregate pharmacy-registration review signals in at most three short English sentences. Matching records indicate possible overlap. Do not infer identities, locations, or license validity, and do not make an approval or rejection decision. Mention missing information only if its indicator is false. Use only the supplied signals.',
                    'input' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'max_output_tokens' => 200,
                ])->throw()->json();
        } catch (HttpClientException) {
            return null;
        }

        if (! is_array($response) || ($response['status'] ?? null) !== 'completed') {
            return null;
        }

        foreach ($response['output'] ?? [] as $message) {
            if (! is_array($message) || ($message['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ($message['content'] ?? [] as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text'
                    && is_string($content['text'] ?? null) && filled($content['text'])) {
                    return trim($content['text']);
                }
            }
        }

        return null;
    }
}
