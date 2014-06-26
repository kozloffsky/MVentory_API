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
 * @package MVentory/API
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$name = 'mv_created_date';

$attributeData = array(
  //Global settings
  'type' => 'datetime',
  'input' => 'date',
  'backend' => 'eav/entity_attribute_backend_datetime',
  'label' => 'Date added',
  'required' => false,
  'user_defined' => false,
  'default' => 0,
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

  //Catalogue setting
  'searchable' => true,
  'visible_on_front' => true,
  'visible_in_advanced_search' => true,
  'used_in_product_listing' => false,
  'used_for_sort_by' => true,
  'is_configurable' => false
);

$this
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);

$name = 'mv_created_userid';

$attributeData = array(
  //Global settings
  'type' => 'int',
  'input' => 'text',
  'label' => 'User ID',
  'required' => false,
  'user_defined' => false,
  'default' => -1,
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

  //Catalogue setting
  'is_configurable' => false
);

$this
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
