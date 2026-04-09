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
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Contexte spécifique', 'Standard')
            // AJOUT DE L'OPTION D'EXPORT
            ->addOption('export', 'e', InputOption::VALUE_NONE, 'Exporter la review dans un fichier AI_REVIEW.md');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // 1. Récupération du diff
        $process = new Process(['git', 'diff', $input->getArgument('base')]);
        $process->run();
        $diff = $process->getOutput();

        if (empty($diff)) {
            $io->warning("Aucun changement détecté pour la review.");
            return Command::SUCCESS;
        }

        $io->title("Audit de code par l'IA de sweeek");

        try {
            // 2. MISE EN PLACE DE LA BARRE DE PROGRESSION
            $io->note("Analyse du diff par Claude en cours...");
            $progressBar = $io->createProgressBar();
            $progressBar->start();

            // Appel au service (le "cerveau")
            $report = $this->analyzer->analyze($diff, $input->getOption('context'));

            $progressBar->finish();
            $io->newLine(2); // Pour sauter une ligne après la barre

            // 3. AFFICHAGE DU RAPPORT DANS LA CONSOLE
            $io->section('Rapport de Review :');
            $io->writeln($report);

            // 4. LOGIQUE D'EXPORTATION
            if ($input->getOption('export')) {
                $folder = 'reports'; // Nom du dossier cible
    
                // Vérifie si le dossier existe, sinon le crée
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