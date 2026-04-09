<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Ai;

class TestAnalyzer
{
    public function __construct(
        private ClaudeClient $claudeClient,
        private string $projectDir
    ) {}

    public function analyze(string $contentToTest, string $context = ''): string
    {
        $promptPath = $this->projectDir . '/config/prompts/test_generator.md';

        if (!is_file($promptPath)) {
            throw new \Exception("Le fichier de prompt est introuvable : $promptPath");
        }

        $systemPrompt = file_get_contents($promptPath);
        if (false === $systemPrompt) {
            throw new \Exception("Impossible de lire le fichier de prompt : $promptPath");
        }

        $userPrompt = "Voici le code source pour lequel tu dois generer les tests :\n\n```php\n" . $contentToTest . "\n```";

        if ('' !== $context) {
            $userPrompt .= "\n\nContexte technique de l'equipe (Framework, regles) : " . $context;
        }

        return $this->claudeClient->call($systemPrompt, $userPrompt);
    }
}
