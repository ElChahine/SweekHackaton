<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Hotfix;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Command\Git\AbstractGitCommand;
use Walibuy\Sweeecli\Command\Git\Enum\RemoteType;
use Walibuy\Sweeecli\Command\Git\Helper\VersionTag;

class HotfixFinishCommand extends AbstractGitCommand
{
    protected function configure(): void
    {
        $this
            ->setName('hotfix:finish')
            ->setDescription('Finish the hotfix branch and merge into master')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->hasLocalChanges()) {
            $io->error('⚠️ Local changes, cannot finish hotfix.');

            return self::FAILURE;
        }

        $newTag = $this->getLatestVersionTag();
        $newTag->incrementMinor();
        $hotfixBranch = sprintf('hotfix/%s', $newTag);

        $this->checkoutToBranch($hotfixBranch);

        $branchCheck = $this->ensureOnValidHotfixBranch($io, $output, $hotfixBranch);
        if (null !== $branchCheck) {
            return $branchCheck;
        }

        if ($this->isHotfixBranchEmpty()) {
            return $this->handleEmptyHotfixBranch($io, $output);
        }

        $this->mergeHotfixToMaster($io, $hotfixBranch, $newTag);
        $this->cleanupHotfixBranches($io, $hotfixBranch);

        $io->success('Hotfix successfully finished.');

        return self::SUCCESS;
    }

    private function ensureOnValidHotfixBranch(SymfonyStyle $io, OutputInterface $output, string $hotfixBranch): ?int
    {
        $currentBranch = $this->getBranchName();

        if (!str_starts_with($currentBranch, 'hotfix/')) {
            $io->error(sprintf('You must be on a hotfix branch (%s).', $hotfixBranch));
            if ($io->askQuestion(new ConfirmationQuestion('Do you want to switch to hotfix branch ?', false))) {
                return $this->runHotfixStartCommand($output) ? null : self::FAILURE;
            }

            return self::FAILURE;
        }

        if ($currentBranch !== $hotfixBranch) {
            $io->error('Your hotfix branch is outdated. Please update with hotfix:start command.');
            if ($io->askQuestion(new ConfirmationQuestion('Launch hotfix:start command ?', false))) {
                return $this->runHotfixStartCommand($output) ? self::SUCCESS : self::FAILURE;
            }

            return self::FAILURE;
        }

        return null;
    }

    private function isHotfixBranchEmpty(): bool
    {
        return str_contains($this->getLastCommitMessage(), '[swk] Init hotfix ');
    }

    private function handleEmptyHotfixBranch(SymfonyStyle $io, OutputInterface $output): int
    {
        $io->warning('Hotfix branch seems to be empty.');
        if (!$io->askQuestion(new ConfirmationQuestion('Do you want to abort hotfix ?', false))) {
            return self::FAILURE;
        }

        return $this->runHotfixAbortCommand($output) ? self::SUCCESS : self::FAILURE;
    }

    private function runHotfixStartCommand(OutputInterface $output): bool
    {
        $exitCode = $this->getApplication()->doRun(new ArrayInput(['command' => 'git:hotfix:start']), $output);
        if (self::SUCCESS !== $exitCode) {
            return false;
        }

        return true;
    }

    private function runHotfixAbortCommand(OutputInterface $output): bool
    {
        return self::SUCCESS === $this->getApplication()->doRun(new ArrayInput(['command' => 'git:hotfix:abort']), $output);
    }

    private function mergeHotfixToMaster(SymfonyStyle $io, string $hotfixBranch, VersionTag $newTag): void
    {
        $io->text(sprintf('Closing hotfix branch %s', $hotfixBranch));
        $io->info('Future tag: '.$newTag);

        $this->checkoutToBranch();
        $this->fetch();
        $this->reset(true, 'master', RemoteType::MAIN);

        $io->text(sprintf('Merging hotfix branch %s into master', $hotfixBranch));
        $this->mergeBranch($hotfixBranch, null, sprintf('Merge hotfix branch: %s', $hotfixBranch));

        $io->text(sprintf('Create new tag %s', $newTag));
        $this->tag((string) $newTag);

        $io->text('Push changes and tag to master');
        $this->push();
        $this->pushTag((string) $newTag);
    }

    private function cleanupHotfixBranches(SymfonyStyle $io, string $hotfixBranch): void
    {
        $io->text(sprintf('Clean local branch %s', $hotfixBranch));
        $this->removeBranch($hotfixBranch);

        $io->text(sprintf('Clean remote branch %s', $hotfixBranch));
        $this->removeBranch($hotfixBranch, RemoteType::MAIN);
    }
}
