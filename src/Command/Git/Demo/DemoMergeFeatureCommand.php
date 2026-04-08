<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Demo;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Command\Git\AbstractGitCommand;
use Walibuy\Sweeecli\Command\Git\Enum\RemoteType;

class DemoMergeFeatureCommand extends AbstractGitCommand
{
    protected function configure(): void
    {
        $this
            ->setName('demo:merge-feature')
            ->addArgument('featureName', InputArgument::REQUIRED, 'The name of the feature to merge in demo')
            ->setDescription('Merge feature into demo branch')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $hasStash = false;

        if ($this->hasLocalChanges()) {
            if (!$io->askQuestion(new ConfirmationQuestion('⚠️ Local changes, Do you want to stash changes ?', true))) {
                $io->error('⚠️ Local changes, cannot merge feature into demo.');

                return self::FAILURE;
            }

            $this->add();
            $this->stash();
            $hasStash = true;
        }

        if (self::SUCCESS !== $this->getApplication()->doRun(new ArrayInput(['command' => 'git:demo:start']), $output)) {
            $io->error('Error on switching to demo branch.');

            return self::FAILURE;
        }

        $featureBranch = sprintf('feature/%s', $input->getArgument('featureName'));

        if ($this->isBranchExistInLocal($featureBranch)) {
            $io->text(sprintf('Feature branch %s exist on local machine. Use it..', $featureBranch));
            $this->mergeBranch($featureBranch, null, sprintf('Merge feature branch: %s', $featureBranch));
        } elseif ($this->isBranchExistInRemote($featureBranch)) {
            $io->text(sprintf('Feature branch %s exist on remote. Use it..', $featureBranch));
            $this->mergeBranch($featureBranch, RemoteType::MAIN, sprintf('Merge feature branch: %s', $featureBranch));
        } else {
            $io->text('⚠️ Feature branch was not found');

            return self::FAILURE;
        }

        $this->push();

        if ($hasStash) {
            $io->text('Apply stashed changes');
            $this->stashApply();
        }

        $io->success('Feature successfully merged in demo.');

        return self::SUCCESS;
    }
}
