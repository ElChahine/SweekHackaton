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
        $this->setDescription('Génère deux documentations (Tech & Fonctionnelle) pour un dossier')
             ->addArgument('directory', InputArgument::REQUIRED, 'Dossier à documenter')
             ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Contexte spécifique');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = (string) $input->getArgument('directory');
        $ctx = (string) $input->getOption('context');

        $io->title("Génération de la Double Documentation IA : $dir");

        try {
            $docsFolder = 'docs/generated/' . date('Ymd_His');
            if (!is_dir($docsFolder)) mkdir($docsFolder, 0777, true);

            // 1. Documentation Technique
            $io->section("Génération du volet Technique...");
            $techDoc = $this->analyzer->analyze($dir, 'technical', $ctx);
            file_put_contents("$docsFolder/README_TECH.md", $techDoc);

            // 2. Documentation Fonctionnelle
            $io->section("Génération du volet Fonctionnel...");
            $funcDoc = $this->analyzer->analyze($dir, 'functional', $ctx);
            file_put_contents("$docsFolder/README_FUNC.md", $funcDoc);

            $io->success([
                "Documentation générée avec succès dans $docsFolder",
                "- README_TECH.md (Architecture, Mermaid, Classes)",
                "- README_FUNC.md (Règles métier, Cas d'usage)"
            ]);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}