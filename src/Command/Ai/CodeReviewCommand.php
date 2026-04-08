<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Walibuy\Sweeecli\Core\Ai\ClaudeClient;

class CodeReviewCommand extends Command
{
    public function __construct(
        private ClaudeClient $claudeClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ai:review')
            ->setDescription('Analyze code and provide AI-driven feedback')
            ->addOption('prompt', 'p', InputOption::VALUE_OPTIONAL, 'Prompt personnalisé')
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Contexte du projet')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $process = new Process(['git', 'diff', 'HEAD']);
        $process->run();
        $diff = $process->getOutput();

        if (empty($diff)) {
            $io->warning("Aucun changement détecté dans git.");
            return Command::SUCCESS;
        }

        $system = $input->getOption('prompt') ?? "Tu es un expert en clean code PHP. Fais une review détaillée de ce diff.";
        $context = $input->getOption('context') ?? "Projet Symfony CLI.";

        $io->title('Analyse du code par Claude...');
        
        try {
            $review = $this->claudeClient->call($system, "Contexte: $context \n\n Diff: \n $diff");
            $io->section('Review de Claude :');
            $io->writeln($review);
        } catch (\Exception $e) {
            $io->error("Erreur API : " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}