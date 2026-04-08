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
        // On utilise l'ID de modèle exact pour éviter les erreurs d'alias
        private string $model = 'claude-sonnet-4-6'
    ) {}

    public function call(string $systemPrompt, string $userPrompt): string
    {
        // On nettoie la clé pour éviter les caractères invisibles de Cygwin (\r)
        $cleanApiKey = trim($this->apiKey);

        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $cleanApiKey,
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

        
        if ($response->getStatusCode() !== 200) {
            $errorData = $response->getContent(false);
            throw new \Exception("Détail de l'erreur Anthropic : " . $errorData);
        }

        $data = $response->toArray();
        return $data['content'][0]['text'] ?? 'Erreur de réponse de l\'IA';
    }
}