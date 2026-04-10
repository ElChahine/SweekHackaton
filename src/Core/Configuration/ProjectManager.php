<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Configuration;

use Walibuy\Sweeecli\Core\Configuration\Project\ProjectInterface;
use Walibuy\Sweeecli\Core\Configuration\Project\SwkProxy;
use Walibuy\Sweeecli\Core\Helper\FolderHelper;

class ProjectManager
{
    protected array $projects = [];

    public function getProject(string $fqcn): ?ProjectInterface
    {
        return $this->getProjects()[$fqcn] ?? null;
    }

    /**
     * @return ProjectInterface[]
     */
    public function getProjects(): array
    {
        if (!empty($this->projects)) {
            return $this->projects;
        }

        foreach ([new SwkProxy()] as $project) {
            if ($this->isInstalled($project)) {
                $project->markAsInstalled();
            }

            $this->projects[$project::class] = $project;
        }

        return $this->projects;
    }

    public function getProjectPath(?ProjectInterface $project = null): string
    {
        if (null !== $project) {
            return sprintf('%s/projects/%s', FolderHelper::getSwkFolder(), $project->getName());
        }

        return sprintf('%s/projects', FolderHelper::getSwkFolder());
    }

    protected function isInstalled(ProjectInterface $project): bool
    {
        $handle = @opendir($this->getProjectPath($project));

        if (false === is_resource($handle)) {
            return false;
        }

        while (($file = readdir($handle)) !== false) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }

            closedir($handle);

            return true;
        }

        closedir($handle);

        return false;
    }

    protected function getProjectModelNamespace(): string
    {
        return 'Walibuy\\Sweeecli\\Core\\Configuration\\Project';
    }
}
