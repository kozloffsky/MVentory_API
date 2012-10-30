<?php

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
  'used_in_product_listing' => true,
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
  'input' => 'varchar',
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
