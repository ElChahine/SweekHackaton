<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Env;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Walibuy\Sweeecli\Command\Env\Tools\EnvTool;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesAwareCommandInterface;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesConfiguration;

class InitEnvSystemCommand extends Command implements PrerequisitesAwareCommandInterface
{
    protected function configure(): void
    {
        $this
            ->setName('env:init')
            ->setDescription('Initialize the environment variables system')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fileSystem = new Filesystem();

        if ($fileSystem->exists(EnvTool::CONFIG_FILE_NAME)) {
            $io->error(sprintf('The %s file already exists.', EnvTool::CONFIG_FILE_NAME));

            return self::FAILURE;
        }

        $fileSystem->touch(EnvTool::CONFIG_FILE_NAME);
        $fileSystem->appendToFile(
            EnvTool::CONFIG_FILE_NAME,
            Yaml::dump(EnvTool::DEFAULT_CONFIG, flags: Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE)
        );

        $io->success(sprintf('The %s file has been initialized.', EnvTool::CONFIG_FILE_NAME));

        return self::SUCCESS;
    }

    public function getPrerequisites(): PrerequisitesConfiguration
    {
        return (new PrerequisitesConfiguration())
            ->andFileOrDirectoryNotExist(EnvTool::CONFIG_FILE_NAME)
            ->andOneOfFilesOrDirectoriesExist('.git')
        ;
    }
}
