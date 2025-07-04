<?php

/**
 * Structured Data Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2025, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\StructuredDataBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;


class StructuredDataBundle extends Bundle {


    public function getPath(): string {

        return \dirname(__DIR__);
    }
}
