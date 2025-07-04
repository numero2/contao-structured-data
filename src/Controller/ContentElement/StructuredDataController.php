<?php

/**
 * Structured Data Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2025, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\StructuredDataBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Routing\ResponseContext\JsonLd\JsonLdManager;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\System;
use Spatie\SchemaOrg\Graph;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


#[AsContentElement(category: 'miscellaneous')]
class StructuredDataController extends AbstractContentElementController {


    protected function getResponse( FragmentTemplate $template, ContentModel $model, Request $request ): Response {

        $json = $model->structuredDataJSON;

        if( !empty($json) && !$this->isBackendScope($request) ) {

            $jsonLd = json_decode($json,true);

            $responseContext = System::getContainer()->get('contao.routing.response_context_accessor')->getResponseContext();

		    if( $responseContext?->has(JsonLdManager::class) ) {

		        $jsonLdManager = $responseContext->get(JsonLdManager::class);
		        $type = $jsonLdManager->createSchemaOrgTypeFromArray($jsonLd);

                $jsonLdManager
                    ->getGraphForSchema(JsonLdManager::SCHEMA_ORG)
                    ->set($type, $jsonLd['identifier'] ?? Graph::IDENTIFIER_DEFAULT)
                ;
            }
        }

        $template->json = $model->structuredDataJSON;

        return $template->getResponse();
    }
}
