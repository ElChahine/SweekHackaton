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

class GenerateEnvFileCommand extends Command implements PrerequisitesAwareCommandInterface
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
            ->setName('env:export:file')
            ->addArgument('applicationName', InputArgument::REQUIRED, 'The application name')
            ->addArgument('filename', InputArgument::OPTIONAL, 'The path to the .env file')
            ->setDescription('Generate a .env file from the environment variables')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $applicationName = $input->getArgument('applicationName');
        $filename = $input->getArgument('filename') ?: sprintf('.%s.env', $applicationName);

        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        if (!$filesystem->exists(EnvTool::CONFIG_FILE_NAME)) {
            $io->error('No config file found. Run "env:init" to create one.');

            return self::FAILURE;
        }

        $config = Yaml::parseFile(EnvTool::CONFIG_FILE_NAME);

        $mapping = $config['_mapping'] ?? [];

        $outputLines = [];

        $envs = array_merge($config[$applicationName]['configmaps'] ?? [], $config[$applicationName]['secrets'] ?? []);

        $existingKeys = $this->envTool->getEnvFileKeys($filename);

        foreach ($envs as $key => $value) {
            $newKey = $this->envTool->getCleanVariableName($key, $mapping);
            if (!in_array($newKey, $existingKeys)) {
                $outputLines[$newKey] = $value;
            }
        }

        $filesystem->appendToFile(
            $filename,
            implode(
                PHP_EOL,
                array_map(fn ($k, $v) => sprintf('%s="%s"', $k, $v), array_keys($outputLines), $outputLines)
            )
        );

        return self::SUCCESS;
    }

    public function getPrerequisites(): PrerequisitesConfiguration
    {
        return (new PrerequisitesConfiguration())
            ->andOneOfFilesOrDirectoriesExist(EnvTool::CONFIG_FILE_NAME)
        ;
    }
}
