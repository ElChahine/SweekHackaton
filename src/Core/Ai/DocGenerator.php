<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Ai;

class DocGenerator
{
    public function __construct(
        private ClaudeClient $claudeClient
    ) {}

    public function generate(string $sourceCode): string
    {
        $promptPath = __DIR__ . '/../../../config/prompts/doc_expert.md';

        if (!file_exists($promptPath)) {
            return "Erreur : Le fichier de prompt doc_expert.md est manquant.";
        }

        $systemPrompt = file_get_contents($promptPath);

        return $this->claudeClient->call(
            $systemPrompt,
            "Voici le code source à documenter :\n\n" . $sourceCode
        );
    }
}