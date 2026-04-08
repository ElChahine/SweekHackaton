<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Updater;

use Walibuy\Sweeecli\Core\Gitlab\GitlabClient;

class VersionChecker
{
    public function __construct(
        private GitlabClient $gitlabClient,
    ) {
    }

    public function getCurrentVersion(): string
    {
        return trim(@file_get_contents(__DIR__.'/../../../.app.version') ?: 'UNKNOWN');
    }

    public function getLastVersion(): string
    {
        try {
            return $this->gitlabClient->getLatestTag() ?: $this->getCurrentVersion();
        } catch (\Throwable) {
            return $this->getCurrentVersion();
        }
    }

    public function isUpdateAvailable(): bool
    {
        return $this->getLastVersion() !== $this->getCurrentVersion();
    }
}
