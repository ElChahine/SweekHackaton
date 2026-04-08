<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Hotfix;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Command\Git\AbstractGitCommand;
use Walibuy\Sweeecli\Command\Git\Enum\RemoteType;

class HotfixMergeCommand extends AbstractGitCommand
{
    protected function configure(): void
    {
        $this
            ->setName('hotfix:merge')
            ->addArgument('featureBranch', InputArgument::REQUIRED, 'The feature branch to merge in hotfix')
            ->setDescription('Merge feature to hotfix')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->hasLocalChanges()) {
            $io->error('⚠️ Local changes, cannot merge feature into hotfix.');

            return self::FAILURE;
        }

        if (self::SUCCESS !== $this->getApplication()->doRun(new ArrayInput(['command' => 'git:hotfix:start']), $output)) {
            $io->error('Error on hotfix start.');

            return self::FAILURE;
        }

        $featureBranch = sprintf('feature/%s', $input->getArgument('featureBranch'));

        $io->text('Search a branch named: '.$featureBranch);

        if ($this->isBranchExistInLocal($featureBranch)) {
            $io->text('Feature branch exist on local machine, merging to hotfix');
            $this->mergeBranch($featureBranch, null, sprintf('Merge feature branch: %s', $featureBranch));
        } elseif ($this->isBranchExistInRemote($featureBranch)) {
            $io->text('Feature branch exist on remote, merging to hotfix');
            $this->mergeBranch($featureBranch, RemoteType::MAIN, sprintf('Merge feature branch: %s', $featureBranch));
        } else {
            $io->error('⚠️ Feature branch was not found.');

            return self::FAILURE;
        }

        $this->push();

        return self::SUCCESS;
    }
}
