<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Helper;

use Walibuy\Sweeecli\Command\Git\Enum\RemoteType;
use Walibuy\Sweeecli\Core\Configuration\ConfigurationManager;

class GitConfig
{
    public function __construct(
        private ConfigurationManager $configurationManager,
    ) {
    }

    public function getConfig(): array
    {
        return $this->configurationManager->getConfiguration()['git'];
    }

    public function getMainRemoteName(): string
    {
        return $this->getConfig()['main_remote'];
    }

    public function getForkRemoteName(): string
    {
        return $this->getConfig()['fork_remote'];
    }

    public function getRemoteBranch(RemoteType $remoteType): string
    {
        return match ($remoteType) {
            RemoteType::MAIN => $this->getMainRemoteName(),
            RemoteType::FORK => $this->getForkRemoteName(),
        };
    }
}
