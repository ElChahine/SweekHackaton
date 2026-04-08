<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Documentation;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OpenDocCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('documentation:open')
            ->setDescription('Open the documentation in the browser')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Opening documentation in the browser:');
        $io->text('https://doc.sweeek.org');
        exec('open https://doc.sweeek.org');

        return self::SUCCESS;
    }
}
