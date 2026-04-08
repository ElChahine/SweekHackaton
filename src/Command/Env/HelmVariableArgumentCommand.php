<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Env;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Walibuy\Sweeecli\Command\Env\Tools\EnvTool;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesAwareCommandInterface;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesConfiguration;

class HelmVariableArgumentCommand extends Command implements PrerequisitesAwareCommandInterface
{
    private EnvTool $envTool;

    public function __construct()
    {
        $this->envTool = new EnvTool();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('env:helm:arguments')
            ->addArgument('applicationName', InputArgument::REQUIRED, 'The application name')
            ->addArgument('env', InputArgument::REQUIRED, 'Environment')
            ->setDescription('Generate helm arguments for environment variables')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $applicationName = $input->getArgument('applicationName');
        $env = $input->getArgument('env');

        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        if (!$filesystem->exists(EnvTool::CONFIG_FILE_NAME)) {
            $io->error('No config file found. Run "env:init" to create one.');

            return self::FAILURE;
        }

        $config = Yaml::parseFile(EnvTool::CONFIG_FILE_NAME);

        $mapping = $config['_mapping'] ?? [];

        $instructions = [];

        foreach ($config[$applicationName]['secrets'] ?? [] as $key => $value) {
            $instructions[] = sprintf(
                '--set app.secrets.%s=%s',
                $this->envTool->getCleanVariableName($key, $mapping),
                $_ENV[$this->envTool->getScopedVariableName($key, $env, $mapping)] ?? $value ?? ''
            );
        }

        foreach ($config[$applicationName]['configmaps'] ?? [] as $key => $value) {
            $instructions[] = sprintf(
                '--set app.configmaps.%s=%s',
                $this->envTool->getCleanVariableName($key, $mapping),
                $_ENV[$this->envTool->getScopedVariableName($key, $env, $mapping)] ?? $value ?? ''
            );
        }

        $io->writeln(implode(' ', $instructions));

        return self::SUCCESS;
    }

    public function getPrerequisites(): PrerequisitesConfiguration
    {
        return (new PrerequisitesConfiguration())
            ->andOneOfFilesOrDirectoriesExist(EnvTool::CONFIG_FILE_NAME)
        ;
    }
}
