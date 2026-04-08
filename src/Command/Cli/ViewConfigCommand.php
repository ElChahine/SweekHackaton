<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Core\Configuration\ConfigurationManager;

class ViewConfigCommand extends Command
{
    public function __construct(
        private ConfigurationManager $configurationManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cli:config:view')
            ->setDescription('View the swk config file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $configuration = $this->configurationManager->getConfiguration();

        $io->tree($this->serializeConfiguration($configuration), $this->configurationManager->getConfigFilePath());

        return self::SUCCESS;
    }

    protected function serializeConfiguration(array $configuration): array
    {
        $result = [];

        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->serializeConfiguration($value);
            } else {
                $result[$key] = $key.':<info>'.$value.'</info>';
            }
        }

        return $result;
    }
}
