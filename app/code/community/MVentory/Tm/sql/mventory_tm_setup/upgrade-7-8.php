<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$attributesData = array(
  'tm_shipping_type' => array(
    //Global settings
    'type' => 'int',
    'input' => 'hidden',
    'label' => 'TM Shipping Type',
    'required' => false,
    'user_defined' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Catalogue setting
    'visible' => false,
    'is_configurable' => false
  ),

  'tm_allow_buy_now' => array(
    //Global settings
    'type' => 'int',
    'input' => 'hidden',
    'label' => 'TM Allow Buy Now',
    'required' => false,
    'user_defined' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Catalogue setting
    'visible' => false,
    'is_configurable' => false
  ),

  'tm_add_fees' => array(
    //Global settings
    'type' => 'int',
    'input' => 'hidden',
    'label' => 'TM Add Fees',
    'required' => false,
    'user_defined' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Catalogue setting
    'visible' => false,
    'is_configurable' => false
  ),

  'tm_category' => array(
    //Global settings
    'type' => 'int',
    'input' => 'hidden',
    'label' => 'TM Category',
    'required' => false,
    'user_defined' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Catalogue setting
    'visible' => false,
    'is_configurable' => false
  )
);

foreach ($attributesData as $name => $attributeData)
  $this
    ->addAttribute($entityTypeId, $name, $attributeData)
    ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
