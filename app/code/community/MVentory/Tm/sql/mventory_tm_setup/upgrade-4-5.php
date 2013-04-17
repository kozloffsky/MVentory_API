<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$name = 'tm_account_id';

$attributeData = array(
  //Global settings
  'type' => 'varchar',
  'input' => 'select',
  'label' => 'TM Account ID',
  'source' => 'mventory_tm/entity_attribute_source_accounts',
  'required' => false,
  'user_defined' => false,
  'default' => null,
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

  //Catalogue setting
  'is_configurable' => false
);

$this
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
