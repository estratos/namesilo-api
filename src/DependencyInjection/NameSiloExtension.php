<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class NameSiloExtension extends Extension
{
    private const PARAMETERS = [
        'api_key',
        'sandbox',
        'base_url',
        'timeout',
        'version',
        'response_type',
        'logging',
        'retry',
        'cache',
        'events',
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach (self::PARAMETERS as $name) {
            $value = $config[$name];

            if (null === $value || is_array($value) || is_bool($value) || is_float($value) || is_int($value) || is_string($value)) {
                $container->setParameter('namesilo.' . $name, $value);
            }
        }

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.php');
    }

    public function getAlias(): string
    {
        return 'namesilo';
    }
}
