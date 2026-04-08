<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Git\Helper;

class VersionTag implements \Stringable
{
    public const VERSION_REGEX = '/^(\d+)\.(\d+)\.(\d+)$/';

    private int $major;
    private int $feature;
    private int $minor;

    public function __construct(string $version)
    {
        if (!preg_match(self::VERSION_REGEX, $version, $matches)) {
            throw new \InvalidArgumentException('Invalid version format');
        }

        $this->major = (int) $matches[1];
        $this->feature = (int) $matches[2];
        $this->minor = (int) $matches[3];
    }

    public static function isValid(string $version): bool
    {
        return (bool) preg_match(self::VERSION_REGEX, $version);
    }

    public function incrementMajor(): void
    {
        $this->major++;
    }

    public function incrementFeature(): void
    {
        $this->feature++;
    }

    public function incrementMinor(): void
    {
        $this->minor++;
    }

    public function __toString(): string
    {
        return sprintf('%d.%d.%d', $this->major, $this->feature, $this->minor);
    }

    public function isEquals(string|VersionTag $version): bool
    {
        return (string) $version === (string) $this;
    }
}
