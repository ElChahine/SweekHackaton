<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Ai;

use Symfony\Component\Finder\Finder;

class DocAnalyzer
{
    public function __construct(
        private ClaudeClient $claudeClient,
        private string $projectDir
    ) {}

    public function analyze(string $directoryPath, string $type = 'technical', string $context = ''): string
    {
        $promptFile = ($type === 'functional') ? 'doc_functional_expert.md' : 'doc_technical_expert.md';
        $promptPath = $this->projectDir . '/config/prompts/' . $promptFile;

        if (!is_file($promptPath)) {
            throw new \Exception("Prompt de documentation manquant : $promptPath");
        }

        $systemPrompt = file_get_contents($promptPath);
        $codeContext = $this->aggregateDirectoryContent($directoryPath);

        $userPrompt = "DOC TYPE: $type\n\nCONTENU DU DOSSIER :\n" . $codeContext;
        if ('' !== $context) {
            $userPrompt .= "\n\nCONTEXTE SUPPLÉMENTAIRE :\n" . $context;
        }

        return $this->claudeClient->call($systemPrompt, $userPrompt);
    }

    private function aggregateDirectoryContent(string $path): string
    {
        $finder = new Finder();
        $finder->files()->in($path)->name(['*.php', '*.yaml', '*.xml', '*.json']);
        $content = '';
        foreach ($finder as $file) {
            $content .= "=== File: " . $file->getRelativePathname() . " ===\n" . $file->getContents() . "\n\n";
        }
        return $content;
    }
}