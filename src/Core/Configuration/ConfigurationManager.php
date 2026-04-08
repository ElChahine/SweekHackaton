<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Configuration;

use Symfony\Component\Yaml\Yaml;
use Walibuy\Sweeecli\Core\Helper\FolderHelper;

class ConfigurationManager
{
    private const CONFIG_PATH = 'config.yaml';

    public function __construct(
        private DefinitionBuilder $definitionBuilder,
    ) {
    }

    public function getConfiguration(): array
    {
        $configPath = $this->getConfigFilePath();

        try {
            $userConfig = Yaml::parseFile($configPath);

            return $this->definitionBuilder->buildDefinition()->finalize($userConfig ?? []);
        } catch (\Exception) {
            return $this->getDefaultConfiguration();
        }
    }

    public function getDefaultConfiguration(): array
    {
        return $this->definitionBuilder->buildDefinition()->finalize([]);
    }

    public function getConfigFilePath(): string
    {
        return FolderHelper::getSwkFolder().'/'.self::CONFIG_PATH;
    }
}
