<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Enum;

enum RemoteType
{
    case MAIN;
    case FORK;
}
