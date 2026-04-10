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
             ->addOption('json', 'j', InputOption::VALUE_NONE, 'Exporter également au format JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = (string) $input->getArgument('directory');
        $ctx = (string) $input->getOption('context');
        $exportJson = (bool) $input->getOption('json');

        $io->title("Génération de la Double Documentation IA : $dir");

        try {
            $timestamp = date('Ymd_His');
            $docsFolder = 'docs/generated/' . $timestamp;
            if (!is_dir($docsFolder)) {
                mkdir($docsFolder, 0777, true);
            }

            $io->section("Génération du volet Technique...");
            $techDoc = $this->analyzer->analyze($dir, 'technical', $ctx);
            file_put_contents("$docsFolder/README_TECH.md", $techDoc);

            $io->section("Génération du volet Fonctionnel...");
            $funcDoc = $this->analyzer->analyze($dir, 'functional', $ctx);
            file_put_contents("$docsFolder/README_FUNC.md", $funcDoc);

            if ($exportJson) {
                $io->section("Export JSON en cours...");
                $jsonData = [
                    'generated_at' => date('Y-m-d H:i:s'),
                    'directory' => $dir,
                    'technical' => $techDoc,
                    'functional' => $funcDoc,
                ];
                
                file_put_contents(
                    "$docsFolder/documentation.json", 
                    json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }

            $successMessages = [
                "Documentation générée avec succès dans $docsFolder",
                "- README_TECH.md (Architecture, Mermaid, Classes)",
                "- README_FUNC.md (Règles métier, Cas d'usage)"
            ];

            if ($exportJson) {
                $successMessages[] = "- documentation.json (Format structuré)";
            }

            $io->success($successMessages);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}