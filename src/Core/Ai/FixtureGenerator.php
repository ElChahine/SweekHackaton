<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Ai;

class FixtureGenerator
{
    public function __construct(
        private ClaudeClient $claudeClient,
        private string $projectDir
    ) {}

    public function generate(string $sourceCode, string $strategy): string
    {
        $promptPath = $this->projectDir . '/config/prompts/test_fixtures_expert.md';
        $systemPrompt = is_file($promptPath) ? file_get_contents($promptPath) : "Tu es un expert en fixtures PHP.";

        $userPrompt = "Basé sur cette stratégie : \n$strategy\n\nGénère les fixtures pour ce code :\n$sourceCode";

        return $this->claudeClient->call($systemPrompt, $userPrompt);
    }
}