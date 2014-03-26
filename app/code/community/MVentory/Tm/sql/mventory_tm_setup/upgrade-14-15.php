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
  'tm_current_listing_id' => array(
    //Global settings
    'type' => 'int',
    'input' => 'hidden',
    'label' => 'TM Current Listing ID',
    'required' => false,
    'user_defined' => false,
    'default' => 0,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Catalogue setting
    'visible' => false,
    'used_in_product_listing' => true,
    'is_configurable' => false
  ),

  'tm_current_account_id' => array(
    //Global settings
    'type' => 'varchar',
    'input' => 'hidden',
    'label' => 'TM Current Account ID',
    'required' => false,
    'user_defined' => false,
    'default' => '',
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Catalogue setting
    'visible' => false,
    'is_configurable' => false
  ),
);

foreach ($attributesData as $name => $attributeData)
  $this
    ->addAttribute($entityTypeId, $name, $attributeData)
    ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
