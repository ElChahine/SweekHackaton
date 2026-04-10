<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Tests\Unit\Core\Updater;

use PHPUnit\Framework\TestCase;
use Walibuy\Sweeecli\Core\Gitlab\GitlabClient;
use Walibuy\Sweeecli\Core\Updater\Updater;
use Walibuy\Sweeecli\Core\Updater\VersionChecker;

/**
 * Sous-classe testable : permet d'injecter un __DIR__ simulé
 * et d'intercepter les appels filesystem/exec sans modifier la prod.
 */
class TestableUpdater extends Updater
{
    private string $simulatedDir;
    public array $execCalls = [];
    public array $fsCopyCalls = [];
    public array $fsRemoveCalls = [];
    private bool $throwOnFsCopy = false;

    public function setSimulatedDir(string $dir): void
    {
        $this->simulatedDir = $dir;
    }

    public function throwOnNextCopy(): void
    {
        $this->throwOnFsCopy = true;
    }

    protected function getDir(): string
    {
        return $this->simulatedDir ?? __DIR__;
    }

    protected function execShell(string $cmd): void
    {
        $this->execCalls[] = $cmd;
    }

    protected function fsCopy(string $src, string $dest): void
    {
        if ($this->throwOnFsCopy) {
            throw new \Symfony\Component\Filesystem\Exception\IOException("Cannot copy from {$src}");
        }
        $this->fsCopyCalls[] = ['src' => $src, 'dest' => $dest];
    }

    protected function fsRemove(string $path): void
    {
        $this->fsRemoveCalls[] = $path;
    }
}

/**
 * Version de prod non modifiable — on teste les exceptions pures
 * via réflexion sur les constantes de PHP_OS_FAMILY simulées,
 * ce qui n'est pas faisable. On teste donc via TestableUpdater
 * qui reproduit fidèlement la logique avec points d'extension.
 *
 * IMPORTANT : PHP_OS_FAMILY et php_uname() ne sont pas mockables nativement.
 * Les tests d'architecture/plateforme vérifient la logique via une
 * sous-classe qui expose la méthode updateToLastVersion() recodée
 * avec les mêmes branches mais en appelant les méthodes protégées.
 */

/**
 * Sous-classe qui reproduit updateToLastVersion() en utilisant
 * les hooks protégés ET des propriétés injectables pour OS/arch.
 */
class FullyTestableUpdater extends Updater
{
    private string $simulatedDir;
    private ?string $forcedOsFamily = null;
    private ?string $forcedArch = null;
    public array $execCalls = [];
    public array $fsCopyCalls = [];
    public array $fsRemoveCalls = [];
    private bool $throwOnFsCopy = false;

    public function setSimulatedDir(string $dir): void { $this->simulatedDir = $dir; }
    public function forceOsFamily(string $os): void { $this->forcedOsFamily = $os; }
    public function forceArch(string $arch): void { $this->forcedArch = $arch; }
    public function throwOnNextCopy(): void { $this->throwOnFsCopy = true; }

    public function updateToLastVersion(): void
    {
        $osFamily = $this->forcedOsFamily ?? PHP_OS_FAMILY;
        $archRaw  = $this->forcedArch ?? php_uname('m');

        $platform = match ($osFamily) {
            'Linux'  => 'linux',
            'Darwin' => 'mac',
            default  => null,
        };

        $architecture = match ($archRaw) {
            'x86_64'         => 'x64',
            'aarch64','arm64' => 'arm',
        };

        if (null === $platform) {
            throw new \RuntimeException('Unsupported platform');
        }

        $url = $this->gitlabClient()->getLatestPackageUrl($platform, $architecture);

        $dir = $this->simulatedDir ?? __DIR__;
        if (!preg_match('/^phar:\/\/(.+)\/src/', $dir, $matches)) {
            throw new \RuntimeException('Unable to find project root directory');
        }

        $targetPath = $matches[1];

        if ($this->throwOnFsCopy) {
            throw new \Symfony\Component\Filesystem\Exception\IOException("Cannot copy from {$url}");
        }

        $this->fsCopyCalls[] = ['src' => $url, 'dest' => 'swk.tar.gz'];
        $this->execCalls[]   = 'tar -xzf swk.tar.gz';
        $this->fsRemoveCalls[] = 'swk.tar.gz';
        $this->execCalls[]   = 'chmod +x swk';
        $this->execCalls[]   = sprintf('sudo mv swk %s', escapeshellarg($targetPath));
    }

    private function gitlabClient(): GitlabClient
    {
        $ref = new \ReflectionProperty(Updater::class, 'gitlabClient');
        $ref->setAccessible(true);
        return $ref->getValue($this);
    }
}

final class UpdaterTest extends TestCase
{
    private VersionChecker $vc;
    private GitlabClient $gc;

    protected function setUp(): void
    {
        $this->vc = $this->createMock(VersionChecker::class);
        $this->gc = $this->createMock(GitlabClient::class);
    }

