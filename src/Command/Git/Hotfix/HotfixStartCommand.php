<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Hotfix;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Command\Git\AbstractGitCommand;
use Walibuy\Sweeecli\Command\Git\Enum\RemoteType;

class HotfixStartCommand extends AbstractGitCommand
{
    protected function configure(): void
    {
        $this
            ->setName('hotfix:start')
            ->addOption('stash-changes', 's', InputOption::VALUE_NONE, 'Stash local changes before starting hotfix')
            ->setDescription('Start a new hotfix branch')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $hasStash = false;

        if ($this->hasLocalChanges()) {
            if (!$input->getOption('stash-changes') && !$io->askQuestion(new ConfirmationQuestion('⚠️ Local changes, Do you want to stash changes ?', true))) {
                $io->error('⚠️ Local changes, cannot start hotfix.');

                return self::FAILURE;
            }

            $this->add();
            $this->stash();
            $hasStash = true;
        }

        $this->checkoutToBranch();
        $this->fetch();
        $this->reset(true, 'master', RemoteType::MAIN);

        $newVersion = $this->getLatestVersionTag();
        $newVersion->incrementMinor();

        $hotfixBranch = sprintf('hotfix/%s', $newVersion);

        $io->text('Search a branch named: '.$hotfixBranch);

        if ($this->isBranchExistInRemote($hotfixBranch)) {
            if ($this->isBranchExistInLocal($hotfixBranch)) {
                $io->text(sprintf('Hotfix %s local branch already exist. Deletion..', $hotfixBranch));
                $this->removeBranch($hotfixBranch);
            }

            $io->text(sprintf('Hotfix %s remote branch already exist. Checkout..', $hotfixBranch));
            $this->checkoutToRemoteBranch($hotfixBranch);

            if ($hasStash) {
                $this->stashApply();
            }

            return self::SUCCESS;
        }

        $io->text(sprintf('Hotfix %s remote branch does not exist. Creation..', $hotfixBranch));

        $this->checkoutToBranch($hotfixBranch, true);
        $io->text($this->commit(sprintf('[swk] Init hotfix %s. [skip ci]', $hotfixBranch), true));
        $this->push();

        if ($hasStash) {
            $this->stashApply();
        }

        return self::SUCCESS;
    }
}
