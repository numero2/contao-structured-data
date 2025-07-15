<?php

/**
 * Structured Data Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2025, numero2 - Agentur für digitales Marketing GbR
 */


/**
 * Modify the palettes
 */
$GLOBALS['TL_DCA']['tl_content']['palettes']['structured_data'] = '{type_legend},type;{config_legend},structuredDataType;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['structuredDataType_custom'] = 'structuredDataJSON;';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['structuredDataType_FAQPage'] = 'structuredDataFAQPage_mainEntity,structuredDataJSON';


/**
 * Modify the fields
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataType'] = [
    'filter'        => true
,   'inputType'     => 'select'
,   'reference'     => &$GLOBALS['TL_LANG']['MSC']['structuredData']['types']
,   'eval'          => ['mandatory'=>false, 'includeBlankOption'=>true, 'submitOnChange'=>true, 'chosen'=>true, 'tl_class'=>'w50']
,   'sql'           => "varchar(64) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataFAQPage_mainEntity'] = [
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnFields' => [
            'question' => [
                'label'     => &$GLOBALS['TL_LANG']['tl_content']['structuredDataFAQPage_mainEntityQuestion']
            ,   'inputType' => 'text'
            ]
        ,   'answer' => [
                'label'     => &$GLOBALS['TL_LANG']['tl_content']['structuredDataFAQPage_mainEntityAnswer']
            ,   'inputType' => 'text'
            ]
        ]
    ,   'tl_class' => 'clr'
    ,   'doNotSaveEmpty' => true
    ]
];

$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataJSON'] = [
    'inputType'     => 'textarea'
,   'eval'          => ['preserveTags'=>true, 'decodeEntities'=>true, 'class'=>'monospace', 'rte'=>'ace', 'tl_class'=>'clr']
,   'sql'           => "text NULL"
];