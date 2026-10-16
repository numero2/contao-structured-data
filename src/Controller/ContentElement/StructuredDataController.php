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
use Contao\CoreBundle\Image\Studio\Studio;
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


    // images are scaled down to this width, taller ones keep their ratio
    private const IMAGE_MAX_WIDTH = 1200;


    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly ResponseContextAccessor $responseContextAccessor,
        private readonly InsertTagParser $insertTagParser,
        private readonly Studio $studio
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
                    array_walk_recursive($jsonLd, function( &$value ) use ( $request ) {
                        if( \is_string($value) ) {
                            $value = $this->replaceFiles($value, $request);
                            $value = $this->insertTagParser->replace($value);
                        }
                    });

                    $jsonLdManager = $responseContext->get(JsonLdManager::class);
                    $type = $jsonLdManager->createSchemaOrgTypeFromArray($jsonLd);

                    $graph = $jsonLdManager->getGraphForSchema(JsonLdManager::SCHEMA_ORG);
                    $identifier = $jsonLd['identifier'] ?? Graph::IDENTIFIER_DEFAULT;

                    // several products, persons, ... must not overwrite each other
                    if( !isset($jsonLd['identifier']) && !\in_array($model->structuredDataType, ['', 'custom', 'FAQPage'], true) ) {
                        $identifier = 'structured-data-'.$model->id;
                    }
                    $typeName = get_class($type);

                    // multiple elements of the same type would overwrite each other,
                    // so merge the questions of several FAQ elements into one FAQPage
                    if( $type->getType() === 'FAQPage' && $graph->has($typeName, $identifier) ) {

                        $existing = $graph->get($typeName, $identifier);
                        $merged = array_merge(
                            self::toList($existing->getProperty('mainEntity'))
                        ,   self::toList($type->getProperty('mainEntity'))
                        );
                        $existing->setProperty('mainEntity', $merged);

                    } else {
                        $graph->set($type, $identifier);
                    }
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


    /**
     * Replaces {{file::uuid}} with the absolute URL of a resized copy created by
     * the Image Studio, so the image is reachable even if the original is not public
     */
    private function replaceFiles( string $value, Request $request ): string {

        return preg_replace_callback('/(?:\{\{env::base\}\})?\{\{file::([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\}\}/', function( array $match ) use ( $request ) {

            $figure = $this->studio->createFigureBuilder()
                ->fromUuid($match[1])
                ->setSize([self::IMAGE_MAX_WIDTH, 0, 'proportional'])
                ->buildIfResourceExists()
            ;

            if( $figure === null ) {
                return '';
            }

            $image = $figure->getImage();
            $image->createIfDeferred();

            $src = $image->getImageSrc();

            if( preg_match('#^(https?:)?//#', $src) ) {
                return $src;
            }

            return $request->getSchemeAndHttpHost().'/'.ltrim($src, '/');

        }, $value);
    }


    /**
     * Normalizes a property value to a list
     */
    private static function toList( mixed $value ): array {

        if( $value === null ) {
            return [];
        }

        return \is_array($value) ? array_values($value) : [$value];
    }
}
