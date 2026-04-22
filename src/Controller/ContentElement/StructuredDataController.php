<?php

/**
 * Structured Data Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\StructuredDataBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ResponseContext\JsonLd\JsonLdManager;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\System;
use Contao\Template;
use Spatie\SchemaOrg\Graph;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


#[AsContentElement(category: 'miscellaneous')]
class StructuredDataController extends AbstractContentElementController {


    /**
     * @var Contao\CoreBundle\Routing\ScopeMatcher
     */
    protected ScopeMatcher $scopeMatcher;

    /**
     * @var Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor
     */
    protected ResponseContextAccessor $responseContextAccessor;

    /**
     * @var Contao\CoreBundle\InsertTag\InsertTagParser
     */
    private InsertTagParser $insertTagParser;


    public function __construct( ScopeMatcher $scopeMatcher, ResponseContextAccessor $responseContextAccessor, InsertTagParser $insertTagParser ) {

        $this->scopeMatcher = $scopeMatcher;
        $this->responseContextAccessor = $responseContextAccessor;
        $this->insertTagParser = $insertTagParser;
    }


    protected function getResponse( Template $template, ContentModel $model, Request $request ): Response {

        $json = $model->structuredDataJSON;

        if( !empty($json) && !$this->scopeMatcher->isBackendRequest($request) ) {

            $jsonLd = json_decode($json,true);

            if( $jsonLd !== null && \is_array($jsonLd) ) {

                $responseContext = $this->responseContextAccessor->getResponseContext();

                if( $responseContext?->has(JsonLdManager::class) ) {

                    // replace insert tags (see #3)
                    array_walk_recursive($jsonLd, function( &$value ) {
                        if( is_string($value) ) {
                            $value = $this->insertTagParser->replace($value);
                        }
                    });

                    $jsonLdManager = $responseContext->get(JsonLdManager::class);
                    $type = $jsonLdManager->createSchemaOrgTypeFromArray($jsonLd);

                    $jsonLdManager
                        ->getGraphForSchema(JsonLdManager::SCHEMA_ORG)
                        ->set($type, $jsonLd['identifier'] ?? Graph::IDENTIFIER_DEFAULT)
                    ;
                }
            }
        }

        $template->json = $model->structuredDataJSON;

        if( $this->scopeMatcher->isBackendRequest($request) ) {

            return $template->getResponse();

        } else {

            return new Response('');
        }
    }
}
