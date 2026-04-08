<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Feature;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Command\Git\AbstractGitCommand;
use Walibuy\Sweeecli\Command\Git\Enum\RemoteType;

class FeatureStartCommand extends AbstractGitCommand
{
    protected function configure(): void
    {
        $this
            ->setName('feature:start')
            ->addArgument('featureName', InputArgument::REQUIRED, 'The feature name')
            ->addOption('stash-changes', 's', InputOption::VALUE_NONE, 'Stash local changes before starting hotfix')
            ->setDescription('Start a new feature branch')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $hasStash = false;

        if ($this->hasLocalChanges()) {
            if (!$input->getOption('stash-changes') && !$io->askQuestion(new ConfirmationQuestion('⚠️ Local changes, Do you want to stash changes ?', true))) {
                $io->error('⚠️ Local changes, cannot start feature.');

                return self::FAILURE;
            }

            $this->add();
            $this->stash();
            $hasStash = true;
        }

        $featureBranch = sprintf('feature/%s', $input->getArgument('featureName'));

        if ($this->isBranchExistInLocal($featureBranch)) {
            $io->text(sprintf('Feature branch %s exist on local machine. Switch it..', $featureBranch));
            $this->checkoutToBranch($featureBranch);
        } elseif ($this->isBranchExistInRemote($featureBranch, RemoteType::FORK)) {
            $io->text(sprintf('Feature branch %s exist on fork remote. Switch it..', $featureBranch));
            $this->checkoutToRemoteBranch($featureBranch, RemoteType::FORK);
        } elseif ($this->isBranchExistInRemote($featureBranch)) {
            $io->text(sprintf('Feature branch %s exist on main remote. Switch it..', $featureBranch));
            $this->checkoutToRemoteBranch($featureBranch);
        } else {
            $io->text(sprintf('Feature branch %s does not exist. Creation..', $featureBranch));

            $io->text('Checkout to master');
            $this->fetch();
            $this->checkoutToBranch();
            $this->reset(true, 'master', RemoteType::MAIN);

            $io->text('Create new feature branch');
            $this->checkoutToBranch($featureBranch, true);

            $io->text('Push branch to remote');
            $io->text($this->commit(sprintf('[swk] Init feature %s. [skip ci]', $featureBranch), true));
            $this->push();
        }

        if ($hasStash) {
            $io->text('Apply stashed changes');
            $this->stashApply();
        }

        $io->success(sprintf('Feature %s started', $featureBranch));

        return self::SUCCESS;
    }
}
