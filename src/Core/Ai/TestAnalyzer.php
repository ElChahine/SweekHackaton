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
        $promptPath = $this->projectDir . '/config/prompts/test_strategy_expert.md';
        
        if (!is_file($promptPath)) {
            throw new \Exception("Prompt de stratégie manquant : $promptPath");
        }

        $systemPrompt = file_get_contents($promptPath);
        $userPrompt = "ANALYSE DE CODE POUR STRATÉGIE DE TEST :\n\n```php\n" . $contentToTest . "\n```";
        
        if ('' !== $context) {
            $userPrompt .= "\n\nContexte équipe : " . $context;
        }

        return $this->claudeClient->call($systemPrompt, $userPrompt);
    }
}