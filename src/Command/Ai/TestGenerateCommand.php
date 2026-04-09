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
use Walibuy\Sweeecli\Core\Ai\TestAnalyzer;

class TestGenerateCommand extends Command
{
    public function __construct(
        private TestAnalyzer $analyzer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ai:test')
            ->setDescription('Genere automatiquement des tests via l\'IA (sur un diff ou un fichier)')
            ->addArgument('base', InputArgument::OPTIONAL, 'La branche ou le commit de base', 'HEAD')
            ->addArgument('target', InputArgument::OPTIONAL, 'La branche cible a comparer')
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Contexte (ex: framework PHPUnit, Pest, regles de nommage)')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Chemin vers un fichier a analyser en entier (au lieu du git diff)')
            ->addOption('export', 'e', InputOption::VALUE_NONE, 'Exporter les tests dans un fichier AI_TEST_<timestamp>.md')
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

            $io->title("Preparation generation de tests : fichier '$resolvedFilePath'");
            $contentToReview = "Fichier analyse : $resolvedFilePath\n\n$fileContent";
        } else {
            $base = $input->getArgument('base');
            $target = $input->getArgument('target');
            $gitArgs = ['git', 'diff', $base];
            if ($target) {
                $gitArgs[] = $target;
            }

            $io->title('Preparation generation de tests : ' . $base . ($target ? " <-> $target" : ' (local)'));

            $process = new Process($gitArgs);
            $process->run();

            if (!$process->isSuccessful()) {
                $io->error('Erreur Git : ' . $process->getErrorOutput());
                return Command::FAILURE;
            }

            $diff = $process->getOutput();
            if (empty($diff)) {
                $io->warning('Aucun changement detecte pour generer des tests.');
                return Command::SUCCESS;
            }

            $contentToReview = $diff;
        }

        $io->title('Generation des tests par l\'IA de sweeek');
        $context = $input->getOption('context') ?? 'Framework par defaut : PHPUnit. Focus sur la couverture des Edge Cases.';
        $io->note('Analyse du contenu (' . strlen($contentToReview) . ' caracteres) et generation des tests en cours...');

        try {
            $progressBar = $io->createProgressBar();
            $progressBar->start();

            $response = $this->analyzer->analyze($contentToReview, $context);

            $progressBar->finish();
            $io->newLine(2);

            $io->section('Tests generes :');
            $io->writeln($response);

            if ($input->getOption('export')) {
                $folder = 'reports';
                if (!is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }

                $filename = 'reports/AI_TEST_' . date('Ymd_His') . '.md';
                file_put_contents($filename, $response);
                $io->success("Les tests ont ete exportes avec succes dans : $filename");
            }

            $io->success('Generation de tests terminee avec succes.');
        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'appel IA : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}