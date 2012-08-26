<?php

//$this->startSetup();

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$name = 'mventory_tm_id';

$attributeData = array(
  //Global settings
  'type' => 'int',
  'input' => 'text',
  'label' => 'MVentory Tm ID',
  'required' => false,
  'user_defined' => true,
  'default' => 0,
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,

  //Catalogue setting
  'visible' => false,
  'is_configurable' => true
);

$this
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);

$entityTypeId = $this->getEntityTypeId('catalog_category');
$setId  = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$name = 'mventory_tm_category';

$attributeData = array(
  //Global settings
  'type' => 'varchar',
  'input' => 'hidden',
  'label' => 'MVentoryTm Category',
  'required' => false,
  'user_defined' => true,
  'default' => '',
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

  //Catalogue setting
  'visible' => true,
  'is_configurable' => true
);

$this
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
//  ->getAttributeId($entityTypeId, $name);

/*$installer->run("
INSERT INTO `{$installer->getTable('catalog_category_entity_varchar')}`
(`entity_type_id`, `attribute_id`, `entity_id`, `value`)
    SELECT '{$entityTypeId}', '{$attributeId}', `entity_id`, ''
        FROM `{$installer->getTable('catalog_category_entity')}`;
");

//this will set data of your custom attribute for root category
Mage::getModel('catalog/category')
    ->load(1)
    ->setImportedCatId(0)
    ->setInitialSetupFlag(true)
    ->save();

//this will set data of your custom attribute for default category
Mage::getModel('catalog/category')
    ->load(2)
    ->setImportedCatId(0)
    ->setInitialSetupFlag(true)
    ->save();*/

//$installer->endSetup();