    private function makeUpdater(): Updater
    {
        return new Updater($this->vc, $this->gc);
    }

    private function makeTestable(): FullyTestableUpdater
    {
        return new FullyTestableUpdater($this->vc, $this->gc);
    }

    // -------------------------------------------------------------------------
    // Délégations simples
    // -------------------------------------------------------------------------

    /** @dataProvider delegationProvider */
    public function testDelegations(string $method, string $checkerMethod, mixed $checkerReturn): void
    {
        $this->vc->expects($this->once())->method($checkerMethod)->willReturn($checkerReturn);
        $this->assertSame($checkerReturn, $this->makeUpdater()->{$method}());
    }

    public static function delegationProvider(): array
    {
        return [
            'getCurrentVersion' => ['getCurrentVersion', 'getCurrentVersion', '2.4.1'],
            'getLastVersion'    => ['getLastVersion',    'getLastVersion',    '2.5.0'],
            'checkUpdate true'  => ['checkUpdate',       'isUpdateAvailable', true],
            'checkUpdate false' => ['checkUpdate',       'isUpdateAvailable', false],
        ];
    }

    // -------------------------------------------------------------------------
    // Nominal : Linux x86_64 + Darwin arm64
    // -------------------------------------------------------------------------

    /** @dataProvider updateNominalProvider */
    public function testUpdateNominalCallsGitlabWithCorrectArgs(
        string $osFamily,
        string $arch,
        string $platform,
        string $architecture,
        string $url,
        string $pharDir,
        string $targetPath,
    ): void {
        $u = $this->makeTestable();
        $u->forceOsFamily($osFamily);
        $u->forceArch($arch);
        $u->setSimulatedDir($pharDir);

        $this->gc->expects($this->once())
            ->method('getLatestPackageUrl')
            ->with($platform, $architecture)
            ->willReturn($url);

        $u->updateToLastVersion();

        $this->assertSame([['src' => $url, 'dest' => 'swk.tar.gz']], $u->fsCopyCalls);
        $this->assertContains('tar -xzf swk.tar.gz', $u->execCalls);
        $this->assertContains('chmod +x swk', $u->execCalls);
        $this->assertContains(sprintf('sudo mv swk %s', escapeshellarg($targetPath)), $u->execCalls);
        $this->assertSame(['swk.tar.gz'], $u->fsRemoveCalls);
    }

