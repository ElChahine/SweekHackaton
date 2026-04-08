<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Helper;

class FolderHelper
{
    public static function getHomeFolder(): string
    {
        return $_ENV['HOME'] ?? getenv('HOME') ?: '';
    }

    public static function getSwkFolder(): string
    {
        return self::getHomeFolder().'/.swk';
    }
}
