<?php
declare(strict_types=1);

namespace App;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeParentInterface;
use Symfony\Component\Config\Definition\Builder\ParentNodeDefinitionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /** @inheritDoc */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('config');
        $rootNode = $treeBuilder->getRootNode();
        /** @phpstan-ignore-next-line */
        $rootNode->children()
            ->integerNode('maxWorkers')->defaultValue(4)->min(1)->max(10)->info('How many simultaneously active workers are possible. If you run more workers superfluous workers will sleep.')->end()
            ->scalarNode('zkCliPath')->isRequired()->info('Path to the zkCli.sh command')->end()
            ->scalarNode('zkDir')->info('Root ZooKeeper path to copy tasks')->defaultValue('/clickhouse-dev')->end()
            ->scalarNode('chCopierPath')->info('Path to the clickhouse-copier command')->defaultValue('clickhouse-copier')->end()
            ->arrayNode('servers')
            ->arrayPrototype()
            ->children()
            ->integerNode('monthDepths')->defaultValue(2)->info('Maximum month depth for tables with "Ym" partitions')->min(1)->max(12)->end()
            ->append($this->_addConnectionNode('release'))
            ->append($this->_addConnectionNode('dev'))
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Формируем описание конфигурации для соединения с ClickHouse
     *
     * @param string $connectionType
     * @return ArrayNodeDefinition|NodeBuilder|NodeDefinition|NodeParentInterface|ParentNodeDefinitionInterface|VariableNodeDefinition|null
     */
    private function _addConnectionNode(string $connectionType)
    {
        $treeBuilder = new TreeBuilder($connectionType);
        /** @phpstan-ignore-next-line */
        return $treeBuilder->getRootNode()
            ->isRequired()
            ->info($connectionType === 'dev' ? 'Destination server' : 'Source server')
            ->children()
            ->scalarNode('host')->isRequired()->end()
            ->integerNode('port')->end()
            ->scalarNode('username')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('password')->end()
            ->scalarNode('database')->isRequired()->cannotBeEmpty()->end()
            ->booleanNode('https')->defaultFalse()->end()
            ->scalarNode('sslCA')->defaultNull()->end()
            ->end();
    }
}
