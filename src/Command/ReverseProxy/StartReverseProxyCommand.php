<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\ReverseProxy;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesAwareCommandInterface;

class StartReverseProxyCommand extends AbstractReverseProxyCommand implements PrerequisitesAwareCommandInterface
{
    protected function configure(): void
    {
        $this
            ->setName('proxy:start')
            ->setDescription('Start the \'swk-proxy\' environment')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new Process($this->buildSwkProxyCommand('up')))
            ->run(function ($type, $buffer) use ($output): void {
                if (Process::ERR === $type) {
                    $output->write("<error>$buffer</error>");
                } else {
                    $output->write($buffer);
                }
            })
        ;

        return self::SUCCESS;
    }
}
