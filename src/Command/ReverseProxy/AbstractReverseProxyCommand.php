<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\ReverseProxy;

use Symfony\Component\Console\Command\Command;
use Walibuy\Sweeecli\Core\Configuration\Project\SwkProxy;
use Walibuy\Sweeecli\Core\Configuration\ProjectManager;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesAwareCommandInterface;
use Walibuy\Sweeecli\Core\Prerequisites\PrerequisitesConfiguration;

class AbstractReverseProxyCommand extends Command implements PrerequisitesAwareCommandInterface
{
    public function __construct(
        protected ProjectManager $projectManager,
    ) {
        parent::__construct();
    }

    protected function buildSwkProxyCommand(string $command): array
    {
        $project = $this->projectManager->getProject(SwkProxy::class);

        return ['make', '-C', $this->projectManager->getProjectPath($project), 'FROM=sweeecli', $command];
    }

    public function getPrerequisites(): PrerequisitesConfiguration
    {
        return (new PrerequisitesConfiguration())
            ->hasProject($this->projectManager->getProject(SwkProxy::class))
        ;
    }
}
