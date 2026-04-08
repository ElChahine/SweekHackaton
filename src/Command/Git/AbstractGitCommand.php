<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Walibuy\Sweeecli\Command\Git\Enum\RemoteType;
use Walibuy\Sweeecli\Command\Git\Helper\GitConfig;
use Walibuy\Sweeecli\Command\Git\Helper\VersionTag;
use Walibuy\Sweeecli\Core\Configuration\ConfigurationManager;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesAwareCommandInterface;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesConfiguration;

abstract class AbstractGitCommand extends Command implements PrerequisitesAwareCommandInterface
{
    protected GitConfig $config;

    public function __construct(
        ConfigurationManager $configurationManager,
        private ArrayAdapter $cache,
    ) {
        $this->config = new GitConfig($configurationManager);
        parent::__construct();
    }

    public function setName(string $name): static
    {
        return parent::setName('git:'.$name);
    }

    public function getPrerequisites(): PrerequisitesConfiguration
    {
        // Check if we are in a git project and git is installed
        return (new PrerequisitesConfiguration())
            ->addCustomValidator(function () {
                return $this->cache->get('has_git', function () {
                    return Command::SUCCESS === Process::fromShellCommandline('git rev-parse --is-inside-work-tree')->run();
                });
            })
        ;
    }

    abstract protected function doExecute(InputInterface $input, OutputInterface $output): int;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            return $this->doExecute($input, $output);
        } catch (ProcessFailedException $e) {
            $io->text(sprintf('Error on command %s', $e->getProcess()->getCommandLine()));
            $io->error(implode(
                PHP_EOL,
                [
                    $e->getProcess()->getOutput(),
                    $e->getProcess()->getErrorOutput(),
                ]
            ));

            return self::FAILURE;
        }
    }

    protected function checkoutToBranch(string $branch = 'master', bool $create = false): void
    {
        if ($create) {
            (new Process(['git', 'checkout', '-b', $branch]))->mustRun();
        } else {
            (new Process(['git', 'checkout', $branch]))->mustRun();
        }
    }

    protected function removeBranch(string $branch, ?RemoteType $remoteType = null): void
    {
        if (null !== $remoteType) {
            (new Process(['git', 'push', $this->config->getRemoteBranch($remoteType), '--delete', $branch]))->mustRun();
        } else {
            (new Process(['git', 'branch', '-D', $branch]))->mustRun();
        }
    }

    protected function checkoutToRemoteBranch(string $branch = 'master', RemoteType $remoteType = RemoteType::MAIN): void
    {
        (new Process(['git', 'checkout', '--track', $this->config->getRemoteBranch($remoteType).'/'.$branch]))->mustRun();
    }

    protected function mergeBranch(string $branch, ?RemoteType $remoteType, ?string $message = null): void
    {
        if (null !== $remoteType) {
            $branch = $this->config->getRemoteBranch($remoteType).'/'.$branch;
        }

        if ($message) {
            (new Process(['git', 'merge', '--no-ff', $branch, '-m', $message]))->mustRun();
        } else {
            (new Process(['git', 'merge', '--no-ff', $branch]))->mustRun();
        }
    }

    protected function getBranchName(): string
    {
        $process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        $process->mustRun();

        return trim($process->getOutput());
    }

    protected function tag(string $name): void
    {
        (new Process(['git', 'tag', $name]))->mustRun();
    }

    protected function add(string $path = '.'): void
    {
        (new Process(['git', 'add', $path]))->mustRun();
    }

    protected function stash(): void
    {
        (new Process(['git', 'stash']))->mustRun();
    }

    protected function stashApply(): void
    {
        (new Process(['git', 'stash', 'apply']))->mustRun();
    }

    protected function commit(string $message, bool $allowEmpty = false): string
    {
        $process = match (true) {
            $allowEmpty => new Process(['git', 'commit', '-m', $message, '--allow-empty']),
            default => new Process(['git', 'commit', '-m', $message]),
        };

        $process->mustRun();

        return trim($process->getOutput());
    }

    protected function getLastCommitMessage(): string
    {
        $process = new Process(['git', 'log', '-1', '--pretty=%B']);
        $process->mustRun();

        return trim($process->getOutput());
    }

    protected function reset(bool $hard = false, ?string $branch = null, ?RemoteType $remoteType = null): void
    {
        if (null !== $remoteType) {
            $branch = $this->config->getRemoteBranch($remoteType).'/'.$branch;
        }

        if ($hard) {
            (new Process(['git', 'reset', '--hard', $branch ?? 'HEAD']))->mustRun();
        } else {
            (new Process(['git', 'reset', $branch ?? 'HEAD']))->mustRun();
        }
    }

    protected function fetch(RemoteType $remoteType = RemoteType::MAIN): void
    {
        (new Process(['git', 'fetch', $this->config->getRemoteBranch($remoteType)]))->mustRun();
    }

    protected function push(string $branchName = 'HEAD', RemoteType $remoteType = RemoteType::MAIN, bool $force = false): string
    {
        $process = new Process(['git', 'push', $this->config->getRemoteBranch($remoteType), $branchName, ...($force ? ['--force'] : [])]);
        $process->mustRun();

        return trim($process->getOutput()) ?: trim($process->getErrorOutput());
    }

    protected function pushTag(string $tag, RemoteType $remoteType = RemoteType::MAIN): void
    {
        (new Process(['git', 'push', $this->config->getRemoteBranch($remoteType), 'tag', $tag]))->mustRun();
    }

    protected function hasLocalChanges(): bool
    {
        $process = new Process(['git', 'status', '--porcelain']);
        $process->mustRun();

        return '' !== trim($process->getOutput());
    }

    protected function getLatestVersionTag(string $branch = 'master'): ?VersionTag
    {
        $process = new Process(['git', 'tag', '--sort=creatordate', '--merged', $branch]);
        $process->mustRun();
        $tags = explode("\n", $process->getOutput());

        foreach (array_reverse($tags) as $tag) {
            if (VersionTag::isValid($tag)) {
                return new VersionTag($tag);
            }
        }

        return new VersionTag('0.0.0');
    }

    protected function isBranchExistInLocal(string $branchName): bool
    {
        $process = new Process(['git', 'branch', '--list', $branchName]);
        $process->mustRun();

        return '' !== trim($process->getOutput());
    }

    protected function isBranchExistInRemote(string $branchName, RemoteType $remoteType = RemoteType::MAIN): bool
    {
        $process = new Process(['git', 'ls-remote', '--heads', $this->config->getRemoteBranch($remoteType), $branchName]);
        $process->mustRun();

        return '' !== trim($process->getOutput());
    }
}
