<?php

namespace Payever\Tests\BehatExtension\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Behat\EventDispatcher\ServiceContainer\EventDispatcherExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PlentyPluginsExtension implements Extension
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigKey()
    {
        return 'payever_plugins';
    }

    /**
     * {@inheritDoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->children()
                ->arrayNode('plugin')
                    ->addDefaultsIfNotSet()
                    ->normalizeKeys(true)
                    ->ignoreExtraKeys(false)
                    ->children()
                        ->scalarNode('connector_class')->cannotBeEmpty()->end()
                        ->scalarNode('cms_directory')->cannotBeEmpty()->end()
                        ->arrayNode('backend')
                            ->children()
                                ->scalarNode('url')->defaultValue('admin')->cannotBeEmpty()->end()
                                ->scalarNode('username')->defaultValue('admin')->cannotBeEmpty()->end()
                                ->scalarNode('password')->defaultValue('admin')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->arrayNode('products_sku')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
                ->scalarNode('fixtures_dir')
                    ->cannotBeEmpty()
                    ->defaultValue('vendor/payever/plugins-stub/fixtures')
                ->end()
            ->end();
    }

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $container->setParameter($this->prefixParameter('config'), $config);
        $container->setParameter($this->prefixParameter('fixtures_dir'), $config['fixtures_dir']);
        $container->register(\Payever\Stub\FixtureLoader::class)
            ->setArguments([
                sprintf('%%%s%%', $this->prefixParameter('fixtures_dir')),
            ])
            ->setShared(true);

        $container->register(\Payever\Stub\BehatExtension\Context\Initializer\FixtureAwareInitializer::class)
            ->setArguments([new Reference('Payever\Stub\FixtureLoader')])
            ->addTag(ContextExtension::INITIALIZER_TAG);

        $container->register(
            \Payever\Tests\BehatExtension\Context\Initializer\BackendCredentialsAwareInitializer::class
        )
            ->setArguments([
                $config['plugin']['backend']['url'],
                $config['plugin']['backend']['username'],
                $config['plugin']['backend']['password']
            ])
            ->addTag(ContextExtension::INITIALIZER_TAG);

        if (!empty($config['plugin']['connector_class'])) {
            $container->register($config['plugin']['connector_class'])
                ->setArguments([$config['plugin']['cms_directory']])
                ->setShared(true);

            $container->register(\Payever\Tests\BehatExtension\Listener\PluginListener::class)
                ->setArguments([
                    new Reference($config['plugin']['connector_class']),
                    sprintf('%%%s%%', $this->prefixParameter('config'))
                ])
                ->addTag(EventDispatcherExtension::SUBSCRIBER_TAG, ['priority' => 5]);
        }
    }

    /**
     * @param string $name
     * @return string
     */
    protected function prefixParameter($name)
    {
        return sprintf('%s.%s', $this->getConfigKey(), $name);
    }
}
