<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Core\Ai\DocAnalyzer;

class DocumentationGenerateCommand extends Command
{
    protected static $defaultName = 'ai:doc:generate';

    public function __construct(private DocAnalyzer $analyzer) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ai:doc:generate')
             ->setDescription('Génère deux documentations (Tech & Fonctionnelle) pour un dossier')
             ->addArgument('directory', InputArgument::REQUIRED, 'Dossier à documenter')
             ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Contexte spécifique')
             ->addOption('export', 'e', InputOption::VALUE_OPTIONAL, 'Format d\'export (md ou json)', 'md');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = (string) $input->getArgument('directory');
        $ctx = (string) $input->getOption('context');
        $format = strtolower((string) $input->getOption('export'));

        if (!in_array($format, ['md', 'json'], true)) {
            $io->error("Format d'export invalide. Utilisez 'md' ou 'json'.");
            return Command::FAILURE;
        }

        $io->title("Génération de la Double Documentation IA : $dir (Format: $format)");

        try {
            $docsFolder = 'docs/generated/' . date('Ymd_His');
            if (!is_dir($docsFolder)) {
                mkdir($docsFolder, 0777, true);
            }

            // 1. Documentation Technique
            $io->section("Génération du volet Technique...");
            $techDoc = $this->analyzer->analyze($dir, 'technical', $ctx, $format);

            // 2. Documentation Fonctionnelle
            $io->section("Génération du volet Fonctionnel...");
            $funcDoc = $this->analyzer->analyze($dir, 'functional', $ctx, $format);

            if ('json' === $format) {
                $techJson = $this->normalizeJson($techDoc, 'Technique');
                $funcJson = $this->normalizeJson($funcDoc, 'Fonctionnel');

                file_put_contents("$docsFolder/README_TECH.json", $techJson);
                file_put_contents("$docsFolder/README_FUNC.json", $funcJson);

                $io->success([
                    "Documentation générée avec succès dans $docsFolder",
                    "- README_TECH.json",
                    "- README_FUNC.json"
                ]);
            } else {
                file_put_contents("$docsFolder/README_TECH.md", $techDoc);
                file_put_contents("$docsFolder/README_FUNC.md", $funcDoc);

                $io->success([
                    "Documentation générée avec succès dans $docsFolder",
                    "- README_TECH.md (Architecture, Mermaid, Classes)",
                    "- README_FUNC.md (Règles métier, Cas d'usage)"
                ]);
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function normalizeJson(string $rawJson, string $label): string
    {
        $candidate = trim($rawJson);
        $candidate = preg_replace('/^```(?:json)?\s*/i', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s*```$/', '', $candidate) ?? $candidate;
        $candidate = trim($candidate);
        $candidate = iconv('UTF-8', 'UTF-8//IGNORE', $candidate) ?: $candidate;

        // Si l'IA ajoute du texte autour, on tente d'extraire l'objet/tableau JSON principal.
        if (
            !str_starts_with($candidate, '{')
            && !str_starts_with($candidate, '[')
        ) {
            $firstObject = strpos($candidate, '{');
            $firstArray = strpos($candidate, '[');

            $start = false;
            if (false !== $firstObject && false !== $firstArray) {
                $start = min($firstObject, $firstArray);
            } elseif (false !== $firstObject) {
                $start = $firstObject;
            } elseif (false !== $firstArray) {
                $start = $firstArray;
            }

            if (false !== $start) {
                $candidate = substr($candidate, $start);
            }
        }

        // Supprime les caractères de contrôle non autorisés en JSON.
        $candidate = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $candidate) ?? $candidate;

        try {
            $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Fallback plus agressif si le modèle renvoie du JSON mal échappé.
            $candidate = preg_replace('/[\x00-\x1F\x7F]/', '', $candidate) ?? $candidate;
            try {
                $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $fallback = [
                    'type' => strtolower($label),
                    'warning' => 'Le modèle n\'a pas retourné un JSON valide. Contenu brut conservé.',
                    'error' => $e->getMessage(),
                    'raw_content' => trim($rawJson),
                ];

                return json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException("Le volet $label n'est pas un objet/tableau JSON valide.");
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}