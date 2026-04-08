<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Core\Updater\Updater;

class CheckUpdateCommand extends Command
{
    public function __construct(
        private Updater $updater,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cli:check-update')
            ->setDescription('Check for updates')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $currentVersion = $this->updater->getCurrentVersion();

        $io->info('Current version: '.$currentVersion);

        if (!$this->updater->checkUpdate()) {
            $io->success('You are on the latest version of swk cli.');

            return self::SUCCESS;
        }

        $latestVersion = $this->updater->getLastVersion();
        $io->warning('Latest version: '.$latestVersion);

        if ($io->askQuestion(new ConfirmationQuestion('A new version is available, do you want to update?', true))) {
            try {
                $this->updater->updateToLastVersion();
            } catch (\Throwable $e) {
                $io->error($e->getMessage());
                $io->warning('Update failed, please try again later.');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
