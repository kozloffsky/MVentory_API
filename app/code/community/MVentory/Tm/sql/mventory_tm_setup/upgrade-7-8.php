<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License BY-NC-ND.
 * NonCommercial — You may not use the material for commercial purposes.
 * NoDerivatives — If you remix, transform, or build upon the material,
 * you may not distribute the modified material.
 * See the full license at http://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * See http://mventory.com/legal/licensing/ for other licensing options.
 *
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$attributesData = array(
  'tm_shipping_type' => array(
    //Global settings
    'type' => 'int',
    'input' => 'select',
    'label' => 'Use free shipping',
    'source' => 'mventory_tm/entity_attribute_source_freeshipping',
    'required' => false,
    'user_defined' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Catalogue setting
    'visible' => true,
    'is_configurable' => false
  ),

  'tm_allow_buy_now' => array(
    //Global settings
    'type' => 'int',
    'input' => 'select',
    'label' => 'Allow Buy Now',
    'source' => 'mventory_tm/entity_attribute_source_boolean',
    'required' => false,
    'user_defined' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Catalogue setting
    'visible' => true,
    'is_configurable' => false
  ),

  'tm_add_fees' => array(
    //Global settings
    'type' => 'int',
    'input' => 'select',
    'label' => 'Add Fees',
    'source' => 'mventory_tm/entity_attribute_source_boolean',
    'required' => false,
    'user_defined' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Catalogue setting
    'visible' => true,
    'is_configurable' => false
  )
);

foreach ($attributesData as $name => $attributeData)
  $this
    ->addAttribute($entityTypeId, $name, $attributeData)
    ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
