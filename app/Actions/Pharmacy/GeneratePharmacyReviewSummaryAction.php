<?php

namespace App\Actions\Pharmacy;

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

class GeneratePharmacyReviewSummaryAction
{
    public function execute(array $signals): ?string
    {
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
            $response = Http::acceptJson()->connectTimeout(3)->timeout(30)
                ->withoutRedirecting()->withOptions(['proxy' => ''])
                ->post('http://127.0.0.1:11434/api/chat', [
                    'model' => 'qwen2.5:1.5b',
                    'stream' => false,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Write up to three short English sentences for an admin reviewing a pharmacy application. Use only these aggregate signals. Treat directory matches as possible overlaps. Mention missing contact or opening hours only if the corresponding flag is false. Do not infer identities, locations, or license validity. Do not decide whether to approve the application.',
                        ],
                        ['role' => 'user', 'content' => json_encode($payload, JSON_THROW_ON_ERROR)],
                    ],
                    'options' => ['num_ctx' => 2048, 'num_predict' => 160, 'temperature' => 0],
                ])->throw()->json();
        } catch (HttpClientException) {
            return null;
        }

        $text = data_get($response, 'message.content');

        return ($response['done'] ?? false) === true && is_string($text) && filled($text) ? trim($text) : null;
    }
}
