<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Hotfix;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Command\Git\AbstractGitCommand;
use Walibuy\Sweeecli\Command\Git\Enum\RemoteType;

class HotfixAbortCommand extends AbstractGitCommand
{
    protected function configure(): void
    {
        $this
            ->setName('hotfix:abort')
            ->setDescription('Abort the hotfix branch')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $version = $this->getLatestVersionTag();
        $version->incrementMinor();
        $hotfixBranch = sprintf('hotfix/%s', $version);

        if (!$io->askQuestion(new ConfirmationQuestion(sprintf('Are you sure you want to abort the hotfix %s ? (Y/N)', $version), false))) {
            $io->warning('Action canceled');

            return self::FAILURE;
        }

        $this->checkoutToBranch();

        $io->text(sprintf('Delete local branch %s', $hotfixBranch));
        $this->removeBranch($hotfixBranch);

        $io->text(sprintf('Delete remote branch %s', $hotfixBranch));
        $this->removeBranch($hotfixBranch, RemoteType::MAIN);

        $io->success('Hotfix successfully aborted.');

        return self::SUCCESS;
    }
}
