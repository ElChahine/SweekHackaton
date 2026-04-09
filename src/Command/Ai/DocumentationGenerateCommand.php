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
    public function __construct(
        private DocAnalyzer $analyzer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ai:doc')
            ->setDescription('Genere automatiquement une documentation (Readme, Architecture, Runbook) a partir d\'un dossier')
            ->addArgument('directory', InputArgument::REQUIRED, 'Chemin vers le dossier a documenter (ex: src/Command)')
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Contexte specifique pour guider l\'IA')
            ->addOption('export', 'e', InputOption::VALUE_NONE, 'Exporter le resultat dans un fichier Markdown')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = (string) $input->getArgument('directory');
        $context = (string) ($input->getOption('context') ?? '');

        if (!is_dir($directory)) {
            $io->error("Le dossier '$directory' est introuvable.");
            return Command::FAILURE;
        }

        $io->title("Generation de la documentation IA : '$directory'");
        $io->note('Agregation des fichiers et analyse IA en cours (cela peut prendre quelques secondes)...');

        try {
            $progressBar = $io->createProgressBar();
            $progressBar->start();

            $report = $this->analyzer->analyze($directory, $context);

            $progressBar->finish();
            $io->newLine(2);

            $io->section('Documentation generee :');
            $io->writeln($report);

            if ($input->getOption('export')) {
                $docsFolder = 'docs/generated';
                if (!is_dir($docsFolder)) {
                    mkdir($docsFolder, 0777, true);
                }

                $safeDirName = str_replace(['/', '\\'], '_', rtrim($directory, '/'));
                $filename = sprintf('%s/DOC_%s_%s.md', $docsFolder, strtoupper($safeDirName), date('Ymd_His'));

                file_put_contents($filename, $report);
                $io->success("La documentation a ete exportee avec succes dans : $filename");
            } else {
                $io->success('Generation terminee (utilisez --export pour sauvegarder le resultat).');
            }
        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'appel IA : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}