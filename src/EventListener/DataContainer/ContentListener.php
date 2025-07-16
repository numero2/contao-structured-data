<?php

/**
 * Structured Data Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2025, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\StructuredDataBundle\EventListener\DataContainer;

use \Exception;
use \JsonException;
use \ReflectionClass;
use \ReflectionMethod;
use Contao\ContentModel;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Routing\ResponseContext\JsonLd\JsonLdManager;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContext;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Spatie\SchemaOrg\Schema;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;


class ContentListener {


    /**
     * @var Symfony\Component\HttpFoundation\RequestStack
     */
    private $requestStack;

    /**
     * @var Symfony\Contracts\Translation\TranslatorInterface
     */
    private $translator;


    public function __construct( RequestStack $requestStack, TranslatorInterface $translator ) {

        $this->requestStack = $requestStack;
        $this->translator = $translator;
    }


    /**
     * Applies the correct subpalette for the selected type of structured data
     *
     * @param Contao\DataContainer $dc
     *
     * @return void
     */
    #[AsCallback('tl_content', target: 'config.onload')]
    public function applySubpaletteForType( DataContainer|null $dc=null ): void {

        if( $dc === null || !$dc->id || $this->requestStack->getCurrentRequest()->query->get('act') !== 'edit' ) {
            return;
        }

        $element = ContentModel::findById($dc->id);

        if( $element === null || $element->type !== 'structured_data' ) {
            return;
        }

        // find subpalette for chosen type
        foreach( $GLOBALS['TL_DCA']['tl_content']['subpalettes'] as $name => $fields ) {

            if( $name === 'structuredDataType_'.$element->structuredDataType ) {

                // rewrite "main" palette
                $GLOBALS['TL_DCA']['tl_content']['palettes']['structured_data'] = str_replace(
                    'structuredDataType;'
                ,   'structuredDataType,'.$fields.';'
                ,   $GLOBALS['TL_DCA']['tl_content']['palettes']['structured_data']
                );
            }
        }

        // disable editing of JSON
        if( $element->structuredDataType !== 'custom' ) {

            unset($GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataJSON']['eval']['rte']);
            $GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataJSON']['eval']['readonly'] = true;
            $GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataJSON']['label'] = [
                $this->translator->trans('tl_content.structuredDataJSONDisabled.0', [], 'contao_content')
            ,   $this->translator->trans('tl_content.structuredDataJSONDisabled.1', [], 'contao_content')
            ];
        }
    }


    /**
     * Lists supported types of schemas
     *
     * @param Contao\DataContainer $dc
     *
     * @return array
     */
    #[AsCallback('tl_content', target: 'fields.structuredDataType.options')]
    public function getTypeOptions( DataContainer|null $dc=null ): array {

        $options = [];
        $reflection = new ReflectionClass(Schema::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_STATIC);

        foreach( $methods as $method ) {

            if( $method->isPublic() && $method->getNumberOfRequiredParameters() === 0 ) {

                $name = ucfirst($method->getName());

                if( array_key_exists('structuredDataType_'.$name, $GLOBALS['TL_DCA']['tl_content']['subpalettes']) ) {
                    $options[$name] = $name.' [https://schema.org/'.$name.']';
                }
            }
        }

        asort($options);

        $options = ['custom' => $this->translator->trans('tl_content.structuredDataTypeCustom', [], 'contao_content')] + $options;

        return $options;
    }


    /**
     * Validates the json
     *
     * @param mixed $value
     * @param Contao\DataContainer $dc
     *
     * @return mixed
     */
    #[AsCallback('tl_content', target: 'fields.structuredDataJSON.save')]
    public function validateJSON( $value, DataContainer $dc ) {

        if( !empty($value) ) {

            try {

                $jsonLd = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

                $jsonLdManager = new JsonLdManager(new ResponseContext());
                $jsonLdManager->createSchemaOrgTypeFromArray($jsonLd);

            } catch( JsonException | Exception $e ) {

                $errorMsg = 'Invalid JSON: ' . $e->getMessage();
                throw new Exception($errorMsg);
            }
        }

        return $value;
    }


    /**
     * Handle loading of FAQPage entries
     *
     * @param mixed $value
     * @param \Contao\DataContainer $dc
     */
    #[AsCallback('tl_content', target: 'fields.structuredDataFAQPage_mainEntity.load')]
    public function loadFAQPageMainEntity( $value, DataContainer $dc ) {

        if( $dc === null || !$dc->id ) {
            return $value;
        }

        $json = json_decode($dc->activeRecord->structuredDataJSON,true);

        if( !empty($json['mainEntity']) ) {

            $value = [];

            foreach( $json['mainEntity'] as $row ) {

                $value[] = [
                    'question' => $row['name']
                ,   'answer' => $row['acceptedAnswer']['text']
                ];
            }
        }

        return $value;
    }


    /**
     * Handle saving of FAQPage entries
     *
     * @param mixed $value
     * @param \Contao\DataContainer $dc
     */
    #[AsCallback('tl_content', target: 'fields.structuredDataFAQPage_mainEntity.save')]
    public function saveFAQPageMainEntity( $value, DataContainer $dc ) {

        $request = $this->requestStack->getCurrentRequest();
        $json = $request->request->get('structuredDataJSON');
        $json = json_decode($json,true);

        $faqPage = null;

        if( empty($json) ) {

            $json = Schema::faqPage()
                ->mainEntity([])
                ->toArray()
            ;
        }

        $questions = StringUtil::deserialize($value,true);

        if( !empty($questions) ) {

            foreach( $questions as $i => $row ) {

                if( empty($row['question']) || empty($row['answer']) ) {
                    unset($questions[$i]);
                    continue;
                }

                $questions[$i] = Schema::question()
                    ->name($row['question'])
                    ->acceptedAnswer(
                        Schema::answer()->text($row['answer'])
                    )
                    ->toArray()
                ;
            }

            if( !empty($questions) ) {

                $json['mainEntity'] = $questions;

            } else {
                $json = null;
            }

            $element = ContentModel::findById($dc->id);
            $element->structuredDataJSON = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $element->save();
        }

        return null;
    }
}
