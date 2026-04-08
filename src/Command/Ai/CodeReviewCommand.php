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
            ->setDescription('Analyse le code et fournit un feedback détaillé via l\'IA')
            // Argument pour comparer avec une branche (ex: main)
            ->addArgument('base', InputArgument::OPTIONAL, 'La branche ou le commit de base', 'HEAD')
            // Argument pour spécifier la branche cible
            ->addArgument('target', InputArgument::OPTIONAL, 'La branche cible à comparer')
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Contexte spécifique du projet')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // 1. Construction dynamique de la commande git diff
        $base = $input->getArgument('base');
        $target = $input->getArgument('target');
        $gitArgs = ['git', 'diff', $base];
        if ($target) {
            $gitArgs[] = $target;
        }

        $io->title("Préparation de la review IA : $base" . ($target ? " <-> $target" : " (local)"));

        // 2. Exécution du process Git
        $process = new Process($gitArgs);
        $process->run();

        if (!$process->isSuccessful()) {
            $io->error("Erreur Git : " . $process->getErrorOutput());
            return Command::FAILURE;
        }

        $diff = $process->getOutput();

        if (empty($diff)) {
            $io->warning("Aucun changement détecté pour la review.");
            return Command::SUCCESS;
        }

        // 3. Analyse via le service centralisé
        $context = $input->getOption('context') ?? "Application Symfony CLI.";

        $io->note("Analyse du diff (" . strlen($diff) . " caractères) par Claude...");

        try {
            $review = $this->analyzer->analyze($diff, $context);

            $io->section('Rapport de Review :');
            $io->writeln($review);
            $io->success('Review terminée avec succès.');

        } catch (\Exception $e) {
            $io->error("Erreur lors de l'appel IA : " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}