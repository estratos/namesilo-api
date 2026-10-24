<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle;

use Estratos\NameSiloBundle\DependencyInjection\NameSiloExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class NameSiloBundle extends AbstractBundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        $extension = $this->extension;

        if (!$extension instanceof ExtensionInterface) {
            $extension = new NameSiloExtension();
            $this->extension = $extension;
        }

        return $extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
