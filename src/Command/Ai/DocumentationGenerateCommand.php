<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DocumentationGenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ai:document')
            ->setDescription('Generate technical documentation for the project')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generating Documentation:');
        $io->text('Coming soon');

        return self::SUCCESS;
    }
}
