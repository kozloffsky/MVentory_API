<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$name = 'tm_relist';

$attributeData = array(
  //Global settings
  'type' => 'int',
  'input' => 'select',
  'label' => 'List on TM',
  'source' => 'eav/entity_attribute_source_boolean',
  'required' => false,
  'user_defined' => false,
  'default' => 1,
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

  //Catalogue setting
  'visible' => true,
  'is_configurable' => false
);

$this
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
