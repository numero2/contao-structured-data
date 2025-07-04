<?php

/**
 * Structured Data Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2025, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\StructuredDataBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use numero2\StructuredDataBundle\StructuredDataBundle;


class Plugin implements BundlePluginInterface {


    public function getBundles( ParserInterface $parser ): array {

        return [
            BundleConfig::create(StructuredDataBundle::class)
                ->setLoadAfter([
                    ContaoCoreBundle::class
                ])
        ];
    }
}
