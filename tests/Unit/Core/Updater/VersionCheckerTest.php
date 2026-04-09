<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Updater;

use PHPUnit\Framework\TestCase;
use Walibuy\Sweeecli\Core\Gitlab\GitlabClient;

class VersionCheckerTest extends TestCase
{
    private GitlabClient $gitlabClient;
    private VersionChecker $versionChecker;

    protected function setUp(): void
    {
        $this->gitlabClient = $this->createMock(GitlabClient::class);
        $this->versionChecker = new VersionChecker($this->gitlabClient);
    }

    public function testGetCurrentVersionReturnsUnknownWhenFileDoesNotExist(): void
    {
        $result = $this->versionChecker->getCurrentVersion();

        $this->assertIsString($result);
    }

    public function testGetLastVersionReturnsTagFromGitlabClient(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willReturn('1.2.3');

        $result = $this->versionChecker->getLastVersion();

        $this->assertSame('1.2.3', $result);
    }

    public function testGetLastVersionFallsBackToCurrentVersionWhenGitlabClientReturnsNull(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willReturn(null);

        $result = $this->versionChecker->getLastVersion();

        $this->assertSame($this->versionChecker->getCurrentVersion(), $result);
    }

    public function testGetLastVersionFallsBackToCurrentVersionWhenGitlabClientThrowsException(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willThrowException(new \RuntimeException('Network error'));

        $result = $this->versionChecker->getLastVersion();

        $this->assertSame($this->versionChecker->getCurrentVersion(), $result);
    }

    public function testGetLastVersionFallsBackToCurrentVersionWhenGitlabClientThrowsThrowable(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willThrowException(new \Error('Fatal error'));

        $result = $this->versionChecker->getLastVersion();

        $this->assertSame($this->versionChecker->getCurrentVersion(), $result);
    }

    public function testIsUpdateAvailableReturnsTrueWhenVersionsDiffer(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willReturn('9.9.9');

        $result = $this->versionChecker->isUpdateAvailable();

        $this->assertTrue($result);
    }

    public function testIsUpdateAvailableReturnsFalseWhenVersionsAreIdentical(): void
    {
        $currentVersion = $this->versionChecker->getCurrentVersion();

        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willReturn($currentVersion);

        $result = $this->versionChecker->isUpdateAvailable();

        $this->assertFalse($result);
    }

    public function testIsUpdateAvailableReturnsFalseWhenGitlabClientThrowsAndVersionsMatch(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willThrowException(new \RuntimeException('Network error'));

        $result = $this->versionChecker->isUpdateAvailable();

        $this->assertFalse($result);
    }
}