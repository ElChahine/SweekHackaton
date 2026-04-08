<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Demo;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Command\Git\AbstractGitCommand;
use Walibuy\Sweeecli\Command\Git\Enum\RemoteType;

class DemoStartCommand extends AbstractGitCommand
{
    protected function configure(): void
    {
        $this
            ->setName('demo:start')
            ->setDescription('Start a demo branch')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->hasLocalChanges()) {
            $io->error('⚠️ Local changes, cannot start demo.');

            return self::FAILURE;
        }

        $this->checkoutToBranch();
        $this->fetch();
        $this->reset(true, 'master', RemoteType::MAIN);

        $productionTag = $this->getLatestVersionTag();

        if ($this->isBranchExistInLocal('demo/demo')) {
            $io->text('Local branch exist, deleting it..');
            $this->removeBranch('demo/demo');
        }

        if (!$this->isBranchExistInRemote('demo/demo')) {
            $io->text('Remote branch does not exist, creating it..');
            $this->checkoutToBranch('demo/demo', true);
            $this->commit('[swk] Init demo demo/demo. [skip ci]', true);
            $this->push();
        } else {
            $io->text('Remote branch exist, checking it out..');
            $this->checkoutToRemoteBranch('demo/demo');
            $demoTag = $this->getLatestVersionTag('demo/demo');

            $io->text(sprintf('Latest production tag: %s', $productionTag));
            $io->text(sprintf('Latest demo tag: %s', $demoTag));

            if (!$productionTag->isEquals($demoTag)) {
                $io->text('⚠️ Codebase is not sync with production');
                $io->comment(sprintf('please, execute: git merge --no-ff %s && git push %s demo/demo', $productionTag, $this->config->getMainRemoteName()));
            }
        }

        return self::SUCCESS;
    }
}
