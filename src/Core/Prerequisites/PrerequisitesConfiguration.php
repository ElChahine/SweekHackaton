<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Prerequisites;

use Symfony\Component\Filesystem\Filesystem;
use Walibuy\Sweeecli\Core\Configuration\Project\ProjectInterface;
use Walibuy\Sweeecli\Core\Prerequisites\Enum\Architecture;
use Walibuy\Sweeecli\Core\Prerequisites\Enum\Platform;

class PrerequisitesConfiguration
{
    private array $prerequisites = [
        'platform' => 'all',
        'architecture' => 'all',
        'projects' => [],
        'validators' => [],
    ];

    public function hasPlatforms(Platform ...$platforms): self
    {
        $this->prerequisites['platform'] = $platforms;

        return $this;
    }

    public function hasArchitecture(Architecture ...$architectures): self
    {
        $this->prerequisites['architecture'] = $architectures;

        return $this;
    }

    public function hasProject(ProjectInterface ...$projects): self
    {
        $this->prerequisites['projects'] = $projects;

        return $this;
    }

    public function andOneOfFilesOrDirectoriesExist(string ...$files): self
    {
        $this->addCustomValidator(function () use ($files) {
            $fileSystem = new Filesystem();

            foreach ($files as $file) {
                if ($fileSystem->exists($file)) {
                    return true;
                }
            }

            return false;
        });

        return $this;
    }

    public function andFileOrDirectoryNotExist(string $file): self
    {
        $this->addCustomValidator(function () use ($file) {
            $fileSystem = new Filesystem();

            return !$fileSystem->exists($file);
        });

        return $this;
    }

    public function addCustomValidator(callable $validation): self
    {
        $this->prerequisites['validators'][] = $validation;

        return $this;
    }

    private function checkPlatform(): bool
    {
        if ('all' === $this->prerequisites['platform']) {
            return true;
        }

        $platform = match (PHP_OS_FAMILY) {
            'Linux' => Platform::LINUX,
            'Darwin' => Platform::MAC_OS,
        };

        return in_array($platform, $this->prerequisites['platform'], true);
    }

    private function checkArchitecture(): bool
    {
        if ('all' === $this->prerequisites['architecture']) {
            return true;
        }

        $architecture = match (php_uname('m')) {
            'x86_64' => Architecture::X64,
            'i386', 'i686' => Architecture::X86,
            'aarch64', 'arm64' => Architecture::ARM_64,
            'armv7l' => Architecture::ARM_32,
        };

        return in_array($architecture, $this->prerequisites['architecture'], true);
    }

    private function checkProjects(): bool
    {
        /** @var ProjectInterface $project */
        foreach ($this->prerequisites['projects'] as $project) {
            if (false === $project->isInstalled()) {
                return false;
            }
        }

        return true;
    }

    private function checkCustomValidators(): bool
    {
        foreach ($this->prerequisites['validators'] as $validator) {
            if (!$validator()) {
                return false;
            }
        }

        return true;
    }

    public function checkPrerequisites(): bool
    {
        return $this->checkPlatform()
            && $this->checkArchitecture()
            && $this->checkProjects()
            && $this->checkCustomValidators()
        ;
    }
}
