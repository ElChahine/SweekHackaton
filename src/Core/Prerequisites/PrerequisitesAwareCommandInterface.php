<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Prerequisites;

interface PrerequisitesAwareCommandInterface
{
    public function getPrerequisites(): PrerequisitesConfiguration;
}
