<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle;

use Estratos\NameSiloBundle\DependencyInjection\NameSiloExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class NameSiloBundle extends AbstractBundle
{
    private const EXTENSION_ALIAS = 'namesilo';

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new NameSiloExtension();
        }

        return $this->extension;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register compiler passes if needed in the future
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}