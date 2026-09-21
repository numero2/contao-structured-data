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
use Contao\CoreBundle\Twig\FragmentTemplate;
use Spatie\SchemaOrg\Graph;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


#[AsContentElement(category: 'miscellaneous')]
class StructuredDataController extends AbstractContentElementController {


    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly ResponseContextAccessor $responseContextAccessor,
        private readonly InsertTagParser $insertTagParser
    ) {
    }


    protected function getResponse( FragmentTemplate $template, ContentModel $model, Request $request ): Response {

        $json = (string) $model->structuredDataJSON;
        $isBackend = $this->scopeMatcher->isBackendRequest($request);

        if( $json !== '' && !$isBackend ) {

            $jsonLd = json_decode($json, true);

            if( \is_array($jsonLd) ) {

                $responseContext = $this->responseContextAccessor->getResponseContext();

                if( $responseContext?->has(JsonLdManager::class) ) {

                    // replace insert tags (see #3)
                    array_walk_recursive($jsonLd, function( &$value ) {
                        if( \is_string($value) ) {
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

        // this element won't create any visible markup in the frontend
        if( !$isBackend ) {
            return new Response('');
        }

        $template->set('json', $json);

        return $template->getResponse();
    }
}