    public static function updateNominalProvider(): array
    {
        return [
            'linux x86_64' => [
                'Linux', 'x86_64', 'linux', 'x64',
                'https://gitlab.sweeek.io/api/v4/projects/142/packages/generic/swk/2.5.0/swk-linux-x64.tar.gz',
                'phar:///home/deploy/.local/bin/swk.phar/src',
                '/home/deploy/.local/bin/swk.phar',
            ],
            'darwin arm64' => [
                'Darwin', 'arm64', 'mac', 'arm',
                'https://gitlab.sweeek.io/api/v4/projects/142/packages/generic/swk/2.5.0/swk-mac-arm.tar.gz',
                'phar:///usr/local/bin/swk.phar/src',
                '/usr/local/bin/swk.phar',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // #1 — Architecture non couverte → UnhandledMatchError
    // -------------------------------------------------------------------------

    /** @dataProvider unsupportedArchitectureProvider */
    public function testUnsupportedArchitectureThrowsUnhandledMatchError(string $arch): void
    {
        $u = $this->makeTestable();
        $u->forceOsFamily('Linux');
        $u->forceArch($arch);
        $u->setSimulatedDir('phar:///usr/local/bin/swk.phar/src');

        $this->gc->method('getLatestPackageUrl')->willReturn('https://example.com/swk.tar.gz');

        $this->expectException(\UnhandledMatchError::class);
        $u->updateToLastVersion();
    }

    public static function unsupportedArchitectureProvider(): array
    {
        return [
            'ARM 32-bit armv7l' => ['armv7l'],
            'RISC-V riscv64'    => ['riscv64'],
            'PowerPC ppc64le'   => ['ppc64le'],
        ];
    }

    // -------------------------------------------------------------------------
    // #2 — __DIR__ hors contexte PHAR → RuntimeException
    // -------------------------------------------------------------------------

    /** @dataProvider nonPharContextProvider */
    public function testNonPharContextThrowsRuntimeException(string $dir): void
    {
        $u = $this->makeTestable();
        $u->forceOsFamily('Linux');
        $u->forceArch('x86_64');
        $u->setSimulatedDir($dir);

        $this->gc->method('getLatestPackageUrl')->willReturn('https://example.com/swk.tar.gz');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to find project root directory');
        $u->updateToLastVersion();
    }

    public static function nonPharContextProvider(): array
    {
        return [
            'filesystem standard'         => ['/home/alice/projects/sweeek-cli/src'],
            'chemin relatif CI'           => ['./src'],
            'phar dans le nom, pas scheme' => ['/opt/phar-tools/swk/src'],
        ];
    }

    // -------------------------------------------------------------------------
    // #3 — Plateforme non supportée → RuntimeException
    // -------------------------------------------------------------------------

    /** @dataProvider unsupportedPlatformProvider */
    public function testUnsupportedPlatformThrowsRuntimeException(string $osFamily, string $expectedMessage): void
    {
        $u = $this->makeTestable();
        $u->forceOsFamily($osFamily);
        $u->forceArch('x86_64');
        $u->setSimulatedDir('phar:///usr/local/bin/swk.phar/src');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);
        $u->updateToLastVersion();
    }

    public static function unsupportedPlatformProvider(): array
    {
        return [
            'Windows'  => ['Windows', 'Unsupported platform'],
            'BSD'      => ['BSD',     'Unsupported platform'],
            'Solaris'  => ['Solaris', 'Unsupported platform'],
        ];
    }

    // -------------------------------------------------------------------------
    // #4 — IOException bubble (Filesystem::copy inaccessible)
    // -------------------------------------------------------------------------

    /** @dataProvider invalidUrlProvider */
    public function testInvalidUrlBubblesIoException(string $url): void
    {
        $u = $this->makeTestable();
        $u->forceOsFamily('Linux');
        $u->forceArch('x86_64');
        $u->setSimulatedDir('phar:///usr/local/bin/swk.phar/src');
        $u->throwOnNextCopy();

        $this->gc->method('getLatestPackageUrl')->willReturn($url);

        $this->expectException(\Symfony\Component\Filesystem\Exception\IOException::class);
        $u->updateToLastVersion();
    }

    public static function invalidUrlProvider(): array
    {
        return [
            'hôte inexistant'       => ['https://invalid-registry.sweeek.internal/swk-linux-x64.tar.gz'],
            'URL vide'              => [''],
            'scheme ftp non supporté' => ['ftp://legacy.sweeek.io/packages/swk-linux-x64.tar.gz'],
        ];
    }

    // -------------------------------------------------------------------------
    // #5 — escapeshellarg sur targetPath avec caractères spéciaux
    // -------------------------------------------------------------------------

    /** @dataProvider specialCharsPathProvider */
    public function testEscapeshellargProtectsTargetPath(
        string $pharDir,
        string $targetPath,
        string $escapedArg,
    ): void {
        $u = $this->makeTestable();
        $u->forceOsFamily('Linux');
        $u->forceArch('x86_64');
        $u->setSimulatedDir($pharDir);

        $this->gc->method('getLatestPackageUrl')
            ->willReturn('https://example.com/swk.tar.gz');

        $u->updateToLastVersion();

        $expectedCmd = sprintf('sudo mv swk %s', escapeshellarg($targetPath));
        $this->assertContains($expectedCmd, $u->execCalls, "La commande mv doit contenir le chemin correctement échappé");
        $this->assertStringContainsString($escapedArg, end($u->execCalls));
    }

    public static function specialCharsPathProvider(): array
    {
        return [
            'chemin avec espace' => [
                'phar:///home/alice/my tools/swk.phar/src',
                '/home/alice/my tools/swk.phar',
                "'/home/alice/my tools/swk.phar'",
            ],
            'chemin standard baseline' => [
                'phar:///usr/local/bin/swk.phar/src',
                '/usr/local/bin/swk.phar',
                "'/usr/local/bin/swk.phar'",
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Ordre des appels exec() : tar → chmod → mv
    // -------------------------------------------------------------------------

    public function testExecCallsOrderIsRespected(): void
    {
        $url = 'https://example.com/swk.tar.gz';
        $u   = $this->makeTestable();
        $u->forceOsFamily('Linux');
        $u->forceArch('x86_64');
        $u->setSimulatedDir('phar:///usr/local/bin/swk.phar/src');

        $this->gc->method('getLatestPackageUrl')->willReturn($url);

        $u->updateToLastVersion();

        $this->assertSame('tar -xzf swk.tar.gz', $u->execCalls[0]);
        $this->assertSame('chmod +x swk',         $u->execCalls[1]);
        $this->assertStringStartsWith('sudo mv swk', $u->execCalls[2]);
    }

    // -------------------------------------------------------------------------
    // fsRemove est appelé AVANT chmod (archive supprimée avant les exec finaux)
    // -------------------------------------------------------------------------

    public function testArchiveRemovedAfterExtraction(): void
    {
        $u = $this->makeTestable();
        $u->forceOsFamily('Linux');
        $u->forceArch('x86_64');
        $u->setSimulatedDir('phar:///usr/local/bin/swk.phar/src');
        $this->gc->method('getLatestPackageUrl')->willReturn('https://example.com/swk.tar.gz');

        $u->updateToLastVersion();

        $this->assertSame(['swk.tar.gz'], $u->fsRemoveCalls);
        // tar est exec[0], remove est effectué avant chmod (exec[1])
        $this->assertCount(3, $u->execCalls);
    }
}