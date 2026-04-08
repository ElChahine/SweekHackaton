<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CodeReviewCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ai:review')
            ->setDescription('Analyze code and provide AI-driven feedback')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generating Array:');
        $io->text('Coming soon');

        return self::SUCCESS;
    }
}
