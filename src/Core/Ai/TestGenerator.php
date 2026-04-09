<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Ai;

class TestGenerator
{
    public function __construct(
        private ClaudeClient $claudeClient,
        private string $projectDir
    ) {}

    public function generate(string $sourceCode, string $strategy, string $fixtures, string $type = 'unit'): string
    {
        $promptFile = ($type === 'functional') ? 'test_functional_expert.md' : 'test_unit_expert.md';
        $promptPath = $this->projectDir . '/config/prompts/' . $promptFile;

        $systemPrompt = file_get_contents($promptPath);
        $userPrompt = "RÉDACTION DES TESTS ($type)\n" .
                      "Stratégie à suivre : $strategy\n" .
                      "Fixtures à utiliser : $fixtures\n\n" .
                      "Code source :\n$sourceCode";

        return $this->claudeClient->call($systemPrompt, $userPrompt);
    }
}