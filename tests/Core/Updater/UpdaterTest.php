<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Tests\Core\Updater;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Walibuy\Sweeecli\Core\Gitlab\GitlabClient;
use Walibuy\Sweeecli\Core\Updater\Updater;
use Walibuy\Sweeecli\Core\Updater\VersionChecker;

class UpdaterTest extends TestCase
{
    private VersionChecker&MockObject $versionChecker;
    private GitlabClient&MockObject $gitlabClient;
    private Updater $updater;

    protected function setUp(): void
    {
        $this->versionChecker = $this->createMock(VersionChecker::class);
        $this->gitlabClient = $this->createMock(GitlabClient::class);

        $this->updater = new Updater(
            $this->versionChecker,
            $this->gitlabClient,
        );
    }

    public function testGetCurrentVersion(): void
    {
        $expectedVersion = '1.2.3';

        $this->versionChecker
            ->expects($this->once())
            ->method('getCurrentVersion')
            ->willReturn($expectedVersion);

        $result = $this->updater->getCurrentVersion();

        $this->assertSame($expectedVersion, $result);
    }

    public function testGetLastVersion(): void
    {
        $expectedVersion = '2.0.0';

        $this->versionChecker
            ->expects($this->once())
            ->method('getLastVersion')
            ->willReturn($expectedVersion);

        $result = $this->updater->getLastVersion();

        $this->assertSame($expectedVersion, $result);
    }

    public function testCheckUpdateReturnsTrue(): void
    {
        $this->versionChecker
            ->expects($this->once())
            ->method('isUpdateAvailable')
            ->willReturn(true);

        $result = $this->updater->checkUpdate();

        $this->assertTrue($result);
    }

    public function testCheckUpdateReturnsFalse(): void
    {
        $this->versionChecker
            ->expects($this->once())
            ->method('isUpdateAvailable')
            ->willReturn(false);

        $result = $this->updater->checkUpdate();

        $this->assertFalse($result);
    }

    public function testUpdateToLastVersionThrowsExceptionWhenDirPatternDoesNotMatch(): void
    {
        $platform = match (PHP_OS_FAMILY) {
            'Linux' => 'linux',
            'Darwin' => 'mac',
            default => null,
        };

        $architecture = null;
        try {
            $architecture = match (php_uname('m')) {
                'x86_64' => 'x64',
                'aarch64', 'arm64' => 'arm',
            };
        } catch (\UnhandledMatchError) {
            // architecture not supported
        }

        if (null === $platform || null === $architecture) {
            $this->expectException(\RuntimeException::class);
            $this->updater->updateToLastVersion();
            return;
        }

        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestPackageUrl')
            ->with($platform, $architecture)
            ->willReturn('https://example.com/swk.tar.gz');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to find project root directory');

        $this->updater->updateToLastVersion();
    }

    public function testUpdateToLastVersionCallsGitlabClientWithCorrectPlatformAndArchitecture(): void
    {
        $platform = match (PHP_OS_FAMILY) {
            'Linux' => 'linux',
            'Darwin' => 'mac',
            default => null,
        };

        $architecture = null;
        try {
            $architecture = match (php_uname('m')) {
                'x86_64' => 'x64',
                'aarch64', 'arm64' => 'arm',
            };
        } catch (\UnhandledMatchError) {
            // architecture not supported
        }

        if (null === $platform || null === $architecture) {
            $this->markTestSkipped('Unsupported platform or architecture for this test.');
        }

        $this->gitlabClient
            ->expects($this->once())
            ->method('getLatestPackageUrl')
            ->with($platform, $architecture)
            ->willReturn('https://example.com/swk.tar.gz');

        try {
            $this->updater->updateToLastVersion();
        } catch (\RuntimeException $e) {
            $this->assertSame('Unable to find project root directory', $e->getMessage());
        }
    }

    public function testUpdateToLastVersionThrowsExceptionOnUnsupportedPlatform(): void
    {
        if (!in_array(PHP_OS_FAMILY, ['Linux', 'Darwin', 'Windows'], true)) {
            $this->markTestSkipped('This test requires a specific OS family.');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unsupported platform');

            $this->gitlabClient
                ->expects($this->never())
                ->method('getLatestPackageUrl');

            $this->updater->updateToLastVersion();
        } else {
            $this->markTestSkipped('Test only relevant on unsupported platforms.');
        }
    }
}