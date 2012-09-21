<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$name = 'tm_listing_id';

$attributeData = array(
  //Global settings
  'type' => 'int',
  'input' => 'hidden',
  'label' => 'Tm Listing ID',
  'required' => false,
  'user_defined' => false,
  'default' => 0,
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,

  //Catalogue setting
  'visible' => false,
  'is_configurable' => false
);

$this
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);

$entityTypeId = $this->getEntityTypeId('catalog_category');
$setId  = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getAttributeGroupId($entityTypeId, $setId,
                                      'General Information');

$name = 'tm_assigned_categories';

$attributeData = array(
  //Global settings
  'type' => 'varchar',
  'input' => 'hidden',
  'label' => 'Tm Assigned Categories',
  'required' => false,
  'user_defined' => false,
  'default' => '',
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

  //Catalogue setting
  'visible' => false,
  'is_configurable' => false
);

$this
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
