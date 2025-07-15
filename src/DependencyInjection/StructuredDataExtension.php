<?php

/**
 * Structured Data Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2025, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\StructuredDataBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;


class StructuredDataExtension extends Extension {


    /**
     * {@inheritdoc}
     */
    public function load( array $mergedConfig, ContainerBuilder $container ): void {

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config')
        );

        $loader->load('controller.yaml');
        $loader->load('listener.yaml');
        $loader->load('services.yaml');
    }
}
