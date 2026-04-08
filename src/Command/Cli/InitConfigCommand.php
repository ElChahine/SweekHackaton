<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Walibuy\Sweeecli\Core\Configuration\ConfigurationManager;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesAwareCommandInterface;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesConfiguration;

class InitConfigCommand extends Command implements PrerequisitesAwareCommandInterface
{
    public function __construct(
        private ConfigurationManager $configurationManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cli:config:init')
            ->setDescription('Initialize the swk config file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filesystem = new Filesystem();

        $configPath = $this->configurationManager->getConfigFilePath();
        if (!$filesystem->exists($configPath)) {
            $io->text(sprintf('Creating %s file', $configPath));

            $config = $this->configurationManager->getDefaultConfiguration();
            $content = implode(PHP_EOL, array_map(fn ($line) => '#'.$line, explode(PHP_EOL, Yaml::dump($config))));
            $filesystem->dumpFile($configPath, $content);
        }

        return self::SUCCESS;
    }

    public function getPrerequisites(): PrerequisitesConfiguration
    {
        return (new PrerequisitesConfiguration())
            ->andFileOrDirectoryNotExist($this->configurationManager->getConfigFilePath())
        ;
    }
}
