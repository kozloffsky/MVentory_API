<?php
$installer = $this;
$installer->startSetup();

$entityTypeId     = $installer->getEntityTypeId('catalog_product');
$attributeSetId   = $installer->getDefaultAttributeSetId($entityTypeId);

$installer->addAttribute(
    'catalog_product', 
    'mventory_tm_id',  
    array(
        'input' =>  'text',
        'type'  =>  'int',
        'label' =>  'MVentoryTm Id',
        'backend'   => '',
        'visible'   => true,
        'required'  => false,
        'user_defined'  => false,
        'global'    => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'default'   => ''
    )
);

$installer->addAttributeToGroup(
    $entityTypeId,
    $attributeSetId,
    $attributeGroupId,
    'mventory_tm_id',
    '100'
);


$entityTypeId     = $installer->getEntityTypeId('catalog_category');
$attributeSetId   = $installer->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $installer->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$installer->addAttribute(
    'catalog_category', 
    'mventory_tm_category',  
    array(
        'input' =>  'hidden',
        'type'  =>  'varchar',
        'label' =>  'MVentoryTm Category',
        'input_renderer' => 'mventory_tm/form_element_category',        
        'backend'   => '',
        'visible'   => true,
        'required'  => false,
        'user_defined'  => false,
        'global'    => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'default'   => ''
    )
);

$installer->addAttributeToGroup(
    $entityTypeId,
    $attributeSetId,
    $attributeGroupId,
    'mventory_tm_category',
    '20'
);

$attributeId = $installer->getAttributeId($entityTypeId, 'mventory_tm_category');

$installer->run("
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
    ->save();

$installer->endSetup();