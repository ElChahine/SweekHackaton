<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Walibuy\Sweeecli\Command\Cli\CheckUpdateCommand;
use Walibuy\Sweeecli\Command\Cli\InitConfigCommand;
use Walibuy\Sweeecli\Command\Cli\ViewConfigCommand;
use Walibuy\Sweeecli\Command\Documentation\OpenDocCommand;
use Walibuy\Sweeecli\Command\Env\GenerateEnvFileCommand;
use Walibuy\Sweeecli\Command\Env\HelmVariableArgumentCommand;
use Walibuy\Sweeecli\Command\Env\InitEnvSystemCommand;
use Walibuy\Sweeecli\Command\Git\Demo\DemoMergeFeatureCommand;
use Walibuy\Sweeecli\Command\Git\Demo\DemoStartCommand;
use Walibuy\Sweeecli\Command\Git\Feature\FeaturePushCommand;
use Walibuy\Sweeecli\Command\Git\Feature\FeatureStartCommand;
use Walibuy\Sweeecli\Command\Git\Hotfix\HotfixAbortCommand;
use Walibuy\Sweeecli\Command\Git\Hotfix\HotfixFinishCommand;
use Walibuy\Sweeecli\Command\Git\Hotfix\HotfixMergeCommand;
use Walibuy\Sweeecli\Command\Git\Hotfix\HotfixStartCommand;
use Walibuy\Sweeecli\Command\Project\RetrieveDatabaseDumpCommand;
use Walibuy\Sweeecli\Command\ReverseProxy\DoctorReverseProxyCommand;
use Walibuy\Sweeecli\Command\ReverseProxy\InstallReverseProxyCommand;
use Walibuy\Sweeecli\Command\ReverseProxy\MigrateReverseProxyCommand;
use Walibuy\Sweeecli\Command\ReverseProxy\StartNgrokReverseProxyCommand;
use Walibuy\Sweeecli\Command\ReverseProxy\StartReverseProxyCommand;
use Walibuy\Sweeecli\Command\ReverseProxy\StopReverseProxyCommand;
use Walibuy\Sweeecli\Command\ReverseProxy\UninstallReverseProxyCommand;
use Walibuy\Sweeecli\Command\ReverseProxy\UpdateReverseProxyCommand;
use Walibuy\Sweeecli\Command\Ai\CodeReviewCommand;
use Walibuy\Sweeecli\Command\Ai\DocumentationGenerateCommand;
use Walibuy\Sweeecli\Command\Ai\TestGenerateCommand;
use Walibuy\Sweeecli\Core\AbstractKernel;

class Kernel extends AbstractKernel
{
    protected function getName(): string
    {
        return 'swk';
    }

    protected function getCommands(): iterable
    {
        yield from $this->getCliCommands();
        yield new OpenDocCommand();
        yield from $this->getEnvCommands();
        yield from $this->getGitCommands();
        yield new RetrieveDatabaseDumpCommand();
        yield from $this->getReverseProxyCommands();
        yield new CodeReviewCommand();
        yield new DocumentationGenerateCommand();
        yield new TestGenerateCommand();
    }

    private function getCliCommands(): iterable
    {
        yield new CheckUpdateCommand($this->versionUpdater);
        yield new InitConfigCommand($this->configurationManager);
        yield new ViewConfigCommand($this->configurationManager);
    }

    private function getEnvCommands(): iterable
    {
        yield new GenerateEnvFileCommand();
        yield new InitEnvSystemCommand();
        yield new HelmVariableArgumentCommand();
    }

    private function getGitCommands(): iterable
    {
        $cliCache = new ArrayAdapter();
        yield new HotfixStartCommand($this->configurationManager, $cliCache);
        yield new HotfixMergeCommand($this->configurationManager, $cliCache);
        yield new HotfixFinishCommand($this->configurationManager, $cliCache);
        yield new HotfixAbortCommand($this->configurationManager, $cliCache);
        yield new FeatureStartCommand($this->configurationManager, $cliCache);
        yield new FeaturePushCommand($this->configurationManager, $cliCache);
        yield new DemoStartCommand($this->configurationManager, $cliCache);
        yield new DemoMergeFeatureCommand($this->configurationManager, $cliCache);
    }

    private function getReverseProxyCommands(): iterable
    {
        yield new DoctorReverseProxyCommand($this->projectManager);
        yield new InstallReverseProxyCommand($this->projectManager);
        yield new MigrateReverseProxyCommand($this->projectManager);
        yield new StartNgrokReverseProxyCommand($this->projectManager);
        yield new StartReverseProxyCommand($this->projectManager);
        yield new StopReverseProxyCommand($this->projectManager);
        yield new UninstallReverseProxyCommand($this->projectManager);
        yield new UpdateReverseProxyCommand($this->projectManager);

    }

    private function getAiCommands(): iterable
    {
        yield new CodeReviewCommand();
        yield new DocumentationGenerateCommand();
        yield new TestGenerateCommand();
    }
}
