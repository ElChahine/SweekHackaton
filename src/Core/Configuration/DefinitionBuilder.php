<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\NodeInterface;

class DefinitionBuilder
{
    public function buildDefinition(): NodeInterface
    {
        $builder = new TreeBuilder('sweeecli');

        $builder->getRootNode()
            ->children()
                ->arrayNode('git')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('main_remote')
                            ->defaultValue('origin')
                            ->info('Main remote name')
                        ->end()
                        ->scalarNode('fork_remote')
                            ->defaultValue('fork')
                            ->info('Fork remote name')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $builder->buildTree();
    }
}
