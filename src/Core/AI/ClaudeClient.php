<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Ai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClaudeClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $model = 'claude-3-5-sonnet-latest'
    ) {}

    public function call(string $systemPrompt, string $userPrompt): string
    {
        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'max_tokens' => 4096,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt]
                ],
            ],
        ]);

        $data = $response->toArray();
        return $data['content'][0]['text'] ?? 'Erreur de réponse de l\'IA';
    }
}