<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Env\Tools;

use Symfony\Component\Filesystem\Filesystem;

class EnvTool
{
    public const CONFIG_FILE_NAME = 'env.config.yaml';
    public const DEFAULT_CONFIG = [
        '_mapping' => [],
        'app' => [
            'secrets' => [],
            'configmaps' => [],
        ],
    ];

    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function getEnvFileKeys(string $filePath): array
    {
        if (!$this->filesystem->exists($filePath)) {
            return [];
        }

        $data = $this->filesystem->readFile($filePath);

        preg_match_all('/(\w+)=.+/m', $data, $matches);

        return $matches[1] ?: [];
    }

    public function getCleanVariableName(string $variableName, array $mapping = []): string
    {
        return preg_replace(
            '/^_/',
            '',
            str_replace(
                '{ENV}',
                '',
                str_replace(
                    array_keys($mapping),
                    '',
                    $variableName
                )
            )
        );
    }

    public function getScopedVariableName(string $variableName, string $env, array $mapping = []): string
    {
        return str_replace(
            '{ENV}',
            $env,
            str_replace(
                array_keys($mapping),
                array_values($mapping),
                $variableName
            )
        );
    }
}
