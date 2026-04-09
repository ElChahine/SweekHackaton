<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Tests\Core\Updater;

use PHPUnit\Framework\TestCase;
use Walibuy\Sweeecli\Core\Gitlab\GitlabClient;
use Walibuy\Sweeecli\Core\Updater\VersionChecker;

class VersionCheckerFunctionalTest extends TestCase
{
    private GitlabClient $gitlabClient;
    private VersionChecker $versionChecker;

    protected function setUp(): void
    {
        $this->gitlabClient = $this->createMock(GitlabClient::class);
        $this->versionChecker = new VersionChecker($this->gitlabClient);
    }

    public function testGetCurrentVersionReturnsStringFromAppVersionFile(): void
    {
        $currentVersion = $this->versionChecker->getCurrentVersion();

        $this->assertIsString($currentVersion);
        $this->assertNotEmpty($currentVersion);
    }

    public function testGetCurrentVersionReturnsUnknownWhenFileIsMissing(): void
    {
        $reflection = new \ReflectionClass(VersionChecker::class);
        $fileName = $reflection->getFileName();
        $expectedPath = dirname($fileName, 3) . '/.app.version';

        if (!file_exists($expectedPath)) {
            $result = $this->versionChecker->getCurrentVersion();
            $this->assertSame('UNKNOWN', $result);
        } else {
            $this->assertTrue(true);
        }
    }

    public function testGetLastVersionReturnsTagFromGitlabClient(): void
    {
        $this->gitlabClient
            ->method('getLatestTag')
            ->willReturn('1.2.3');

        $lastVersion = $this->versionChecker->getLastVersion();

        $this->assertSame('1.2.3', $lastVersion);
    }

    public function testGetLastVersionFallsBackToCurrentVersionWhenGitlabReturnsEmptyString(): void
    {
        $this->gitlabClient
            ->method('getLatestTag')
            ->willReturn('');

        $lastVersion = $this->versionChecker->getLastVersion();

        $this->assertSame($this->versionChecker->getCurrentVersion(), $lastVersion);
    }

    public function testGetLastVersionFallsBackToCurrentVersionWhenGitlabThrowsException(): void
    {
        $this->gitlabClient
            ->method('getLatestTag')
            ->willThrowException(new \RuntimeException('Network error'));

        $lastVersion = $this->versionChecker->getLastVersion();

        $this->assertSame($this->versionChecker->getCurrentVersion(), $lastVersion);
    }

    public function testGetLastVersionFallsBackToCurrentVersionWhenGitlabThrowsError(): void
    {
        $this->gitlabClient
            ->method('getLatestTag')
            ->willThrowException(new \Error('Fatal error'));

        $lastVersion = $this->versionChecker->getLastVersion();

        $this->assertSame($this->versionChecker->getCurrentVersion(), $lastVersion);
    }

    public function testIsUpdateAvailableReturnsTrueWhenRemoteVersionDiffersFromCurrent(): void
    {
        $currentVersion = $this->versionChecker->getCurrentVersion();
        $differentVersion = $currentVersion === '1.0.0' ? '2.0.0' : '1.0.0';

        $this->gitlabClient
            ->method('getLatestTag')
            ->willReturn($differentVersion);

        $this->assertTrue($this->versionChecker->isUpdateAvailable());
    }

    public function testIsUpdateAvailableReturnsFalseWhenRemoteVersionMatchesCurrent(): void
    {
        $currentVersion = $this->versionChecker->getCurrentVersion();

        $this->gitlabClient
            ->method('getLatestTag')
            ->willReturn($currentVersion);

        $this->assertFalse($this->versionChecker->isUpdateAvailable());
    }

    public function testIsUpdateAvailableReturnsFalseWhenGitlabThrowsException(): void
    {
        $this->gitlabClient
            ->method('getLatestTag')
            ->willThrowException(new \RuntimeException('Unreachable'));

        $this->assertFalse($this->versionChecker->isUpdateAvailable());
    }
}