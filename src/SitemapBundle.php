<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle;

use Ecourty\SitemapBundle\DependencyInjection\EcourtySitemapExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SitemapBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__;
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new EcourtySitemapExtension();
        }

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }
}
