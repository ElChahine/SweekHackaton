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

    public function analyze(string $directoryPath, string $context = ''): string
    {
        $promptPath = $this->projectDir . '/config/prompts/doc_generator.md';

        if (!is_file($promptPath)) {
            throw new \Exception("Le fichier de prompt est introuvable : $promptPath");
        }

        $systemPrompt = file_get_contents($promptPath);
        if (false === $systemPrompt) {
            throw new \Exception("Impossible de lire le fichier de prompt : $promptPath");
        }

        $codeContext = $this->aggregateDirectoryContent($directoryPath);

        $userPrompt = "Voici le contenu du dossier a documenter :\n\n" . $codeContext;

        if ('' !== $context) {
            $userPrompt .= "\n\nContexte supplementaire de l'equipe : " . $context;
        }

        return $this->claudeClient->call($systemPrompt, $userPrompt);
    }

    private function aggregateDirectoryContent(string $path): string
    {
        $finder = new Finder();
        $finder->files()
            ->in($path)
            ->name('*.php')
            ->name('*.yaml')
            ->name('*.xml')
            ->name('*.json');

        $content = '';
        foreach ($finder as $file) {
            $content .= '=== File: ' . $file->getRelativePathname() . " ===\n";
            $content .= $file->getContents() . "\n\n";
        }

        if ('' === $content) {
            throw new \Exception("Aucun fichier source valide (PHP/YAML/XML/JSON) trouve dans : $path");
        }

        return $content;
    }
}