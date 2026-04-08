<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\ReverseProxy;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesAwareCommandInterface;

class DoctorReverseProxyCommand extends AbstractReverseProxyCommand implements PrerequisitesAwareCommandInterface
{
    protected function configure(): void
    {
        $this
            ->setName('proxy:doctor')
            ->setDescription('Run diagnostics')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new Process($this->buildSwkProxyCommand('doctor')))
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
