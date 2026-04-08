<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Feature;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Command\Git\AbstractGitCommand;
use Walibuy\Sweeecli\Command\Git\Enum\RemoteType;

class FeaturePushCommand extends AbstractGitCommand
{
    protected function configure(): void
    {
        $this
            ->setName('feature:push')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force push')
            ->setDescription('[EXPERIMENTAL] Push feature commits to remote')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $featureBranch = $this->getBranchName();

        if (!preg_match('/^feature\//', $featureBranch)) {
            $io->error('⚠️ Not in feature branch, cannot continue.');

            return self::FAILURE;
        }

        if ($this->hasLocalChanges()) {
            if (!$io->askQuestion(new ConfirmationQuestion('⚠️ Local changes, Do you want to commit changes ?', true))) {
                $io->error('⚠️ Local changes, cannot continue.');

                return self::FAILURE;
            }

            do {
                $message = $io->ask('Please enter your commit message', null);
            } while (null === $message || empty(trim($message)));

            $this->add();
            $this->commit($message);
        }

        $pushOutput = $this->push('HEAD', RemoteType::FORK, $input->getOption('force'));
        $io->text($pushOutput);

        if (preg_match('/(https:\/\/gitlab\.com\/.+\/merge_requests\/new.+)/', $pushOutput, $matches)) {
            if (!$io->askQuestion(new ConfirmationQuestion('Do you want to open a merge request ?', true))) {
                return self::SUCCESS;
            }

            $type = $io->askQuestion(new ChoiceQuestion(
                'Which kind of feature have you coded ?',
                [
                    'new feature',
                    'bug fix',
                    'refactoring',
                    'documentation',
                    'other',
                ],
                'other'
            ));
            $prefix = match ($type) {
                'new feature' => ':construction: ',
                'bug fix' => ':bug: ',
                'refactoring' => ':recycle: ',
                'documentation' => ':memo: ',
                default => '',
            };

            $url = trim($matches[1]);

            $params = http_build_query([
                'merge_request[title]' => $prefix.$this->getLastCommitMessage(),
                'merge_request[description]' => '/labels ~RFR',
            ]);
            $url .= '&'.$params;

            $io->text('Open '.$url);
            exec(sprintf('open "%s"', $url));
        }

        return self::SUCCESS;
    }
}
