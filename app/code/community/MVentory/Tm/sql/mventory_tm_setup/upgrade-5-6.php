<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$name = 'tm_avoid_withdrawal';

$attributeData = array(
  //Global settings
  'type' => Varien_Db_Ddl_Table::TYPE_SMALLINT,
  'input' => 'hidden',
  'label' => 'TM Avoid withdraw',
  'required' => false,
  'user_defined' => false,
  'default' => -1,
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

  //Catalogue setting
  'visible' => false,
  'is_configurable' => false
);

$this
  ->removeAttribute($entityTypeId, 'tm_withdraw')
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
