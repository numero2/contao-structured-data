<?php

/**
 * Structured Data Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


/**
 * Modify the palettes
 */
$GLOBALS['TL_DCA']['tl_content']['palettes']['structured_data'] = '{type_legend},type;{config_legend},structuredDataType;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['structuredDataType_custom'] = 'structuredDataJSON;';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['structuredDataType_FAQPage'] = 'structuredDataFAQPage_mainEntity,structuredDataJSON';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['structuredDataType_Person'] = 'structuredDataName,structuredDataJobTitle,structuredDataWorksFor,structuredDataUrl,structuredDataEmail,structuredDataTelephone,structuredDataImage,structuredDataDescription,structuredDataSameAs,structuredDataJSON';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['structuredDataType_Organization'] = 'structuredDataName,structuredDataUrl,structuredDataEmail,structuredDataTelephone,structuredDataLogo,structuredDataDescription,structuredDataStreetAddress,structuredDataPostalCode,structuredDataAddressLocality,structuredDataAddressCountry,structuredDataSameAs,structuredDataJSON';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['structuredDataType_LocalBusiness'] = 'structuredDataName,structuredDataUrl,structuredDataEmail,structuredDataTelephone,structuredDataLogo,structuredDataImage,structuredDataDescription,structuredDataStreetAddress,structuredDataPostalCode,structuredDataAddressLocality,structuredDataAddressCountry,structuredDataOpeningHours,structuredDataPriceRange,structuredDataSameAs,structuredDataJSON';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['structuredDataType_Product'] = 'structuredDataName,structuredDataBrand,structuredDataSku,structuredDataImage,structuredDataDescription,structuredDataPrice,structuredDataPriceCurrency,structuredDataAvailability,structuredDataJSON';


/**
 * Modify the fields
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataType'] = [
    'filter'        => true
,   'translate'     => false
,   'inputType'     => 'select'
,   'reference'     => &$GLOBALS['TL_LANG']['MSC']['structuredData']['types']
,   'eval'          => ['mandatory'=>false, 'includeBlankOption'=>true, 'submitOnChange'=>true, 'chosen'=>true, 'tl_class'=>'w50']
,   'sql'           => "varchar(64) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataFAQPage_mainEntity'] = [
    'inputType'     => 'rowWizard'
,   'fields'        => [
        'question' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_content']['structuredDataFAQPage_mainEntityQuestion']
        ,   'inputType' => 'text'
        ]
    ,   'answer' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_content']['structuredDataFAQPage_mainEntityAnswer']
        ,   'inputType' => 'text'
        ]
    ]
,   'eval'          => ['tl_class'=>'clr', 'doNotSaveEmpty'=>true]
];

// simple fields without a column, these are converted from and to the JSON by the ContentListener
$tx = ['inputType'=>'text', 'translate'=>false, 'eval'=>['maxlength'=>255, 'tl_class'=>'w50']];
$img = ['inputType'=>'fileTree', 'translate'=>false, 'eval'=>['filesOnly'=>true, 'fieldType'=>'radio', 'extensions'=>'%contao.image.valid_extensions%', 'tl_class'=>'clr']];

$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataName'] = array_replace_recursive($tx, ['translate'=>true, 'eval'=>['mandatory'=>true, 'tl_class'=>'w50 clr']]);
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataDescription'] = ['inputType'=>'textarea', 'eval'=>['rows'=>3, 'tl_class'=>'clr']];
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataUrl'] = array_replace_recursive($tx, ['eval'=>['rgxp'=>'url', 'decodeEntities'=>true]]);
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataEmail'] = array_replace_recursive($tx, ['eval'=>['rgxp'=>'email']]);
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataTelephone'] = array_replace_recursive($tx, ['eval'=>['rgxp'=>'phone']]);
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataImage'] = $img;
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataLogo'] = $img;
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataJobTitle'] = array_replace($tx, ['translate'=>true]);
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataWorksFor'] = $tx;
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataStreetAddress'] = $tx;
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataPostalCode'] = array_replace_recursive($tx, ['eval'=>['maxlength'=>32]]);
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataAddressLocality'] = $tx;
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataAddressCountry'] = array_replace_recursive($tx, ['eval'=>['maxlength'=>64]]);
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataOpeningHours'] = ['inputType'=>'textarea', 'translate'=>false, 'eval'=>['rows'=>4, 'tl_class'=>'clr']];
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataPriceRange'] = $tx;
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataSameAs'] = ['inputType'=>'textarea', 'translate'=>false, 'eval'=>['rows'=>3, 'decodeEntities'=>true, 'tl_class'=>'clr']];
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataBrand'] = $tx;
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataSku'] = $tx;
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataPrice'] = array_replace_recursive($tx, ['eval'=>['rgxp'=>'digit', 'maxlength'=>16, 'mandatory'=>true]]);
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataPriceCurrency'] = array_replace_recursive($tx, ['eval'=>['maxlength'=>3, 'mandatory'=>true]]);
$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataAvailability'] = [
    'translate'     => false
,   'inputType'     => 'select'
,   'options'       => ['InStock', 'OutOfStock', 'PreOrder', 'BackOrder', 'LimitedAvailability', 'SoldOut', 'Discontinued']
,   'eval'          => ['tl_class'=>'w50']
];

unset($tx, $img);

$GLOBALS['TL_DCA']['tl_content']['fields']['structuredDataJSON'] = [
    'inputType'     => 'textarea'
,   'eval'          => ['preserveTags'=>true, 'decodeEntities'=>true, 'class'=>'monospace', 'rte'=>'ace', 'tl_class'=>'clr']
,   'sql'           => "mediumtext NULL"
];
