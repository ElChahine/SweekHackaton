<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Ai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClaudeClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const CONTINUATION_MAX_LOOPS = 4;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        // On utilise l'ID de modèle exact pour éviter les erreurs d'alias
        private string $model = 'claude-sonnet-4-6'
    ) {}

    public function call(string $systemPrompt, string $userPrompt): string
    {
        $messages = [
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $fullText = '';
        for ($loop = 0; $loop < self::CONTINUATION_MAX_LOOPS; ++$loop) {
            $data = $this->requestWithRetry($systemPrompt, $messages);
            $chunk = $this->extractTextContent($data);

            if ('' !== $chunk) {
                $fullText .= $chunk;
            }

            $stopReason = (string) ($data['stop_reason'] ?? '');
            if ('max_tokens' !== $stopReason) {
                break;
            }

            // Continue cleanly only when Anthropic truncated on output tokens.
            $messages[] = [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => $chunk],
                ],
            ];
            $messages[] = [
                'role' => 'user',
                'content' => 'Continue exactement où tu t\'es arrêté. Ne répète pas les sections déjà produites.',
            ];
        }

        return '' !== trim($fullText) ? $fullText : 'Erreur de réponse de l\'IA';
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     *
     * @return array<string, mixed>
     */
    private function requestWithRetry(string $systemPrompt, array $messages): array
    {
        // On nettoie la clé pour éviter les caractères invisibles de Cygwin (\r)
        $cleanApiKey = trim($this->apiKey);
        $requestTimeout = (float) ($_ENV['CLAUDE_REQUEST_TIMEOUT'] ?? getenv('CLAUDE_REQUEST_TIMEOUT') ?? 600);
        $maxDuration = (float) ($_ENV['CLAUDE_MAX_DURATION'] ?? getenv('CLAUDE_MAX_DURATION') ?? 1800);
        $maxTokens = (int) ($_ENV['CLAUDE_MAX_TOKENS'] ?? getenv('CLAUDE_MAX_TOKENS') ?? 8192);

        if ($requestTimeout <= 0) {
            $requestTimeout = 600;
        }

        if ($maxDuration <= 0) {
            $maxDuration = 1800;
        }

        if ($maxTokens <= 0) {
            $maxTokens = 8192;
        }

        $lastException = null;
        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            try {
                $response = $this->httpClient->request('POST', self::API_URL, [
                    'headers' => [
                        'x-api-key' => $cleanApiKey,
                        'anthropic-version' => '2023-06-01',
                        'content-type' => 'application/json',
                    ],
                    // Timeout explicite pour éviter les valeurs implicites trop agressives
                    'timeout' => $requestTimeout,
                    'max_duration' => $maxDuration,
                    'json' => [
                        'model' => $this->model,
                        'max_tokens' => $maxTokens,
                        'system' => $systemPrompt,
                        'messages' => $messages,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $rawBody = $response->getContent(false);

                if (200 !== $statusCode) {
                    throw new \Exception("Détail de l'erreur Anthropic : " . $rawBody);
                }

                return $this->decodeResponseJson($rawBody);
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt >= 2) {
                    break;
                }
            }
        }

        throw new \Exception('Erreur d\'appel Anthropic après retry: ' . ($lastException?->getMessage() ?? 'inconnue'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractTextContent(array $data): string
    {
        $content = $data['content'] ?? null;
        if (!is_array($content)) {
            return '';
        }

        $text = '';
        foreach ($content as $block) {
            if (
                is_array($block)
                && ('text' === ($block['type'] ?? null))
                && is_string($block['text'] ?? null)
            ) {
                $text .= $block['text'];
            }
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponseJson(string $rawBody): array
    {
        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $firstException) {
            // Some upstream responses may contain invisible control bytes; normalize and retry.
            $candidate = preg_replace('/^\xEF\xBB\xBF/', '', $rawBody) ?? $rawBody;
            $candidate = iconv('UTF-8', 'UTF-8//IGNORE', $candidate) ?: $candidate;
            $candidate = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $candidate) ?? $candidate;

            try {
                $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $secondException) {
                throw new \Exception(
                    'Réponse JSON Anthropic invalide: '
                    . $secondException->getMessage()
                    . ' (première erreur: '
                    . $firstException->getMessage()
                    . ')'
                );
            }
        }

        if (!is_array($decoded)) {
            throw new \Exception('Réponse Anthropic invalide: format JSON inattendu.');
        }

        return $decoded;
    }
}