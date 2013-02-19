<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');

$name = 'mv_qtyunit_';

$attributeData = array(
  //Global settings
  'type' => 'int',
  'input' => 'select',
  'label' => 'Unit (quantity)',
  'required' => false,

  //Catalogue setting
  'used_in_product_listing' => true,
);

$this->addAttribute($entityTypeId, $name, $attributeData);
