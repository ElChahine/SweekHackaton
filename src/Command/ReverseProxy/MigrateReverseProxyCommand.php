<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\ReverseProxy;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Walibuy\Sweeecli\Core\Configuration\Project\SwkProxy;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesAwareCommandInterface;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesConfiguration;

class MigrateReverseProxyCommand extends AbstractReverseProxyCommand implements PrerequisitesAwareCommandInterface
{
    protected function configure(): void
    {
        $this
            ->setName('proxy:migrate')
            ->setDescription('Migrate the \'swk-proxy\' project into sweeecli')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->projectManager->getProject(SwkProxy::class);

        if (null === $project) {
            return self::INVALID;
        }

        $realpath = (new Filesystem)->readlink('/usr/local/bin/swk-proxy', true);

        if (null === $realpath) {
            return self::INVALID;
        }

        $directory = Path::getDirectory($realpath);

        new Process(['make', '-C', $directory, 'FROM=swk-proxy', 'down'])
            ->run(function ($type, $buffer) use ($output): void {
                if (Process::ERR === $type) {
                    $output->write("<error>$buffer</error>");
                } else {
                    $output->write($buffer);
                }
            })
        ;

        (new Filesystem)->rename($directory, $this->projectManager->getProjectPath($project), true);

        (new Filesystem)->remove('/usr/local/bin/swk-proxy');

        return self::SUCCESS;
    }

    public function getPrerequisites(): PrerequisitesConfiguration
    {
        return (new PrerequisitesConfiguration())
            ->addCustomValidator(fn () => true !== $this->projectManager->getProject(SwkProxy::class)?->isInstalled())
            ->addCustomValidator(fn () => (new Filesystem)->exists('/usr/local/bin/swk-proxy'))
        ;
    }
}
