<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Walibuy\Sweeecli\Core\Configuration\ConfigurationManager;
use Walibuy\Sweeecli\Core\Configuration\DefinitionBuilder;
use Walibuy\Sweeecli\Core\Configuration\ProjectManager;
use Walibuy\Sweeecli\Core\Gitlab\GitlabClient;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesAwareCommandInterface;
use Walibuy\Sweeecli\Core\Updater\Updater;
use Walibuy\Sweeecli\Core\Updater\VersionChecker;

use Symfony\Component\HttpClient\HttpClient;
use Walibuy\Sweeecli\Core\Ai\ClaudeClient;

abstract class AbstractKernel
{
    private const CACHE_KEY_UPDATE_VERSION = 'update_version';
    private const CACHE_TTL_ONE_DAY = 60 * 24;

    protected Application $application;
    protected Updater $versionUpdater;
    protected CacheInterface $cache;
    protected ConfigurationManager $configurationManager;
    protected ProjectManager $projectManager;

    public function __construct()
    {
        $this->configurationManager = new ConfigurationManager(new DefinitionBuilder());
        $this->projectManager = new ProjectManager();
        $gitlabClient = new GitlabClient();
        $this->cache = new FilesystemAdapter();
        $this->application = new Application();
        $this->versionUpdater = new Updater(
            new VersionChecker($gitlabClient),
            $gitlabClient
        );
        $this->claudeClient = new ClaudeClient(
            HttpClient::create(),
            $_ENV['CLAUDE_API_KEY'] ?? getenv('CLAUDE_API_KEY') ?? ''
);
    }

    abstract protected function getCommands(): iterable;

    protected function getName(): string
    {
        return 'UNKNOWN';
    }

    protected function getVersion(): string
    {
        return $this->versionUpdater->getCurrentVersion();
    }

    public function initialize(): void
    {
        $this->configureConsole();
        $this->checkUpdate();
    }

    protected function configureConsole(): void
    {
        $this->application->setName($this->getName());
        $this->application->setVersion($this->getVersion());
        $this->application->getDefinition()->addOption(new InputOption('disable-update-checking', 'd', InputOption::VALUE_NONE, 'Disable update checking'));

        $this->registerCommands();
    }

    public function checkUpdate(): void
    {
        $input = new ArgvInput();

        if ($input->hasParameterOption(['--disable-update-checking', '-d'])) {
            return;
        }

        $updated = $this->cache->get(self::CACHE_KEY_UPDATE_VERSION, function (ItemInterface $item) use ($input) {
            $item->expiresAfter(self::CACHE_TTL_ONE_DAY);

            if (!$this->versionUpdater->checkUpdate()) {
                return false;
            }

            $io = new SymfonyStyle($input, new ConsoleOutput());

            if ($io->askQuestion(new ConfirmationQuestion('A new version is available, do you want to update?', true))) {
                try {
                    $this->versionUpdater->updateToLastVersion();
                } catch (\Throwable $e) {
                    $io->error($e->getMessage());
                    $io->warning('Update failed, please try again later.');
                }

                return true;
            }

            return false;
        });

        if ($updated) {
            $this->cache->delete(self::CACHE_KEY_UPDATE_VERSION);
            exit;
        }
    }

    protected function registerCommands(): void
    {
        foreach ($this->getCommands() as $command) {
            if (
                $command instanceof PrerequisitesAwareCommandInterface
                && !$command->getPrerequisites()->checkPrerequisites()
            ) {
                continue;
            }

            $this->application->addCommand($command);
        }
    }

    public function run(): void
    {
        $this->application->run();
    }
}
