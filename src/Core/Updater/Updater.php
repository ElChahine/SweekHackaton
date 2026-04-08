<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Updater;

use Symfony\Component\Filesystem\Filesystem;
use Walibuy\Sweeecli\Core\Gitlab\GitlabClient;

class Updater
{
    private const ARCHIVE_NAME = 'swk.tar.gz';

    public function __construct(
        private VersionChecker $versionChecker,
        private GitlabClient $gitlabClient,
    ) {
    }

    public function getCurrentVersion(): string
    {
        return $this->versionChecker->getCurrentVersion();
    }

    public function getLastVersion(): string
    {
        return $this->versionChecker->getLastVersion();
    }

    public function checkUpdate(): bool
    {
        return $this->versionChecker->isUpdateAvailable();
    }

    public function updateToLastVersion(): void
    {
        $platform = match (PHP_OS_FAMILY) {
            'Linux' => 'linux',
            'Darwin' => 'mac',
            default => null,
        };
        $architecture = match (php_uname('m')) {
            'x86_64' => 'x64',
            'aarch64', 'arm64' => 'arm',
        };

        if (null === $platform || null === $architecture) {
            throw new \RuntimeException('Unsupported platform');
        }

        $url = $this->gitlabClient->getLatestPackageUrl($platform, $architecture);

        if (!preg_match('/^phar:\/\/(.+)\/src/', __DIR__, $matches)) {
            throw new \RuntimeException('Unable to find project root directory');
        }

        $targetPath = $matches[1];
        $filesystem = new Filesystem();

        $filesystem->copy($url, self::ARCHIVE_NAME, true);
        exec('tar -xzf '.self::ARCHIVE_NAME);
        $filesystem->remove(self::ARCHIVE_NAME);

        exec('chmod +x swk');
        exec(sprintf('sudo mv swk %s', escapeshellarg($targetPath)));
    }
}
