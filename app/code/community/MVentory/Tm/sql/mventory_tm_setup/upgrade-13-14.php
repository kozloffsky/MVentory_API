<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$attributesData = array(
  'tm_match_id' => array(
    //Global settings
    'type' => 'int',
    'input' => 'hidden',
    'label' => 'TM Matched Category ID',
    'required' => false,
    'user_defined' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Catalogue setting
    'visible' => false,
    'is_configurable' => false
  ),

  'tm_match_name' => array(
    //Global settings
    'type' => 'varchar',
    'input' => 'hidden',
    'label' => 'TM Matched Category Name',
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
