<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Configuration\Project;

interface ProjectInterface
{
    public function getName(): string;

    public function getRepository(): string;

    public function markAsInstalled(): self;

    public function isInstalled(): bool;
}
