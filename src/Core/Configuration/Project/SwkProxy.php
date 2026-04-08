<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Configuration\Project;

class SwkProxy implements ProjectInterface
{
    public function __construct(protected bool $installed = false) {}

    public function getName(): string
    {
        return 'swk-proxy';
    }

    public function getRepository(): string
    {
        return 'git@gitlab.com:alicesgarden/swk-proxy.git';
    }

    public function markAsInstalled(): self
    {
        $this->installed = true;

        return $this;
    }

    public function isInstalled(): bool
    {
        return $this->installed;
    }
}
