<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Prerequisites\Enum;

enum Architecture
{
    case X64;
    case X86;
    case ARM_64;
    case ARM_32;
}
