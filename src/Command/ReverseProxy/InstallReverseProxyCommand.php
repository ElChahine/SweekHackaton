<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\ReverseProxy;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Walibuy\Sweeecli\Core\Configuration\Project\SwkProxy;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesAwareCommandInterface;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesConfiguration;

class InstallReverseProxyCommand extends AbstractReverseProxyCommand implements PrerequisitesAwareCommandInterface
{
    protected function configure(): void
    {
        $this
            ->setName('proxy:install')
            ->setDescription('Install the \'swk-proxy\' project')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->projectManager->getProject(SwkProxy::class);

        if (null === $project) {
            return self::INVALID;
        }

        (new Process(['git', 'clone', $project->getRepository(), $this->projectManager->getProjectPath($project)]))
            ->mustRun()
        ;

        (new Process($this->buildSwkProxyCommand('install')))
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

    public function getPrerequisites(): PrerequisitesConfiguration
    {
        return (new PrerequisitesConfiguration())
            ->addCustomValidator(fn () => true !== $this->projectManager->getProject(SwkProxy::class)?->isInstalled())
            ->addCustomValidator(fn () => false === (new Filesystem)->exists('/usr/local/bin/swk-proxy'))
        ;
    }
}
