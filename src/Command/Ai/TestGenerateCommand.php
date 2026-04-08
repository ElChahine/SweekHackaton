<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TestGenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ai:unit_test:create')
            ->setDescription('Generate functional test suites for the project')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generating Tests:');
        $io->text('Coming soon');

        return self::SUCCESS;
    }
}
