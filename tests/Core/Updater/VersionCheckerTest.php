<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Tests\Core\Updater;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Walibuy\Sweeecli\Core\Gitlab\GitlabClient;
use Walibuy\Sweeecli\Core\Updater\VersionChecker;

class VersionCheckerTest extends TestCase
{
    private GitlabClient&MockObject $gitlabClient;
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

    public function testGetLastVersionReturnsGitlabTagWhenAvailable(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willReturn('1.2.3');

        $result = $this->versionChecker->getLastVersion();

        $this->assertSame('1.2.3', $result);
    }

    public function testGetLastVersionReturnsCurrentVersionWhenGitlabTagIsEmpty(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willReturn('');

        $currentVersion = $this->versionChecker->getCurrentVersion();
        $result = $this->versionChecker->getLastVersion();

        $this->assertSame($currentVersion, $result);
    }

    public function testGetLastVersionReturnsCurrentVersionWhenGitlabTagIsNull(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willReturn(null);

        $currentVersion = $this->versionChecker->getCurrentVersion();
        $result = $this->versionChecker->getLastVersion();

        $this->assertSame($currentVersion, $result);
    }

    public function testGetLastVersionReturnsCurrentVersionWhenGitlabClientThrowsException(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willThrowException(new \RuntimeException('Network error'));

        $currentVersion = $this->versionChecker->getCurrentVersion();
        $result = $this->versionChecker->getLastVersion();

        $this->assertSame($currentVersion, $result);
    }

    public function testGetLastVersionReturnsCurrentVersionWhenGitlabClientThrowsError(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willThrowException(new \Error('Fatal error'));

        $currentVersion = $this->versionChecker->getCurrentVersion();
        $result = $this->versionChecker->getLastVersion();

        $this->assertSame($currentVersion, $result);
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

    public function testIsUpdateAvailableReturnsFalseWhenVersionsAreEqual(): void
    {
        $currentVersion = $this->versionChecker->getCurrentVersion();

        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willReturn($currentVersion);

        $result = $this->versionChecker->isUpdateAvailable();

        $this->assertFalse($result);
    }

    public function testIsUpdateAvailableReturnsFalseWhenGitlabClientThrowsException(): void
    {
        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestTag')
            ->willThrowException(new \RuntimeException('Network error'));

        $result = $this->versionChecker->isUpdateAvailable();

        $this->assertFalse($result);
    }
}