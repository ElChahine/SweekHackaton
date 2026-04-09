<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Walibuy\Sweeecli\Core\Ai\ReviewAnalyzer;

class CodeReviewCommand extends Command
{
    public function __construct(
        private ReviewAnalyzer $analyzer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ai:review')
            ->setDescription('Analyse le code via l\'IA avec export et progression')
            ->addArgument('base', InputArgument::OPTIONAL, 'La branche ou le commit de base', 'HEAD')
            ->addArgument('target', InputArgument::OPTIONAL, 'La branche cible à comparer')
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Contexte spécifique du projet')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Chemin vers un fichier à analyser en entier (au lieu du git diff)')
            ->addOption('export', 'e', InputOption::VALUE_NONE, 'Exporter la review dans un fichier AI_REVIEW.md')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filePath = $input->getOption('file');
        $contentToReview = '';

        if (null !== $filePath) {
            $resolvedFilePath = (string) $filePath;

            if (!is_file($resolvedFilePath)) {
                $io->error("Le fichier '$resolvedFilePath' est introuvable.");
                return Command::FAILURE;
            }

            if (!is_readable($resolvedFilePath)) {
                $io->error("Le fichier '$resolvedFilePath' n'est pas lisible.");
                return Command::FAILURE;
            }

            $fileContent = file_get_contents($resolvedFilePath);
            if (false === $fileContent) {
                $io->error("Impossible de lire le fichier '$resolvedFilePath'.");
                return Command::FAILURE;
            }

            if ('' === trim($fileContent)) {
                $io->warning("Le fichier '$resolvedFilePath' est vide.");
                return Command::SUCCESS;
            }

            $io->title("Préparation de la review IA : fichier '$resolvedFilePath'");
            $contentToReview = "Fichier analysé : $resolvedFilePath\n\n$fileContent";
        } else {
            $base = $input->getArgument('base');
            $target = $input->getArgument('target');
            $gitArgs = ['git', 'diff', $base];
            if ($target) {
                $gitArgs[] = $target;
            }

            $io->title("Préparation de la review IA : $base" . ($target ? " <-> $target" : " (local)"));

            $process = new Process($gitArgs);
            $process->run();

            if (!$process->isSuccessful()) {
                $io->error('Erreur Git : ' . $process->getErrorOutput());
                return Command::FAILURE;
            }

            $diff = $process->getOutput();
            if (empty($diff)) {
                $io->warning('Aucun changement détecté pour la review.');
                return Command::SUCCESS;
            }

            $contentToReview = $diff;
        }

        $io->title("Audit de code par l'IA de sweeek");
        $context = $input->getOption('context') ?? 'Application Symfony CLI.';
        $io->note('Analyse du contenu (' . strlen($contentToReview) . ' caractères) par Claude...');

        try {
            $progressBar = $io->createProgressBar();
            $progressBar->start();

            $report = $this->analyzer->analyze($contentToReview, $context);

            $progressBar->finish();
            $io->newLine(2);

            $io->section('Rapport de Review :');
            $io->writeln($report);

            if ($input->getOption('export')) {
                $folder = 'reports';
                if (!is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }

                $filename = 'reports/AI_REVIEW.md';
                file_put_contents($filename, $report);
                $io->success("Le rapport a été exporté avec succès dans : $filename");
            }

            $io->success('Review terminée avec succès.');
        } catch (\Exception $e) {
            $io->error("Erreur lors de l'appel IA : " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}