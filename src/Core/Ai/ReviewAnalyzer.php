<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Ai;

class ReviewAnalyzer
{
    public function __construct(
        private ClaudeClient $claudeClient
    ) {}

    public function analyze(string $diff, string $context): string
    {
        $promptPath = __DIR__ . '/../../../config/prompts/review_expert.md';

        if (!file_exists($promptPath)) {
            throw new \Exception("Le fichier de prompt est introuvable à l'adresse : " . $promptPath);
        }

        $systemPrompt = file_get_contents($promptPath);

        return $this->claudeClient->call(
            $systemPrompt,
            "Contexte : $context\n\nVoici le code :\n$diff"
        );
    }
}