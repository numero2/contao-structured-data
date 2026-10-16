<?php

/**
 * Structured Data Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\StructuredDataBundle\EventListener\DataContainer;

use \Exception;
use \ReflectionClass;
use \ReflectionMethod;
use \ReflectionNamedType;
use \Throwable;
use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Routing\ResponseContext\JsonLd\JsonLdManager;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContext;
use Contao\DataContainer;
use Contao\StringUtil;
use Seld\JsonLint\DuplicateKeyException;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Schema;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;


class ContentListener {


    /**
     * Fields without a column mapped to their path in the JSON
     */
    private const SIMPLE_FIELDS = [
        'structuredDataName'            => 'name'
    ,   'structuredDataDescription'     => 'description'
    ,   'structuredDataUrl'             => 'url'
    ,   'structuredDataEmail'           => 'email'
    ,   'structuredDataTelephone'       => 'telephone'
    ,   'structuredDataImage'           => 'image'
    ,   'structuredDataLogo'            => 'logo'
    ,   'structuredDataJobTitle'        => 'jobTitle'
    ,   'structuredDataWorksFor'        => 'worksFor.name'
    ,   'structuredDataStreetAddress'   => 'address.streetAddress'
    ,   'structuredDataPostalCode'      => 'address.postalCode'
    ,   'structuredDataAddressLocality' => 'address.addressLocality'
    ,   'structuredDataAddressCountry'  => 'address.addressCountry'
    ,   'structuredDataOpeningHours'    => 'openingHours'
    ,   'structuredDataPriceRange'      => 'priceRange'
    ,   'structuredDataSameAs'          => 'sameAs'
    ,   'structuredDataBrand'           => 'brand.name'
    ,   'structuredDataSku'             => 'sku'
    ,   'structuredDataPrice'           => 'offers.price'
    ,   'structuredDataPriceCurrency'   => 'offers.priceCurrency'
    ,   'structuredDataAvailability'    => 'offers.availability'
    ];

    private const NESTED_TYPES = [
        'worksFor'  => 'Organization'
    ,   'address'   => 'PostalAddress'
    ,   'brand'     => 'Brand'
    ,   'offers'    => 'Offer'
    ];

    private ?string $activeType = null;

    /**
     * @var array<string,mixed>
     */
    private array $pendingValues = [];


    /**
     * @var string[]|null
     */
    private ?array $schemaTypes = null;


    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator
    ) {
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

        if( $dc === null || !$dc->id || $this->requestStack->getCurrentRequest()?->query->get('act') !== 'edit' ) {
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

                $this->registerSimpleFields((string) $element->structuredDataType, explode(',', $fields));
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
     *
     * @throws Exception
     */
    #[AsCallback('tl_content', target: 'fields.structuredDataJSON.save')]
    public function validateJSON( $value, DataContainer $dc ) {

        if( empty($value) ) {
            return $value;
        }

        // check syntax first, jsonlint gives us the line and a reason
        $parseError = (new JsonParser())->lint($value, JsonParser::DETECT_KEY_CONFLICTS);

        if( $parseError !== null ) {
            throw new Exception($this->getSyntaxErrorMessage($parseError));
        }

        $jsonLd = json_decode($value, true);

        if( !\is_array($jsonLd) || array_is_list($jsonLd) ) {
            throw new Exception($this->trans('structuredDataJSONErrorNoObject'));
        }

        $errors = $this->getStructureErrors($jsonLd);

        if( !empty($errors) ) {
            throw new Exception(implode(' ', $errors));
        }

        // make sure Contao can actually process the data
        try {

            $jsonLdManager = new JsonLdManager(new ResponseContext());
            $jsonLdManager->createSchemaOrgTypeFromArray($jsonLd);

        } catch( Throwable $e ) {

            throw new Exception($this->trans('structuredDataJSONErrorGeneric', [$e->getMessage()]));
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

        if( !$dc->id ) {
            return $value;
        }

        $json = json_decode((string) ($dc->getCurrentRecord()['structuredDataJSON'] ?? ''), true);

        if( !empty($json['mainEntity']) && \is_array($json['mainEntity']) ) {

            $value = [];

            foreach( $json['mainEntity'] as $row ) {

                $value[] = [
                    'question' => $row['name'] ?? ''
                ,   'answer' => $row['acceptedAnswer']['text'] ?? ''
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
        $json = json_decode((string) $request?->request->get('structuredDataJSON'), true);

        if( empty($json) || !\is_array($json) ) {

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

            $json['mainEntity'] = array_values($questions);

            $element = ContentModel::findById($dc->id);

            if( $element !== null ) {
                $element->structuredDataJSON = !empty($questions) ? json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : null;
                $element->save();
            }
        }

        return null;
    }


    /**
     * Writes the values of the simple fields (Person, Product, ...) into the JSON
     *
     * @param \Contao\DataContainer $dc
     */
    #[AsCallback('tl_content', target: 'config.onsubmit')]
    public function saveSimpleFields( DataContainer $dc ): void {

        if( empty($this->pendingValues) || $this->activeType === null ) {
            return;
        }

        $element = ContentModel::findById($dc->id);

        // the type was changed with this very request, the submitted values belong to the old one
        if( $element === null || $element->structuredDataType !== $this->activeType ) {
            return;
        }

        $json = ['@context'=>'https://schema.org', '@type'=>$this->activeType];

        foreach( $this->pendingValues as $field => $value ) {

            $value = $this->valueToJson($field, $value);

            if( $value === null ) {
                continue;
            }

            $path = explode('.', self::SIMPLE_FIELDS[$field]);

            if( \count($path) === 1 ) {
                $json[$path[0]] = $value;
            } else {
                $json[$path[0]] ??= ['@type'=>self::NESTED_TYPES[$path[0]]];
                $json[$path[0]][$path[1]] = $value;
            }
        }

        $this->pendingValues = [];

        $element->structuredDataJSON = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $element->save();
    }


    /**
     * Adds the callbacks which convert the simple fields from and to the JSON
     *
     * @param string $type
     * @param string[] $fields
     */
    private function registerSimpleFields( string $type, array $fields ): void {

        $this->activeType = $type;

        foreach( $fields as $field ) {

            if( !isset(self::SIMPLE_FIELDS[$field]) ) {
                continue;
            }

            // there are no columns for these fields, so empty values must not be written to the database
            $GLOBALS['TL_DCA']['tl_content']['fields'][$field]['eval']['doNotSaveEmpty'] = true;

            $GLOBALS['TL_DCA']['tl_content']['fields'][$field]['load_callback'][] = function( $value, DataContainer $dc ) use ( $field ) {

                $json = json_decode((string) ($dc->getCurrentRecord()['structuredDataJSON'] ?? ''), true);

                if( !\is_array($json) ) {
                    return $field === 'structuredDataPriceCurrency' ? 'EUR' : $value;
                }

                $found = $json;

                foreach( explode('.', self::SIMPLE_FIELDS[$field]) as $key ) {
                    $found = \is_array($found) ? ($found[$key] ?? null) : null;
                }

                return $found === null ? $value : $this->valueFromJson($field, $found);
            };

            $GLOBALS['TL_DCA']['tl_content']['fields'][$field]['save_callback'][] = function( $value ) use ( $field ) {

                $this->pendingValues[$field] = $value;

                return null;
            };
        }
    }


    /**
     * Converts a submitted value to the value stored in the JSON
     *
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    private function valueToJson( string $field, mixed $value ): mixed {

        switch( $field ) {

            case 'structuredDataImage':
            case 'structuredDataLogo':
                // resolved to a public (resized) image by the controller when the element is rendered
                return \strlen((string) $value) === 16 ? '{{file::'.StringUtil::binToUuid($value).'}}' : null;

            case 'structuredDataSameAs':
            case 'structuredDataOpeningHours':
                $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $value))));
                return $lines ?: null;

            case 'structuredDataPrice':
                $value = str_replace(',', '.', trim((string) $value));
                return $value !== '' ? $value : null;

            case 'structuredDataAvailability':
                return $value ? 'https://schema.org/'.$value : null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }


    /**
     * Converts a value stored in the JSON back to the value of the form field
     *
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    private function valueFromJson( string $field, mixed $value ): mixed {

        switch( $field ) {

            case 'structuredDataImage':
            case 'structuredDataLogo':
                return preg_match('/\{\{file::([0-9a-f-]{36})\}\}/', (string) $value, $match) ? StringUtil::uuidToBin($match[1]) : null;

            case 'structuredDataSameAs':
            case 'structuredDataOpeningHours':
                return implode("\n", (array) $value);

            case 'structuredDataAvailability':
                return str_replace('https://schema.org/', '', (string) $value);
        }

        return \is_scalar($value) ? (string) $value : null;
    }


    /**
     * Builds a readable error message for syntax errors
     *
     * @param Seld\JsonLint\ParsingException $e
     *
     * @return string
     */
    private function getSyntaxErrorMessage( ParsingException $e ): string {

        $lines = explode("\n", $e->getMessage());

        preg_match('/line (\d+)/', $lines[0], $match);
        $line = $match[1] ?? '?';

        if( $e instanceof DuplicateKeyException ) {
            return $this->trans('structuredDataJSONErrorDuplicateKey', [$e->getKey(), $line]);
        }

        // message consists of "Parse error on line X:", the position and the reason
        $position = trim($lines[1] ?? '');
        $reason = trim(end($lines));

        return $this->trans('structuredDataJSONErrorSyntax', [$line, $position, $reason]);
    }


    /**
     * Checks the given node and all nested nodes the same way the JsonLdManager
     * will process them and returns errors including the path of the node
     *
     * @param array $node
     * @param string $path
     *
     * @return array
     */
    private function getStructureErrors( array $node, string $path='' ): array {

        $location = $path === '' ? $this->trans('structuredDataJSONErrorLocationRoot') : $this->trans('structuredDataJSONErrorLocationPath', [$path]);

        if( !isset($node['@type']) ) {
            return [$this->trans('structuredDataJSONErrorMissingType', [$location])];
        }

        $types = $node['@type'];

        if( \is_string($types) ) {
            $types = [$types];
        }

        if( !\is_array($types) || $types === [] || array_filter($types, static fn( $type ) => !\is_string($type)) !== [] ) {
            return [$this->trans('structuredDataJSONErrorInvalidType', [$location])];
        }

        $errors = [];

        foreach( $types as $type ) {

            if( $this->isValidSchemaType($type) ) {
                continue;
            }

            $suggestion = $this->getSchemaTypeSuggestion($type);

            if( $suggestion !== null ) {
                $errors[] = $this->trans('structuredDataJSONErrorUnknownTypeSuggestion', [$location, $type, $suggestion]);
            } else {
                $errors[] = $this->trans('structuredDataJSONErrorUnknownType', [$location, $type]);
            }
        }

        foreach( $node as $key => $value ) {

            if( \is_array($value) && isset($value['@type']) ) {
                $errors = array_merge($errors, $this->getStructureErrors($value, ($path === '' ? '' : $path.'.').$key));
            }
        }

        return $errors;
    }


    /**
     * Checks if the given type exists as a schema.org type
     *
     * @param string $type
     *
     * @return bool
     */
    private function isValidSchemaType( string $type ): bool {

        return \in_array($type, $this->getSchemaTypes(), true);
    }


    /**
     * Tries to find the type that was most likely meant
     *
     * @param string $type
     *
     * @return string|null
     */
    private function getSchemaTypeSuggestion( string $type ): ?string {

        $needle = strtolower($type);
        $bestMatch = null;
        $bestDistance = null;

        foreach( $this->getSchemaTypes() as $candidate ) {

            $distance = levenshtein($needle, strtolower($candidate));

            if( $bestDistance === null || $distance < $bestDistance ) {
                $bestMatch = $candidate;
                $bestDistance = $distance;
            }
        }

        // only suggest if the difference is reasonably small
        if( $bestDistance !== null && $bestDistance <= max(1, (int) floor(\strlen($type) / 4)) ) {
            return $bestMatch;
        }

        return null;
    }


    /**
     * Returns the names of all available schema.org types
     *
     * @return string[]
     */
    private function getSchemaTypes(): array {

        if( $this->schemaTypes !== null ) {
            return $this->schemaTypes;
        }

        $this->schemaTypes = [];

        foreach( (new ReflectionClass(Schema::class))->getMethods(ReflectionMethod::IS_STATIC) as $method ) {

            $returnType = $method->getReturnType();

            if( $method->isPublic() && $returnType instanceof ReflectionNamedType && is_subclass_of($returnType->getName(), BaseType::class) ) {
                $this->schemaTypes[] = (new ReflectionClass($returnType->getName()))->getShortName();
            }
        }

        return $this->schemaTypes;
    }


    /**
     * Shortcut for translating error messages
     *
     * @param string $key
     * @param array $parameters
     *
     * @return string
     */
    private function trans( string $key, array $parameters=[] ): string {

        return $this->translator->trans('tl_content.'.$key, $parameters, 'contao_content');
    }
}
