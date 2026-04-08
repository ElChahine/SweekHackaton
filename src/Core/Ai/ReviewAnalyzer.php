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
        $systemPrompt = 'Tu es un Senior Developer expert en Symfony. Analyse ce diff et structure ton retour par : 1. Sécurité, 2. Performance, 3. Propreté, 4. Suggestions.';

        return $this->claudeClient->call(
            $systemPrompt,
            "Contexte : $context\n\nVoici le code :\n$diff"
        );
    }
}