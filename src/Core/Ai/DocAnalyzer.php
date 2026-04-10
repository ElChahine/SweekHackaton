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

    public function analyze(string $directoryPath, string $type = 'technical', string $context = '', string $format = 'md'): string
    {
        $promptFile = ($type === 'functional') ? 'doc_functional_expert.md' : 'doc_technical_expert.md';
        $promptPath = $this->projectDir . '/config/prompts/' . $promptFile;

        if (!is_file($promptPath)) {
            throw new \Exception("Prompt de documentation manquant : $promptPath");
        }

        $systemPrompt = file_get_contents($promptPath);
        if ('json' === strtolower($format)) {
            $systemPrompt .= "\n\nCONTRAINTE DE FORMAT: Réponds uniquement avec un JSON valide, sans markdown, sans bloc de code, et sans texte hors JSON.";
        }
        $codeContext = $this->aggregateDirectoryContent($directoryPath);
        $fileInventory = $this->listDirectoryFiles($directoryPath);

        $userPrompt = "DOC TYPE: $type\n\nLISTE DES FICHIERS ANALYSÉS :\n" . $fileInventory
            . "\n\nCONTENU DU DOSSIER :\n" . $codeContext;
        if ('' !== $context) {
            $userPrompt .= "\n\nCONTEXTE SUPPLÉMENTAIRE :\n" . $context;
        }

        if ('md' === strtolower($format)) {
            $userPrompt .= "\n\nCONTRAINTE DE DÉTAIL (OBLIGATOIRE):\n"
                . "- Produis une documentation longue, exhaustive et détaillée (pas de résumé court).\n"
                . "- Couvre explicitement chaque fichier de la liste avec son rôle, ses dépendances et ses impacts.\n"
                . "- Inclue une section dédiée \"Couverture par Fichier\" avec un sous-bloc par fichier.\n"
                . "- Pour chaque classe/fichier critique, détaille: responsabilités, entrées/sorties, erreurs possibles, points de vigilance.\n"
                . "- Donne des exemples concrets de scénarios, y compris cas nominaux et cas d\'échec.\n"
                . "- N\'écourte pas la réponse: priorité à la complétude et à la précision.\n";
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

    private function listDirectoryFiles(string $path): string
    {
        $finder = new Finder();
        $finder->files()->in($path)->name(['*.php', '*.yaml', '*.xml', '*.json']);

        $files = [];
        foreach ($finder as $file) {
            $files[] = '- ' . $file->getRelativePathname();
        }

        if ([] === $files) {
            return '- (aucun fichier compatible trouvé)';
        }

        sort($files);

        return implode("\n", $files);
    }
}