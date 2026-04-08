<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Core\Ai\ClaudeClient;

class CodeReviewCommand extends Command
{
    // Indispensable pour éviter le TypeError
    public function __construct(
        private ClaudeClient $claudeClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ai:review')
            ->setDescription('Analyze code (Back to basics)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Code Review :');
        $io->text('Bientôt disponible - Connexion IA opérationnelle.');

        return self::SUCCESS;
    }
}